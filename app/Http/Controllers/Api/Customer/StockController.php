<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\CustomerStockResource;
use App\Repositories\Contracts\CustomerStockRepositoryInterface;
use Illuminate\Http\JsonResponse;

class StockController extends Controller
{
    protected CustomerStockRepositoryInterface $customerStockRepository;

    public function __construct(CustomerStockRepositoryInterface $customerStockRepository)
    {
        $this->customerStockRepository = $customerStockRepository;
    }

    public function index(): JsonResponse
    {
        $customer = auth()->user();
        $customerId = (int) ($customer->resolved_stock_owner_id ?? $customer->id);
        $stocks = $this->customerStockRepository->getCustomerStocks($customerId);

        return response()->json([
            'success' => true,
            'data' => CustomerStockResource::collection($stocks),
        ]);
    }
}
