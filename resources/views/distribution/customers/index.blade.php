@extends('layouts.app')
@section('title', __('Distribution Customers'))
@section('content')
<section class="content-header">
    <h1>Distribution Customers
        <small>Manage distribution customers</small>
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
            <a href="{{ action([\App\Http\Controllers\Distribution\CustomerController::class, 'create']) }}" class="btn btn-primary">
                <i class="fa fa-plus"></i> Add Customer
            </a>
        </div>
        <div class="box-body">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($customers as $customer)
                    <tr>
                        <td>{{ $customer->id }}</td>
                        <td>{{ $customer->name }}</td>
                        <td>{{ $customer->email }}</td>
                        <td>{{ $customer->phone }}</td>
                        <td><span class="label label-{{ $customer->status == 'active' ? 'success' : 'danger' }}">{{ ucfirst($customer->status) }}</span></td>
                        <td>
                            <a href="{{ action([\App\Http\Controllers\Distribution\CustomerController::class, 'show'], [$customer->id]) }}" class="btn btn-xs btn-info">View</a>
                            <a href="{{ action([\App\Http\Controllers\Distribution\CustomerController::class, 'edit'], [$customer->id]) }}" class="btn btn-xs btn-primary">Edit</a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</section>
@endsection
