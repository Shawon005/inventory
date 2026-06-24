<?php

namespace App\Services;

use App\CustomerStock;
use App\CustomerStockTransfer;
use App\Repositories\Contracts\CustomerStockRepositoryInterface;
use App\Variation;
use App\VariationLocationDetails;
use Illuminate\Support\Facades\DB;

class CustomerStockTransferService
{
    public function __construct(
        protected CustomerStockRepositoryInterface $customerStockRepository
    ) {}

    /**
     * Transfer stock from main inventory to customer.
     *
     * @throws \Exception
     */
    public function transfer(array $data): CustomerStockTransfer
    {
        return DB::transaction(function () use ($data) {
            $productId = $data['product_id'];
            $variationId = $data['variation_id'] ?? null;
            $locationId = $data['location_id'];
            $stockCustomerId = $this->resolveStockCustomerId((int) $data['customer_id']);

            if (! $variationId) {
                $variation = Variation::where('product_id', $productId)->first();
                $variationId = $variation ? $variation->id : null;
            }
            if (! $variationId) {
                throw new \Exception('Product has no variation. Please select a variation.');
            }
            $quantity = (float) $data['quantity'];
            $transferPrice = (float) $data['transfer_price'];

            // Validate main inventory stock
            $vld = VariationLocationDetails::where('variation_id', $variationId)
                ->where('product_id', $productId)
                ->where('location_id', $locationId)
                ->first();

            if (! $vld || $vld->qty_available < $quantity) {
                throw new \Exception('Insufficient stock in main inventory. Available: '.($vld->qty_available ?? 0));
            }

            // Decrease main inventory
            $vld->decrement('qty_available', $quantity);

            // Add to customer stock
            $customerStock = $this->customerStockRepository->getStockByCustomerAndProduct(
                $stockCustomerId,
                $productId
            );

            if ($customerStock) {
                // Free items (price <= 0) should increase quantity only and must not
                // reduce the existing average purchase price.
                if ($transferPrice <= 0) {
                    $customerStock->increment('quantity', $quantity);
                } else {
                    $avgPrice = (($customerStock->purchase_price * $customerStock->quantity) + ($transferPrice * $quantity))
                        / ($customerStock->quantity + $quantity);
                    $customerStock->increment('quantity', $quantity);
                    $customerStock->update(['purchase_price' => $avgPrice]);
                }
            } else {
                $this->customerStockRepository->updateOrCreate(
                    [
                        'customer_id' => $stockCustomerId,
                        'product_id' => $productId,
                    ],
                    [
                        'variation_id' => $variationId,
                        'quantity' => $quantity,
                        'purchase_price' => $transferPrice,
                    ]
                );
            }

            // Create transfer record
            return CustomerStockTransfer::create([
                'customer_id' => $stockCustomerId,
                'product_id' => $productId,
                'variation_id' => $variationId,
                'location_id' => $locationId,
                'quantity' => $quantity,
                'transfer_price' => $transferPrice,
                'total_amount' => $quantity * $transferPrice,
                'created_by' => $data['created_by'],
            ]);
        });
    }

    protected function resolveStockCustomerId(int $customerId): int
    {
        $customer = \App\Customer::find($customerId);

        if (! $customer) {
            return $customerId;
        }

        return (int) $customer->resolved_stock_owner_id;
    }
    public function reverseTransfer(CustomerStockTransfer $transfer): void
    {
        DB::transaction(function () use ($transfer) {
            $productId = $transfer->product_id;
            $variationId = $transfer->variation_id;
            $locationId = $transfer->location_id;
            $quantity = (float) $transfer->quantity;

            // Restore main inventory
            $vld = VariationLocationDetails::where('variation_id', $variationId)
                ->where('product_id', $productId)
                ->where('location_id', $locationId)
                ->firstOrFail();

            $vld->increment('qty_available', $quantity);

            // Remove from customer stock
            $customerStock = $this->customerStockRepository->getStockByCustomerAndProduct(
                $transfer->customer_id,
                $productId
            );

            if ($customerStock) {
                $newQuantity = $customerStock->quantity - $quantity;
                
                if ($newQuantity <= 0) {
                    // Delete if quantity becomes 0 or less
                    $customerStock->delete();
                } else {
                    $customerStock->decrement('quantity', $quantity);
                }
            }

            // Delete the transfer record
            $transfer->delete();
        });
    }
}
