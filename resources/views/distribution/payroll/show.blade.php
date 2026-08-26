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
                    <p><strong>Employee Type:</strong> {{ strtoupper($payroll['customer']->employee_type ?? 'sr') }}</p>
                    <p><strong>Month:</strong> {{ $selectedMonth }}</p>
                    @if($payroll['uses_attendance'])
                    <p><strong>Attendance Location:</strong> {{ $payroll['customer']->attendance_location ?: '-' }}</p>
                    @else
                    <p class="text-muted">DSR payroll is salary-only; attendance deductions and overtime do not apply.</p>
                    @endif
                </div>
                <div class="col-md-4 text-right">
                    <a href="{{ route('distribution.payroll.print', ['customer' => $payroll['customer']->id, 'month' => $selectedMonth]) }}" target="_blank" class="btn btn-default">Print</a>
                    <a href="{{ route('distribution.payroll.index', ['month' => $selectedMonth]) }}" class="btn btn-primary">Back</a>
                </div>
            </div>

            <hr>
            @if($payroll['uses_attendance'])
            <div class="row">
                <div class="col-md-3"><strong>Present Days:</strong> {{ $payroll['present_days'] }}</div>
                <div class="col-md-3"><strong>Holiday Days:</strong> {{ $payroll['holiday_days'] }}</div>
                <div class="col-md-3"><strong>Approved Leave Days:</strong> {{ $payroll['approved_leave_days'] }}</div>
                <div class="col-md-3"><strong>Absent Days:</strong> {{ $payroll['absent_days'] }}</div>
            </div>
            <div class="row" style="margin-top:10px;">
                <div class="col-md-3"><strong>Worked Hours:</strong> {{ $payroll['worked_hours'] }}</div>
                <div class="col-md-3"><strong>Late Hours:</strong> {{ $payroll['late_hours'] }}</div>
                <div class="col-md-3"><strong>Overtime Hours:</strong> {{ $payroll['overtime_hours'] }}</div>
            </div>
            <div class="row" style="margin-top:10px;">
                <div class="col-md-3"><strong>Base Salary:</strong> @format_currency($payroll['base_salary'])</div>
                <div class="col-md-3"><strong>Absent Deduction:</strong> @format_currency($payroll['absent_deduction'])</div>
                <div class="col-md-3"><strong>Late Deduction:</strong> @format_currency($payroll['late_deduction'])</div>
                <div class="col-md-3"><strong>Overtime Pay:</strong> @format_currency($payroll['overtime_pay'])</div>
                <div class="col-md-3"><strong>TA/DA Total:</strong> @format_currency($payroll['total_tada_bill'])</div>
                <div class="col-md-3"><strong>Friday TA/DA:</strong> {{ $payroll['holiday_tada_paid_days'] }} paid / {{ $payroll['holiday_tada_missed_days'] }} missed</div>
                <div class="col-md-3"><strong>Monthly Sales:</strong> @format_currency($payroll['monthly_sales_amount'])</div>
                <div class="col-md-3"><strong>Target:</strong> @format_currency($payroll['monthly_target_amount'])</div>
                <div class="col-md-3"><strong>Excess Sales:</strong> @format_currency($payroll['excess_sales_amount'])</div>
                <div class="col-md-3"><strong>Commission ({{ $payroll['commission_percent'] }}%):</strong> @format_currency($payroll['commission_amount'])</div>
                <div class="col-md-3"><strong>Gross Earnings:</strong> @format_currency($payroll['gross_earnings'])</div>
                <div class="col-md-3"><strong>Net Payable:</strong> @format_currency($payroll['net_payable'])</div>
            </div>
            @else
            <div class="row">
                <div class="col-md-4"><strong>Base Salary:</strong> @format_currency($payroll['base_salary'])</div>
                <div class="col-md-4"><strong>Gross Earnings:</strong> @format_currency($payroll['gross_earnings'])</div>
                <div class="col-md-4"><strong>Net Payable:</strong> @format_currency($payroll['net_payable'])</div>
            </div>
            @endif

            @if($payroll['uses_attendance'])
            <hr>
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Check In</th>
                            <th>Check Out</th>
                            <th>Worked Hours</th>
                            <th>Late Hours</th>
                            <th>Overtime Hours</th>
                            <th>TA/DA Bill</th>
                            <th>Location</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($payroll['daily_rows'] as $day)
                            <tr>
                                <td>{{ $day['date'] }}</td>
                                <td>{{ $day['description'] }}</td>
                                <td>{{ $day['in_time'] ? @format_datetime($day['in_time']) : '-' }}</td>
                                <td>{{ $day['out_time'] ? @format_datetime($day['out_time']) : '-' }}</td>
                                <td>{{ $day['worked_hours'] }}</td>
                                <td>{{ $day['late_hours'] }}</td>
                                <td>{{ $day['overtime_hours'] }}</td>
                                <td>@format_currency($day['tada_bill_amount'])</td>
                                <td>{{ $day['location'] ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="text-center">No attendance data for this month</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @endif
        </div>
    </div>
</section>
@endsection
