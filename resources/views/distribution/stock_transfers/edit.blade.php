
@extends('layouts.app')
@section('title', 'Edit Stock Transfer')
@section('content')
<section class="content-header">
    <h1>Edit Stock Transfer</h1>
</section>
<section class="content">
    {!! Form::open(['url' => route('distribution.stock-transfers.update', $transfer->id), 'method' => 'put']) !!}
    <div class="box">
        <div class="box-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Customer *</label>
                        <select name="customer_id" class="form-control" required>
                            <option value="">Select Customer</option>
                            @foreach($customers as $c)
                            <option value="{{ $c->id }}" {{ $transfer->customer_id == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Location *</label>
                        <select name="location_id" class="form-control" required>
                            <option value="">Select Location</option>
                            @foreach($locations as $loc)
                            <option value="{{ $loc->id }}" {{ $transfer->location_id == $loc->id ? 'selected' : '' }}>{{ $loc->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Product *</label>
                        <select name="product_id" class="form-control" required>
                            <option value="">Select Product</option>
                            @php $products = \App\Product::where('business_id', session('business.id'))->active()->get(); @endphp
                            @foreach($products as $p)
                            <option value="{{ $p->id }}" {{ $transfer->product_id == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Variation</label>
                        <select name="variation_id" class="form-control">
                            <option value="">Auto</option>
                            @if($transfer->variation)
                            <option value="{{ $transfer->variation->id }}" selected>{{ $transfer->variation->name }}</option>
                            @endif
                        </select>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Quantity *</label>
                        <input type="number" name="quantity" class="form-control" step="0.0001" min="0.0001" value="{{ $transfer->quantity }}" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Transfer Price *</label>
                        <input type="number" name="transfer_price" class="form-control" step="0.01" min="0" value="{{ $transfer->transfer_price }}" required>
                    </div>
                </div>
            </div>
        </div>
        <div class="box-footer">
            <button type="submit" class="btn btn-primary">Update</button>
            <a href="{{ route('distribution.stock-transfers.index') }}" class="btn btn-default">Cancel</a>
        </div>
    </div>
    {!! Form::close() !!}
</section>
@endsection