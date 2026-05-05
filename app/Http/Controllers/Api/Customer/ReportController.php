<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Repositories\Contracts\CustomerSaleRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
    public function __construct(
        protected CustomerSaleRepositoryInterface $customerSaleRepository
    ) {}

    public function appReport(): JsonResponse
    {
        $customerId = auth()->id();

        return response()->json([
            'success' => true,
            'data' => [
                'total_daily_sales' => $this->sumForPeriod($customerId, Carbon::today(), Carbon::today()),
                'total_weekly_sales' => $this->sumForPeriod($customerId, Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()),
                'total_monthly_sales' => $this->sumForPeriod($customerId, Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()),
            ],
        ]);
    }

    public function dailySales(): JsonResponse
    {
        $customerId = auth()->id();

        return response()->json([
            'success' => true,
            'data' => [
                'period' => 'daily',
                'sales' => $this->sumForPeriod($customerId, Carbon::today(), Carbon::today()),
            ],
        ]);
    }

    public function weeklySales(): JsonResponse
    {
        $customerId = auth()->id();

        return response()->json([
            'success' => true,
            'data' => [
                'period' => 'weekly',
                'sales' => $this->sumForPeriod($customerId, Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()),
            ],
        ]);
    }

    public function monthlySales(): JsonResponse
    {
        $customerId = auth()->id();

        return response()->json([
            'success' => true,
            'data' => [
                'period' => 'monthly',
                'sales' => $this->sumForPeriod($customerId, Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()),
            ],
        ]);
    }

    private function sumForPeriod(int $customerId, Carbon $from, Carbon $to): float
    {
        return $this->customerSaleRepository->getSalesTotalByDateRange(
            $customerId,
            $from->toDateString(),
            $to->toDateString()
        );
    }
}
