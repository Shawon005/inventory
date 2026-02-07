@extends('layouts.app')
@section('title', 'Customer Stock Transfers')
@section('content')
<section class="content-header">
    <h1>Customer Stock Transfers
        <small>Transfer stock from main inventory to customers</small>
    </h1>
</section>
<section class="content">
    @if(session('status'))
        <div class="alert alert-{{ session('status.success') ? 'success' : 'danger' }}">
            {{ session('status.msg') }}
        </div>
    @endif
    <div class="box">
        <div class="box-header">
            <a href="{{ route('distribution.stock-transfers.create') }}" class="btn btn-primary">
                <i class="fa fa-plus"></i> New Transfer
            </a>
        </div>
        <div class="box-body">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Product</th>
                        <th>Qty</th>
                        <th>Price</th>
                        <th>Total</th>
                        <th>By</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($transfers as $transfer)
                    <tr>
                        <td>{{ $transfer->created_at->format('Y-m-d H:i') }}</td>
                        <td>{{ $transfer->customer->name ?? '-' }}</td>
                        <td>{{ $transfer->product->name ?? '-' }}</td>
                        <td>{{ $transfer->quantity }}</td>
                        <td>{{ number_format($transfer->transfer_price, 2) }}</td>
                        <td>{{ number_format($transfer->total_amount, 2) }}</td>
                        <td>{{ $transfer->createdBy->user_full_name ?? '-' }}</td>
                        <td>
                            <div class="btn-group">
                                <a href="{{ route('distribution.stock-transfers.edit', $transfer->id) }}" class="btn btn-xs btn-primary">
                                    <i class="fa fa-edit"></i> Edit
                                </a>
                                <a href="{{ route('distribution.stock-transfers.destroy', $transfer->id) }}" class="btn btn-xs btn-danger" onclick="return confirm('Are you sure?')">
                                    <i class="fa fa-trash"></i> Delete
                                </a>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            {{ $transfers->links() }}
        </div>
    </div>
</section>
@endsection
