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
            $items = $data['items'];
            $salePrice = (float) $data['sale_price'];
            $discount = (float) $data['discount'];
            $soldTo = $data['sold_to'] ?? null;
            $paymentMethod = $data['payment_method'] ?? null;
        
            $sales = [];
            $qu=0;
            foreach ($items as $item) {
                $productId = $item['product_id'];
                $quantity = (float) $item['quantity'];
        
                $customerStock = $this->customerStockRepository
                    ->getStockByCustomerAndProduct($customerId, $productId);
        
                // Decrease customer stock
                $customerStock->decrement('quantity', $quantity);
        
                if ($customerStock->quantity <= 0) {
                    $customerStock->delete();
                }
                $qu=$qu+$item['quantity'];
                // Create sale record
                
            }

            $sales = $this->customerSaleRepository->create([
                'customer_id'     => $customerId,
                'product_id'      => 1,
                'quantity'        => $qu,
                'items'           =>json_encode($items),
                'sale_price'      => $salePrice,
                'discount'        => $discount,
                'payment_method'  => $paymentMethod,
                'sold_to'         => $soldTo,
            ]);
            return $sales; // return AFTER loop
        });
    }
}
