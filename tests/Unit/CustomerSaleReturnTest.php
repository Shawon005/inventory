<?php

namespace Tests\Unit;

use App\CustomerSale;
use App\Http\Resources\Api\CustomerSaleResource;
use App\Repositories\Contracts\CustomerSaleRepositoryInterface;
use App\Repositories\Contracts\CustomerStockRepositoryInterface;
use App\Services\CustomerSaleService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class CustomerSaleReturnTest extends TestCase
{
    public function test_resource_exposes_return_amounts_and_net_total(): void
    {
        $sale = new CustomerSale;
        $sale->forceFill([
            'id' => 10,
            'items' => '[]',
            'sale_price' => 1000,
            'return_amount' => 250,
            'return_reason' => 'Two damaged products',
            'status' => 'delivered',
            'returned_at' => Carbon::parse('2026-08-12 10:00:00'),
            'created_at' => Carbon::parse('2026-08-12 09:00:00'),
            'updated_at' => Carbon::parse('2026-08-12 10:00:00'),
        ]);
        $sale->setRelation('product', null);

        $data = (new CustomerSaleResource($sale))->toArray(request());

        $this->assertSame(1000.0, $data['order_amount']);
        $this->assertSame(1000.0, $data['delivery_amount']);
        $this->assertSame(250.0, $data['return_amount']);
        $this->assertSame(750.0, $data['total']);
        $this->assertSame('partial', $data['return_status']);
        $this->assertSame('Two damaged products', $data['return_reason']);
    }

    public function test_return_service_rejects_value_above_order_amount(): void
    {
        DB::shouldReceive('transaction')->once()->andReturnUsing(function ($callback) {
            return $callback();
        });

        $stockRepository = Mockery::mock(CustomerStockRepositoryInterface::class);
        $saleRepository = Mockery::mock(CustomerSaleRepositoryInterface::class);
        $saleRepository->shouldNotReceive('update');

        $sale = new CustomerSale([
            'customer_id' => 1,
            'sale_price' => 100,
            'status' => 'delivered',
        ]);

        $service = new CustomerSaleService($stockRepository, $saleRepository);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Return amount cannot be greater than the order amount.');

        $service->returnSale($sale, 'Damaged', 101);
    }

    public function test_return_service_records_reason_value_and_timestamp(): void
    {
        DB::shouldReceive('transaction')->once()->andReturnUsing(function ($callback) {
            return $callback();
        });

        $stockRepository = Mockery::mock(CustomerStockRepositoryInterface::class);
        $saleRepository = Mockery::mock(CustomerSaleRepositoryInterface::class);
        $sale = new CustomerSale;
        $sale->forceFill([
            'customer_id' => 1,
            'sale_price' => 100,
            'status' => 'delivered',
        ]);

        $saleRepository->shouldReceive('update')
            ->once()
            ->with($sale, Mockery::on(function ($payload) {
                return $payload['return_reason'] === 'Damaged package'
                    && $payload['return_amount'] === 40.0
                    && $payload['returned_at'] instanceof \DateTimeInterface;
            }))
            ->andReturnUsing(function ($model, $payload) {
                $model->forceFill($payload);

                return $model;
            });

        $service = new CustomerSaleService($stockRepository, $saleRepository);
        $updated = $service->returnSale($sale, '  Damaged package  ', 40);

        $this->assertSame(40.0, (float) $updated->return_amount);
        $this->assertSame(60.0, $updated->total_amount);
        $this->assertSame('Damaged package', $updated->return_reason);
    }
}
