<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll Print</title>
    <link rel="stylesheet" href="{{ asset('bootstrap/css/bootstrap.min.css') }}">
    <style>
        body { font-size: 12px; padding: 20px; }
        .summary-row { margin-bottom: 8px; }
        @media print {
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
    <div class="no-print text-right" style="margin-bottom: 15px;">
        <button onclick="window.print()" class="btn btn-primary btn-sm">Print</button>
    </div>

    <h3>Monthly Payroll Details</h3>
    <p><strong>Employee:</strong> {{ $payroll['customer']->name }}</p>
    <p><strong>Month:</strong> {{ $selectedMonth }}</p>
    <p><strong>Attendance Location:</strong> {{ $payroll['customer']->attendance_location ?: '-' }}</p>

    <div class="row summary-row">
        <div class="col-xs-3"><strong>Present Days:</strong> {{ $payroll['present_days'] }}</div>
        <div class="col-xs-3"><strong>Worked Hours:</strong> {{ $payroll['worked_hours'] }}</div>
        <div class="col-xs-3"><strong>Late Hours:</strong> {{ $payroll['late_hours'] }}</div>
        <div class="col-xs-3"><strong>Overtime Hours:</strong> {{ $payroll['overtime_hours'] }}</div>
    </div>
    <div class="row summary-row">
        <div class="col-xs-3"><strong>Base Salary:</strong> @format_currency($payroll['base_salary'])</div>
        <div class="col-xs-3"><strong>Late Deduction:</strong> @format_currency($payroll['late_deduction'])</div>
        <div class="col-xs-3"><strong>Overtime Pay:</strong> @format_currency($payroll['overtime_pay'])</div>
        <div class="col-xs-3"><strong>Net Payable:</strong> @format_currency($payroll['net_payable'])</div>
    </div>

    <table class="table table-bordered table-striped" style="margin-top: 20px;">
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
</body>
</html>

