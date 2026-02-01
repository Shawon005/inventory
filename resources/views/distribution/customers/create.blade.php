@extends('layouts.app')
@section('title', 'Add Distribution Customer')
@section('content')
<section class="content-header">
    <h1>Add Distribution Customer</h1>
</section>
<section class="content">
    {!! Form::open(['url' => action([\App\Http\Controllers\Distribution\CustomerController::class, 'store']), 'method' => 'post']) !!}
    <div class="box">
        <div class="box-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Name *</label>
                        {!! Form::text('name', null, ['class' => 'form-control', 'required']) !!}
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Email *</label>
                        {!! Form::email('email', null, ['class' => 'form-control', 'required']) !!}
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Phone *</label>
                        {!! Form::text('phone', null, ['class' => 'form-control', 'required']) !!}
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Password *</label>
                        {!! Form::password('password', ['class' => 'form-control', 'required']) !!}
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Confirm Password *</label>
                        {!! Form::password('password_confirmation', ['class' => 'form-control', 'required']) !!}
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Status</label>
                        {!! Form::select('status', ['active' => 'Active', 'inactive' => 'Inactive'], 'active', ['class' => 'form-control']) !!}
                    </div>
                </div>
            </div>
            <div class="form-group">
                <label>Address</label>
                {!! Form::textarea('address', null, ['class' => 'form-control', 'rows' => 2]) !!}
            </div>
        </div>
        <div class="box-footer">
            <button type="submit" class="btn btn-primary">Save</button>
            <a href="{{ action([\App\Http\Controllers\Distribution\CustomerController::class, 'index']) }}" class="btn btn-default">Cancel</a>
        </div>
    </div>
    {!! Form::close() !!}
</section>
@endsection
