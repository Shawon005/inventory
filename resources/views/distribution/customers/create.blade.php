
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
                <!-- Existing Contact selector -->
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Existing Contact* </label>
                        {!! Form::select('existing_contact', [], null, ['class' => 'form-control select2', 'id' => 'existing_contact', 'placeholder' => 'Search contact by name / phone']) !!}
                        {!! Form::hidden('contact_id', null, ['id' => 'contact_id']) !!}
                        <p class="help-block">Select an existing contact to autofill details. If left empty a new customer will be created.</p>
                    </div>
                </div>

                <!-- Name -->
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Name *</label>
                        {!! Form::text('name', null, ['class' => 'form-control', 'required']) !!}
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Email -->
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Email *</label>
                        {!! Form::email('email', null, ['class' => 'form-control', 'required']) !!}
                    </div>
                </div>

                <!-- Phone -->
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Phone *</label>
                        {!! Form::text('phone', null, ['class' => 'form-control', 'required']) !!}
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Password -->
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Password *</label>
                        {!! Form::password('password', ['class' => 'form-control', 'required']) !!}
                        <small>Password must be 6 character</small>
                    </div>
                </div>

                <!-- Confirm Password -->
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Confirm Password *</label>
                        {!! Form::password('password_confirmation', ['class' => 'form-control', 'required']) !!}
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label>Address</label>
                {!! Form::textarea('address', null, ['class' => 'form-control', 'rows' => 2]) !!}
            </div>
            <hr>
            <h4>Attendance & Payroll Settings</h4>
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Work Latitude</label>
                        {!! Form::text('work_latitude', null, ['class' => 'form-control', 'placeholder' => '23.8103']) !!}
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Work Longitude</label>
                        {!! Form::text('work_longitude', null, ['class' => 'form-control', 'placeholder' => '90.4125']) !!}
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Allowed Radius (m)</label>
                        {!! Form::number('allowed_radius_meters', 100, ['class' => 'form-control', 'min' => 10]) !!}
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Salary</label>
                        {!! Form::number('salary', 0, ['class' => 'form-control', 'step' => '0.01', 'min' => 0]) !!}
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Attendance Location</label>
                        {!! Form::text('attendance_location', null, ['class' => 'form-control', 'placeholder' => 'Office / Warehouse / Branch name']) !!}
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>TA/DA Bill (Daily)</label>
                        {!! Form::number('tada_bill_amount', 0, ['class' => 'form-control', 'step' => '0.01', 'min' => 0]) !!}
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Monthly Target Amount</label>
                        {!! Form::number('monthly_target_amount', 0, ['class' => 'form-control', 'step' => '0.01', 'min' => 0]) !!}
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Commission %</label>
                        {!! Form::number('commission_percent', 0, ['class' => 'form-control', 'step' => '0.01', 'min' => 0, 'max' => 100]) !!}
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Shift Start</label>
                        {!! Form::time('shift_start_time', null, ['class' => 'form-control']) !!}
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Shift End</label>
                        {!! Form::time('shift_end_time', null, ['class' => 'form-control']) !!}
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Late Deduction / Hour</label>
                        {!! Form::number('late_deduction_per_hour', 0, ['class' => 'form-control', 'step' => '0.01', 'min' => 0]) !!}
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Overtime Pay / Hour</label>
                        {!! Form::number('overtime_pay_per_hour', 0, ['class' => 'form-control', 'step' => '0.01', 'min' => 0]) !!}
                    </div>
                </div>
            </div>
            <hr>
            <h4>Weekly Work Locations (Friday can be blank/off-day)</h4>
            @php($days = ['saturday','sunday','monday','tuesday','wednesday','thursday','friday'])
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
                            <tr>
                                <td>{{ ucfirst($day) }}</td>
                                <td><input type="text" class="form-control" name="day_locations[{{ $day }}][location_name]" value="{{ old('day_locations.'.$day.'.location_name') }}"></td>
                                <td><input type="text" class="form-control" name="day_locations[{{ $day }}][latitude]" value="{{ old('day_locations.'.$day.'.latitude') }}" placeholder="23.8103"></td>
                                <td><input type="text" class="form-control" name="day_locations[{{ $day }}][longitude]" value="{{ old('day_locations.'.$day.'.longitude') }}" placeholder="90.4125"></td>
                                <td><input type="number" min="10" class="form-control" name="day_locations[{{ $day }}][allowed_radius_meters]" value="{{ old('day_locations.'.$day.'.allowed_radius_meters', 100) }}"></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
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
@section('javascript')
<script>
$(document).ready(function(){
    console.log('Initializing existing contact selector');
    
    // Initialize select2 for existing contact search
    $('#existing_contact').select2({
        ajax: {
            url: '/contacts/customers',
            type: 'GET',
            dataType: 'json',
            delay: 250,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            data: function (params) {
                console.log('Search term:', params.term);
                return { 
                    q: params.term || '',
                    _token: $('meta[name="csrf-token"]').attr('content')
                };
            },
            processResults: function (data, params) {
                console.log('Response data:', data);
                if (!Array.isArray(data)) {
                    data = [];
                }
                return {
                    results: data.map(function(d){
                        return { 
                            id: d.id, 
                            text: d.text || d.name || 'Unknown', 
                            mobile: d.mobile || '', 
                            email: d.email || '' 
                        };
                    })
                };
            },
            error: function(error) {
                console.error('Select2 AJAX error:', error);
            }
        },
        placeholder: 'Search contact by name / phone',
        minimumInputLength: 1,
        allowClear: true
    });

    // On select, autofill the form fields
    $('#existing_contact').on('select2:select', function (e) {
        var data = e.params.data;
        console.log('Selected contact:', data);
        $('#contact_id').val(data.id);
        $('input[name="name"]').val(data.text || '');
        $('input[name="email"]').val(data.email || '');
        $('input[name="phone"]').val(data.mobile || '');
    });

    // On clear, empty the form fields
    $('#existing_contact').on('select2:clear', function () {
        console.log('Cleared selection');
        $('#contact_id').val('');
        $('input[name="name"]').val('');
        $('input[name="email"]').val('');
        $('input[name="phone"]').val('');
    });
});
</script>

@endsection
