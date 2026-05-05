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
            <hr>
            <h4>Attendance & Payroll Settings</h4>
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Work Latitude</label>
                        {!! Form::text('work_latitude', $customer->work_latitude, ['class' => 'form-control']) !!}
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Work Longitude</label>
                        {!! Form::text('work_longitude', $customer->work_longitude, ['class' => 'form-control']) !!}
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Allowed Radius (m)</label>
                        {!! Form::number('allowed_radius_meters', $customer->allowed_radius_meters ?? 100, ['class' => 'form-control', 'min' => 10]) !!}
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Salary</label>
                        {!! Form::number('salary', $customer->base_salary ?? 0, ['class' => 'form-control', 'step' => '0.01', 'min' => 0]) !!}
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Attendance Location</label>
                        {!! Form::text('attendance_location', $customer->attendance_location, ['class' => 'form-control', 'placeholder' => 'Office / Warehouse / Branch name']) !!}
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Shift Start</label>
                        {!! Form::time('shift_start_time', $customer->shift_start_time, ['class' => 'form-control']) !!}
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Shift End</label>
                        {!! Form::time('shift_end_time', $customer->shift_end_time, ['class' => 'form-control']) !!}
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Late Deduction / Hour</label>
                        {!! Form::number('late_deduction_per_hour', $customer->late_deduction_per_hour ?? 0, ['class' => 'form-control', 'step' => '0.01', 'min' => 0]) !!}
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Overtime Pay / Hour</label>
                        {!! Form::number('overtime_pay_per_hour', $customer->overtime_pay_per_hour ?? 0, ['class' => 'form-control', 'step' => '0.01', 'min' => 0]) !!}
                    </div>
                </div>
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
