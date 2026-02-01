@extends('layouts.app')
@section('title', 'Edit Distribution Customer')
@section('content')
<section class="content-header">
    <h1>Edit Distribution Customer</h1>
</section>
<section class="content">
    {!! Form::open(['url' => action([\App\Http\Controllers\Distribution\CustomerController::class, 'update'], [$customer->id]), 'method' => 'put']) !!}
    <div class="box">
        <div class="box-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Name *</label>
                        {!! Form::text('name', $customer->name, ['class' => 'form-control', 'required']) !!}
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Email *</label>
                        {!! Form::email('email', $customer->email, ['class' => 'form-control', 'required']) !!}
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Phone *</label>
                        {!! Form::text('phone', $customer->phone, ['class' => 'form-control', 'required']) !!}
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Status</label>
                        {!! Form::select('status', ['active' => 'Active', 'inactive' => 'Inactive'], $customer->status, ['class' => 'form-control']) !!}
                    </div>
                </div>
            </div>
            <div class="form-group">
                <label>Address</label>
                {!! Form::textarea('address', $customer->address, ['class' => 'form-control', 'rows' => 2]) !!}
            </div>
            <div class="form-group">
                <label>New Password (leave blank to keep current)</label>
                {!! Form::password('password', ['class' => 'form-control']) !!}
            </div>
            <div class="form-group">
                <label>Confirm New Password</label>
                {!! Form::password('password_confirmation', ['class' => 'form-control']) !!}
            </div>
        </div>
        <div class="box-footer">
            <button type="submit" class="btn btn-primary">Update</button>
            <a href="{{ action([\App\Http\Controllers\Distribution\CustomerController::class, 'index']) }}" class="btn btn-default">Cancel</a>
        </div>
    </div>
    {!! Form::close() !!}
</section>
@endsection
