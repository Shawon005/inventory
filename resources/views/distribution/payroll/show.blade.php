@extends('layouts.app')
@section('title', 'Payroll Details')

@section('content')
<section class="content-header">
    <h1>Payroll Details</h1>
</section>

<section class="content">
    <div class="box box-primary">
        <div class="box-body">
            <div class="row">
                <div class="col-md-8">
                    <h4>{{ $payroll['customer']->name }}</h4>
                    <p><strong>Month:</strong> {{ $selectedMonth }}</p>
                    <p><strong>Attendance Location:</strong> {{ $payroll['customer']->attendance_location ?: '-' }}</p>
                </div>
                <div class="col-md-4 text-right">
                    <a href="{{ route('distribution.payroll.print', ['customer' => $payroll['customer']->id, 'month' => $selectedMonth]) }}" target="_blank" class="btn btn-default">Print</a>
                    <a href="{{ route('distribution.payroll.index', ['month' => $selectedMonth]) }}" class="btn btn-primary">Back</a>
                </div>
            </div>

            <hr>
            <div class="row">
                <div class="col-md-3"><strong>Present Days:</strong> {{ $payroll['present_days'] }}</div>
                <div class="col-md-3"><strong>Worked Hours:</strong> {{ $payroll['worked_hours'] }}</div>
                <div class="col-md-3"><strong>Late Hours:</strong> {{ $payroll['late_hours'] }}</div>
                <div class="col-md-3"><strong>Overtime Hours:</strong> {{ $payroll['overtime_hours'] }}</div>
            </div>
            <div class="row" style="margin-top:10px;">
                <div class="col-md-3"><strong>Base Salary:</strong> @format_currency($payroll['base_salary'])</div>
                <div class="col-md-3"><strong>Late Deduction:</strong> @format_currency($payroll['late_deduction'])</div>
                <div class="col-md-3"><strong>Overtime Pay:</strong> @format_currency($payroll['overtime_pay'])</div>
                <div class="col-md-3"><strong>Net Payable:</strong> @format_currency($payroll['net_payable'])</div>
            </div>

            <hr>
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Check In</th>
                            <th>Check Out</th>
                            <th>Worked Hours</th>
                            <th>Late Hours</th>
                            <th>Overtime Hours</th>
                            <th>Location</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($payroll['daily_rows'] as $day)
                            <tr>
                                <td>{{ $day['date'] }}</td>
                                <td>{{ $day['in_time'] ? @format_datetime($day['in_time']) : '-' }}</td>
                                <td>{{ $day['out_time'] ? @format_datetime($day['out_time']) : '-' }}</td>
                                <td>{{ $day['worked_hours'] }}</td>
                                <td>{{ $day['late_hours'] }}</td>
                                <td>{{ $day['overtime_hours'] }}</td>
                                <td>{{ $day['location'] ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center">No attendance data for this month</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
@endsection

