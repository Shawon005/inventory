<?php

namespace App\Services;

use App\CustomerSale;
use App\Repositories\Contracts\CustomerSaleRepositoryInterface;
use App\Repositories\Contracts\CustomerStockRepositoryInterface;
use Illuminate\Support\Facades\DB;

class CustomerSaleService
{
    private const STATUS_ORDERED = 'ordered';
    private const STATUS_DELIVERED = 'delivered';
    private const STATUS_CANCELLED = 'cancelled';

    public function __construct(
        protected CustomerStockRepositoryInterface $customerStockRepository,
        protected CustomerSaleRepositoryInterface $customerSaleRepository
    ) {}

    /**
     * Process customer sale (POS) - decrease stock and record profit.
     *
     * @throws \Exception
     */
    public function sell(array $data): CustomerSale
    {
        return DB::transaction(function () use ($data) {
            $customerId = $data['customer_id'];
            $stockCustomerId = $this->resolveStockCustomerId($customerId);
            $items = $this->normalizeItems($data['items']);
            $salePrice = (float) $data['sale_price'];
            $discount = (float) $data['discount'];
            $soldTo = $data['sold_to'] ?? null;
            $resalerAddress = $data['resaler_address'] ?? null;
            $resalerPhone = $data['resaler_phone'] ?? null;
            $paymentMethod = $data['payment_method'] ?? null;
            $status = $this->normalizeStatus($data['status'] ?? self::STATUS_ORDERED);

            if ($status === self::STATUS_DELIVERED) {
                $this->decreaseStockForItems($stockCustomerId, $items);
            }

            $sales = $this->customerSaleRepository->create([
                'customer_id'     => $customerId,
                'product_id'      => (int) $items[0]['product_id'],
                'quantity'        => $this->sumQuantity($items),
                'items'           => json_encode($items),
                'sale_price'      => $salePrice,
                'discount'        => $discount,
                'payment_method'  => $paymentMethod,
                'sold_to'         => $soldTo,
                'resaler_address' => $resalerAddress,
                'resaler_phone' => $resalerPhone,
                'status'          => $status,
            ]);

            return $sales; // return AFTER loop
        });
    }

    /**
     * @throws \Exception
     */
    public function updateSale(CustomerSale $sale, array $data): CustomerSale
    {
        return DB::transaction(function () use ($sale, $data) {
            $currentStatus = $this->currentStatus($sale);
            $oldItems = $this->normalizeItems($this->decodeItems($sale->items));
            $newItems = isset($data['items']) ? $this->normalizeItems($data['items']) : $oldItems;
            $stockCustomerId = $this->resolveStockCustomerId((int) $sale->customer_id);

            if ($currentStatus === self::STATUS_DELIVERED) {
                $this->adjustStockForDeliveredEdit($stockCustomerId, $oldItems, $newItems);
            }

            $payload = [];
            if (isset($data['items'])) {
                $payload['items'] = json_encode($newItems);
                $payload['product_id'] = (int) $newItems[0]['product_id'];
                $payload['quantity'] = $this->sumQuantity($newItems);
            }
            if (array_key_exists('sale_price', $data)) {
                $payload['sale_price'] = (float) $data['sale_price'];
            }
            if (array_key_exists('discount', $data)) {
                $payload['discount'] = (float) $data['discount'];
            }
            if (array_key_exists('payment_method', $data)) {
                $payload['payment_method'] = $data['payment_method'];
            }
            if (array_key_exists('sold_to', $data)) {
                $payload['sold_to'] = $data['sold_to'];
            }

            return $this->customerSaleRepository->update($sale, $payload);
        });
    }

    /**
     * @throws \Exception
     */
    public function updateStatus(CustomerSale $sale, string $nextStatus): CustomerSale
    {
        return DB::transaction(function () use ($sale, $nextStatus) {
            $currentStatus = $this->currentStatus($sale);
            $nextStatus = $this->normalizeStatus($nextStatus);
            $stockCustomerId = $this->resolveStockCustomerId((int) $sale->customer_id);

            if ($currentStatus === $nextStatus) {
                return $sale;
            }

            if ($currentStatus === self::STATUS_DELIVERED && $nextStatus !== self::STATUS_DELIVERED) {
                throw new \Exception('Delivered sale status cannot be changed.');
            }

            if ($nextStatus === self::STATUS_DELIVERED) {
                $items = $this->normalizeItems($this->decodeItems($sale->items));
                $this->decreaseStockForItems($stockCustomerId, $items);
            }

            return $this->customerSaleRepository->update($sale, ['status' => $nextStatus, 'updated_at' => now()]);
        });
    }

    /**
     * @throws \Exception
     */
    public function deleteSale(CustomerSale $sale): void
    {
        DB::transaction(function () use ($sale) {
            if ($this->currentStatus($sale) === self::STATUS_DELIVERED) {
                throw new \Exception('Delivered sale cannot be deleted.');
            }

            $this->customerSaleRepository->delete($sale);
        });
    }

    private function normalizeItems(array $items): array
    {
        $normalized = [];


        foreach ($items as $item) {
            $normalized[] = [
                'product_id' => (int) $item['product_id'],
                'quantity' => (float) $item['quantity'],
                'name' => $item['name'] ?? null,
                'sale_price' => isset($item['sale_price']) ? (float) $item['sale_price'] : null,
                'free_item' => isset($item['free_item']) ? (float) $item['free_item'] : 0,
            ];
        }

        return $normalized;
    }

    private function normalizeStatus(string $status): string
    {
        $status = strtolower(trim($status));

        if ($status === 'order') {
            return self::STATUS_ORDERED;
        }

        if ($status === 'canceled') {
            return self::STATUS_CANCELLED;
        }

        if (! in_array($status, [self::STATUS_ORDERED, self::STATUS_DELIVERED, self::STATUS_CANCELLED], true)) {
            throw new \Exception('Invalid status. Allowed: ordered, delivered, cancelled');
        }

        return $status;
    }

    private function currentStatus(CustomerSale $sale): string
    {
        // Backward compatibility: old rows had stock deducted at create time and no status.
        if (empty($sale->status)) {
            return self::STATUS_DELIVERED;
        }

        return $this->normalizeStatus((string) $sale->status);
    }

    private function decodeItems($items): array
    {
        if (is_array($items)) {
            return $items;
        }

        if (empty($items)) {
            return [];
        }

        $decoded = json_decode((string) $items, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function sumQuantity(array $items): float
    {
        return array_reduce($items, function ($carry, $item) {
            return $carry + (float) $item['quantity'];
        }, 0.0);
    }

    private function aggregateItems(array $items): array
    {
        $aggregated = [];
        foreach ($items as $item) {
            $productId = (int) $item['product_id'];
            $qty = (float) $item['quantity'];

            if (! isset($aggregated[$productId])) {
                $aggregated[$productId] = 0.0;
            }

            $aggregated[$productId] += $qty;
        }

        return $aggregated;
    }

    /**
     * @throws \Exception
     */
    private function decreaseStockForItems(int $customerId, array $items): void
    {
        $requiredByProduct = $this->aggregateItems($items);

        foreach ($requiredByProduct as $productId => $requiredQty) {
            $stock = $this->customerStockRepository->getStockByCustomerAndProduct($customerId, (int) $productId);

            if (empty($stock) || (float) $stock->quantity < $requiredQty) {
                throw new \Exception("Insufficient stock for product id {$productId}.");
            }
        }

        foreach ($requiredByProduct as $productId => $requiredQty) {
            $stock = $this->customerStockRepository->getStockByCustomerAndProduct($customerId, (int) $productId);
            $stock->decrement('quantity', $requiredQty);

            $stock->refresh();
            if ((float) $stock->quantity <= 0) {
                $stock->delete();
            }
        }
    }

    /**
     * @throws \Exception
     */
    private function adjustStockForDeliveredEdit(int $customerId, array $oldItems, array $newItems): void
    {
        $oldByProduct = $this->aggregateItems($oldItems);
        $newByProduct = $this->aggregateItems($newItems);
        $productIds = array_unique(array_merge(array_keys($oldByProduct), array_keys($newByProduct)));

        foreach ($productIds as $productId) {
            $oldQty = (float) ($oldByProduct[$productId] ?? 0);
            $newQty = (float) ($newByProduct[$productId] ?? 0);
            $delta = $newQty - $oldQty;

            if ($delta > 0) {
                $stock = $this->customerStockRepository->getStockByCustomerAndProduct($customerId, (int) $productId);
                if (empty($stock) || (float) $stock->quantity < $delta) {
                    throw new \Exception("Insufficient stock for product id {$productId}.");
                }
            }
        }

        foreach ($productIds as $productId) {
            $oldQty = (float) ($oldByProduct[$productId] ?? 0);
            $newQty = (float) ($newByProduct[$productId] ?? 0);
            $delta = $newQty - $oldQty;

            if ($delta > 0) {
                $stock = $this->customerStockRepository->getStockByCustomerAndProduct($customerId, (int) $productId);
                $stock->decrement('quantity', $delta);
                $stock->refresh();
                if ((float) $stock->quantity <= 0) {
                    $stock->delete();
                }
            } elseif ($delta < 0) {
                $stock = $this->customerStockRepository->getStockByCustomerAndProduct($customerId, (int) $productId);
                if ($stock) {
                    $stock->increment('quantity', abs($delta));
                } else {
                    $this->customerStockRepository->updateOrCreate(
                        ['customer_id' => $customerId, 'product_id' => (int) $productId],
                        ['quantity' => abs($delta)]
                    );
                }
            }
        }
    }

    private function resolveStockCustomerId(int $customerId): int
    {
        $customer = \App\Customer::find($customerId);

        if (! $customer) {
            return $customerId;
        }

        return (int) $customer->resolved_stock_owner_id;
    }
}
