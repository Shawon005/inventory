@extends('layouts.app')
@section('title', $isDsr ? 'Edit DSR (Delivery Sales Representative)' : 'Edit SR (Sales Representative)')
@section('content')
<section class="content-header">
    <h1>{{ $isDsr ? 'Edit DSR (Delivery Sales Representative)' : 'Edit SR (Sales Representative)' }}</h1>
</section>
<section class="content">
    {!! Form::open(['url' => action([\App\Http\Controllers\Distribution\CustomerController::class, 'update'], [$customer->id]), 'method' => 'put', 'files' => true]) !!}
    <div class="box">
        <div class="box-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Profile Image</label>
                        @if($customer->profile_image_url)
                            <div style="margin-bottom:10px;">
                                <img src="{{ $customer->profile_image_url }}" alt="{{ $customer->name }}" style="width:80px;height:80px;object-fit:cover;border-radius:50%;border:3px solid #e5e7eb;">
                            </div>
                        @endif
                        {!! Form::file('profile_image', ['class' => 'form-control', 'accept' => 'image/jpeg,image/png,image/webp']) !!}
                        <small class="help-block">Upload a new image to replace the current one. Maximum 2 MB.</small>
                    </div>
                </div>
                @if($isDsr)
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Assigned SR *</label>
                            {!! Form::select('assigned_sr_customer_id', $customers->pluck('name', 'id')->prepend('Select SR', ''), $customer->assigned_sr_customer_id, ['class' => 'form-control select2', 'style' => 'width:100%', 'required']) !!}
                            <small class="help-block">Each SR can have only one DSR.</small>
                        </div>
                    </div>
                @else
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Dealer</label>
                            <p class="form-control-static">{{ optional($customer->contact)->name ?: (optional($customer->contact)->supplier_business_name ?: 'No dealer assigned') }}</p>
                            <small class="help-block">All SRs assigned to this dealer share the dealer's stock.</small>
                        </div>
                    </div>
                @endif
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Name *</label>
                        {!! Form::text('name', $customer->name, ['class' => 'form-control', 'required']) !!}
                    </div>
                </div>
                @unless($isDsr)
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Email *</label>
                            {!! Form::email('email', $customer->email, ['class' => 'form-control', 'required']) !!}
                        </div>
                    </div>
                @endunless
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Phone{{ $isDsr ? '' : ' *' }}</label>
                        {!! Form::text('phone', $customer->phone, $isDsr ? ['class' => 'form-control'] : ['class' => 'form-control', 'required' => true]) !!}
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
            @if($isDsr)
            <hr>
            <h4>Payroll Settings</h4>
            <p class="text-muted">DSR employees do not use attendance. Their payroll is based on the salary below without attendance deductions.</p>
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Monthly Salary</label>
                        {!! Form::number('salary', $customer->base_salary ?? 0, ['class' => 'form-control', 'step' => '0.01', 'min' => 0]) !!}
                    </div>
                </div>
            </div>
            @else
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
                <div class="col-md-3">
                    <div class="form-group">
                        <label>TA/DA Bill (Daily)</label>
                        {!! Form::number('tada_bill_amount', $customer->tada_bill_amount ?? 0, ['class' => 'form-control', 'step' => '0.01', 'min' => 0]) !!}
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Monthly Target Amount</label>
                        {!! Form::number('monthly_target_amount', $customer->monthly_target_amount ?? 0, ['class' => 'form-control', 'step' => '0.01', 'min' => 0]) !!}
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Commission %</label>
                        {!! Form::number('commission_percent', $customer->commission_percent ?? 0, ['class' => 'form-control', 'step' => '0.01', 'min' => 0, 'max' => 100]) !!}
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
            <hr>
            <h4>Weekly Work Locations (Friday can be blank/off-day)</h4>
            @php($days = ['saturday','sunday','monday','tuesday','wednesday','thursday','friday'])
            @php($dayLocations = $customer->workLocations->keyBy('weekday'))
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Day</th>
                            <th>Location Name</th>
                            <th>Latitude</th>
                            <th>Longitude</th>
                            <th>Radius (m)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($days as $day)
                            @php($loc = $dayLocations->get($day))
                            <tr>
                                <td>{{ ucfirst($day) }}</td>
                                <td><input type="text" class="form-control" name="day_locations[{{ $day }}][location_name]" value="{{ old('day_locations.'.$day.'.location_name', $loc->location_name ?? '') }}"></td>
                                <td><input type="text" class="form-control" name="day_locations[{{ $day }}][latitude]" value="{{ old('day_locations.'.$day.'.latitude', $loc->latitude ?? '') }}"></td>
                                <td><input type="text" class="form-control" name="day_locations[{{ $day }}][longitude]" value="{{ old('day_locations.'.$day.'.longitude', $loc->longitude ?? '') }}"></td>
                                <td><input type="number" min="10" class="form-control" name="day_locations[{{ $day }}][allowed_radius_meters]" value="{{ old('day_locations.'.$day.'.allowed_radius_meters', $loc->allowed_radius_meters ?? 100) }}"></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
            @unless($isDsr)
                <div class="form-group">
                    <label>New Password (leave blank to keep current)</label>
                    {!! Form::password('password', ['class' => 'form-control']) !!}
                </div>
                <div class="form-group">
                    <label>Confirm New Password</label>
                    {!! Form::password('password_confirmation', ['class' => 'form-control']) !!}
                </div>
            @endunless
        </div>
        <div class="box-footer">
            <button type="submit" class="btn btn-primary">Update</button>
            <a href="{{ action([\App\Http\Controllers\Distribution\CustomerController::class, 'index'], $isDsr ? ['employee_type' => 'dsr'] : []) }}" class="btn btn-default">Cancel</a>
        </div>
    </div>
    {!! Form::close() !!}
</section>
@endsection
