<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CustomerSellRequest;
use App\Http\Resources\Api\CustomerSaleResource;
use App\Repositories\Contracts\CustomerSaleRepositoryInterface;
use App\Services\CustomerSaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
                'discount' => $request->discount ?? 0,
                'payment_method' => $request->payment_method,
                'shop_owner_id' => $request->shop_owner_id,
                'sold_to' => $request->sold_to,
                'resaler_address' => $request->resaler_address,
                'resaler_phone' => $request->resaler_phone,
                'status' => $request->status ?? 'ordered',
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

    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.product_id' => ['required_with:items', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required_with:items', 'numeric', 'gt:0'],
            'sale_price' => ['sometimes', 'numeric', 'min:0'],
            'discount' => ['sometimes', 'numeric', 'min:0'],
            'payment_method' => ['sometimes', 'nullable', 'string', 'max:191'],
            'shop_owner_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('shop_owners', 'id')->where(fn ($query) => $query->where('distributor_id', auth()->id())),
            ],
            'sold_to' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $sale = $this->customerSaleRepository->findByIdAndCustomer($id, auth()->id());
        if (! $sale) {
            return response()->json([
                'success' => false,
                'message' => 'Sale not found',
            ], 404);
        }

        try {
            $updated = $this->customerSaleService->updateSale($sale, $request->only([
                'items', 'sale_price', 'discount', 'payment_method', 'shop_owner_id', 'sold_to',
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Sale updated successfully',
                'data' => new CustomerSaleResource($updated->load('product')),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function changeStatus(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'status' => ['required', 'string', Rule::in(['order', 'ordered', 'delivered', 'cancelled', 'canceled'])],
        ]);

        $sale = $this->customerSaleRepository->findByIdAndCustomer($id, auth()->id());
        if (! $sale) {
            return response()->json([
                'success' => false,
                'message' => 'Sale not found',
            ], 404);
        }

        try {
            $updated = $this->customerSaleService->updateStatus($sale, $request->status);

            return response()->json([
                'success' => true,
                'message' => 'Sale status updated successfully',
                'data' => new CustomerSaleResource($updated->load('product')),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function returnSale(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'return_reason' => ['required', 'string', 'max:1000'],
            'return_amount' => ['required', 'numeric', 'gt:0'],
        ]);

        $sale = $this->customerSaleRepository->findByIdAndCustomer($id, auth()->id());
        if (! $sale) {
            return response()->json([
                'success' => false,
                'message' => 'Sale not found',
            ], 404);
        }

        try {
            $updated = $this->customerSaleService->returnSale(
                $sale,
                $request->return_reason,
                (float) $request->return_amount
            );

            return response()->json([
                'success' => true,
                'message' => 'Sale return recorded successfully',
                'data' => new CustomerSaleResource($updated->load('product')),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        $sale = $this->customerSaleRepository->findByIdAndCustomer($id, auth()->id());
        if (! $sale) {
            return response()->json([
                'success' => false,
                'message' => 'Sale not found',
            ], 404);
        }

        try {
            $this->customerSaleService->deleteSale($sale);

            return response()->json([
                'success' => true,
                'message' => 'Sale deleted successfully',
            ]);
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

        $summary = [
            'order_amount' => (float) $sales->sum('order_amount'),
            'delivery_amount' => (float) $sales->sum('delivery_amount'),
            'return_amount' => (float) $sales->sum('return_amount'),
            'total' => (float) $sales->sum('total_amount'),
        ];

        return response()->json([
            'success' => true,
            'data' => CustomerSaleResource::collection($sales),
            'summary' => $summary,
        ]);
    }
}
