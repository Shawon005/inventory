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
        $customerId = auth()->id();
        $stocks = $this->customerStockRepository->getCustomerStocks($customerId);

        return response()->json([
            'success' => true,
            'data' => CustomerStockResource::collection($stocks),
        ]);
    }
}
