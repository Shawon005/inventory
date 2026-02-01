<?php

namespace App\Services;

use App\CustomerSale;
use App\Repositories\Contracts\CustomerSaleRepositoryInterface;
use App\Repositories\Contracts\CustomerStockRepositoryInterface;
use Illuminate\Support\Facades\DB;

class CustomerSaleService
{
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
            $productId = $data['product_id'];
            $quantity = (float) $data['quantity'];
            $salePrice = (float) $data['sale_price'];
            $soldTo = $data['sold_to'] ?? null;

            $customerStock = $this->customerStockRepository->getStockByCustomerAndProduct($customerId, $productId);

            if (! $customerStock || $customerStock->quantity < $quantity) {
                throw new \Exception('Insufficient stock. Available: '.($customerStock->quantity ?? 0));
            }

            $purchasePrice = $customerStock->purchase_price;
            $profit = ($salePrice - $purchasePrice) * $quantity;

            $variationId = $customerStock->variation_id;

            // Decrease customer stock
            $customerStock->decrement('quantity', $quantity);

            if ($customerStock->quantity <= 0) {
                $customerStock->delete();
            }

            // Create sale record
            return $this->customerSaleRepository->create([
                'customer_id' => $customerId,
                'product_id' => $productId,
                'variation_id' => $variationId,
                'quantity' => $quantity,
                'purchase_price' => $purchasePrice,
                'sale_price' => $salePrice,
                'profit' => $profit,
                'sold_to' => $soldTo,
            ]);
        });
    }
}
