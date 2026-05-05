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
            <div class="box">
                <div class="box-header"><h3>Sale Summary</h3></div>
                <div class="box-body">
                <form method="GET" action="">
                    <div style="margin-bottom:15px;text-align: end;">
                        <input type="date" name="from_date" value="{{ request('from_date') }}"style="height:32px;border-radius: 4px;width:150px;text-indent: 6px;">
                        <input type="date" name="to_date" value="{{ request('to_date') }}"style="height:32px;border-radius: 4px;width:150px;text-indent: 6px;">
                        <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                        <a href="{{ url()->current() }}" class="btn btn-danger btn-sm">Reset</a>
                    </div>
                </form>
                    <table class="table table-bordered table-striped  dataTable" id="products-table" dataID="{{$customer->id}}">
                        <thead><tr><th>Resaler name</th><th>items</th><th>Qty</th><th>Purchase Price</th><th>Date</th></tr></thead>
                        <tbody>
                            @forelse($sales as $sale)
                            <tr>
                                <td>{{ $sale->sold_to ?? '-' }}</td>
                                <td>
                                <table  width="100%">
                                    <tr>
                                        <th width="50%">Name</th>
                                        <th width="20%">Qty</th>
                                        <th>Price</th>
                                    </tr>
                                    @foreach(json_decode($sale->items) as $item)
                                    <tr>
                                        <td>{{$item->name}}</td><td>{{$item->quantity}}</td><td>{{$item->sale_price}}৳</td>                       
                                    </tr>
                                    @endforeach
                                </table>
                                    <!-- @foreach(json_decode($sale->items) as $item)
                                    <tr>{{$item->name}} -- ×{{$item->quantity}} --  {{$item->sale_price}}৳</tr>
                                    @endforeach -->
                                </td>
                                <td>{{ number_format($sale->quantity,2)}}</td>
                                <td>{{ number_format($sale->sale_price, 2) }}</td>
                                <td>{{ $sale->created_at }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="3">No Sell</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                    <div class="text-center">
                        {{ $sales->appends(request()->query())->links() }}
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="box">
                <div class="box-header"><h3>Stock Summary</h3></div>
                <div class="box-body">
                    <table class="table table-bordered table-striped align-middle">
                        <thead><tr><th>Product</th><th>Stock</th><th>Purchase Price</th></tr></thead>
                        <tbody >
                            @forelse($customer->stocks as $stock)
                            <tr>
                                <td>{{ $stock->product->name ?? '-' }}</td>
                                <td>{{ number_format($stock->quantity,0) }}</td>
                                <td>{{ number_format($stock->purchase_price, 2) }} ৳</td>
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
