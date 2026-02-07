<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CustomerSellRequest;
use App\Http\Resources\Api\CustomerSaleResource;
use App\Repositories\Contracts\CustomerSaleRepositoryInterface;
use App\Services\CustomerSaleService;
use Illuminate\Http\JsonResponse;

class SaleController extends Controller
{
    protected CustomerSaleService $customerSaleService;
    protected CustomerSaleRepositoryInterface $customerSaleRepository;

    public function __construct(
        CustomerSaleService $customerSaleService,
        CustomerSaleRepositoryInterface $customerSaleRepository
    ) {
        $this->customerSaleService = $customerSaleService;
        $this->customerSaleRepository = $customerSaleRepository;
    }

    public function sell(CustomerSellRequest $request): JsonResponse
    {
        try {
            
            $sale = $this->customerSaleService->sell([
                'customer_id' => auth()->id(),
                'items' => $request->items,
                'sale_price' => $request->sale_price,
                'discount' => $request->discount,
                'payment_method' => $request->payment_method,
                'sold_to' => $request->sold_to,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Sale recorded successfully',
                'data' => new CustomerSaleResource($sale->load('product')),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function history(): JsonResponse
    {
        $fromDate = request('from_date');
        $toDate = request('to_date');

        $sales = $this->customerSaleRepository->getSalesByCustomer(
            auth()->id(),
            $fromDate,
            $toDate
        );

        return response()->json([
            'success' => true,
            'data' => CustomerSaleResource::collection($sales),
        ]);
    }
}
