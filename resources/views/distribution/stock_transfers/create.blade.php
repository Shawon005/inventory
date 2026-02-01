@extends('layouts.app')
@section('title', 'Transfer Stock to Customer')
@section('content')
<section class="content-header">
    <h1>Transfer Stock to Customer</h1>
</section>
<section class="content">
    @if(session('status'))
        <div class="alert alert-{{ session('status.success') ? 'success' : 'danger' }}">
            {{ session('status.msg') }}
        </div>
    @endif
    {!! Form::open(['url' => route('distribution.stock-transfers.store'), 'method' => 'post']) !!}
    <div class="box">
        <div class="box-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Customer *</label>
                        <select name="customer_id" class="form-control" required>
                            <option value="">Select Customer</option>
                            @foreach($customers as $c)
                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Location (Main Inventory) *</label>
                        <select name="location_id" class="form-control" required>
                            <option value="">Select Location</option>
                            @foreach($locations as $loc)
                            <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Product *</label>
                        <select name="product_id" id="product_id" class="form-control" required>
                            <option value="">Select Product</option>
                            @php $products = \App\Product::where('business_id', session('business.id'))->active()->get(); @endphp
                            @foreach($products as $p)
                            <option value="{{ $p->id }}">{{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Variation (optional)</label>
                        <select name="variation_id" id="variation_id" class="form-control">
                            <option value="">Auto</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Quantity *</label>
                        <input type="number" name="quantity" class="form-control" step="0.0001" min="0.0001" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Transfer Price *</label>
                        <input type="number" name="transfer_price" class="form-control" step="0.01" min="0" required>
                    </div>
                </div>
            </div>
        </div>
        <div class="box-footer">
            <button type="submit" class="btn btn-primary">Transfer</button>
            <a href="{{ route('distribution.stock-transfers.index') }}" class="btn btn-default">Cancel</a>
        </div>
    </div>
    {!! Form::close() !!}
</section>
@endsection
