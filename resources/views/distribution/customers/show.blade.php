@extends('layouts.app')
@section('title', 'Customer Details')
@section('content')
<section class="content-header">
    <h1>{{ $customer->name }}
        <small>Distribution Customer</small>
    </h1>
</section>
<section class="content">
    <div class="row">
        <div class="col-md-6">
            <div class="box">
                <div class="box-header"><h3>Details</h3></div>
                <div class="box-body">
                    <p><strong>Email:</strong> {{ $customer->email }}</p>
                    <p><strong>Phone:</strong> {{ $customer->phone }}</p>
                    <p><strong>Address:</strong> {{ $customer->address ?? '-' }}</p>
                    <p><strong>Status:</strong> <span class="label label-{{ $customer->status == 'active' ? 'success' : 'danger' }}">{{ ucfirst($customer->status) }}</span></p>
                    <a href="{{ action([\App\Http\Controllers\Distribution\CustomerController::class, 'edit'], [$customer->id]) }}" class="btn btn-primary">Edit</a>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="box">
                <div class="box-header"><h3>Stock Summary</h3></div>
                <div class="box-body">
                    <table class="table table-bordered">
                        <thead><tr><th>Product</th><th>Qty</th><th>Purchase Price</th></tr></thead>
                        <tbody>
                            @forelse($customer->stocks as $stock)
                            <tr>
                                <td>{{ $stock->product->name ?? '-' }}</td>
                                <td>{{ $stock->quantity }}</td>
                                <td>{{ number_format($stock->purchase_price, 2) }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="3">No stock</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
