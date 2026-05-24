<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payslip - {{ $payroll['customer']->name }}</title>
    <link rel="stylesheet" href="{{ asset('bootstrap/css/bootstrap.min.css') }}">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-size: 12px;
            color: #1f2937;
            background: #f3f4f6;
            font-family: "Segoe UI", Arial, sans-serif;
        }
        .page {
            width: 210mm;
            min-height: 297mm;
            margin: 10px auto;
            background: #ffffff;
            padding: 18mm 14mm;
            border: 1px solid #e5e7eb;
        }
        .top-actions {
            width: 210mm;
            margin: 14px auto 0;
            text-align: right;
        }
        .slip-header {
            border: 1px solid #d1d5db;
            padding: 12px 14px;
            margin-bottom: 10px;
        }
        .slip-title {
            margin: 0;
            text-transform: uppercase;
            font-size: 22px;
            letter-spacing: 0.5px;
            color: #0f172a;
            font-weight: 700;
        }
        .company-name {
            margin: 0 0 4px 0;
            font-size: 18px;
            font-weight: 600;
            color: #111827;
        }
        .muted { color: #6b7280; }
        .grid-2 {
            display: table;
            width: 100%;
            table-layout: fixed;
            margin-top: 8px;
        }
        .grid-2 .col {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding-right: 10px;
        }
        .info-box {
            border: 1px solid #d1d5db;
            margin-bottom: 10px;
        }
        .info-box .head {
            background: #f8fafc;
            border-bottom: 1px solid #d1d5db;
            padding: 7px 10px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            color: #0f172a;
        }
        .info-box .body {
            padding: 8px 10px;
        }
        .kv-row {
            display: table;
            width: 100%;
            margin-bottom: 4px;
        }
        .kv-label, .kv-value {
            display: table-cell;
            vertical-align: top;
        }
        .kv-label {
            width: 45%;
            color: #4b5563;
            font-weight: 600;
        }
        .kv-value {
            width: 55%;
            color: #111827;
        }
        .salary-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .salary-table th, .salary-table td {
            border: 1px solid #d1d5db;
            padding: 7px 8px;
        }
        .salary-table thead th {
            background: #f8fafc;
            text-transform: uppercase;
            font-size: 11px;
            color: #0f172a;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .amount-highlight {
            border: 2px solid #0f172a;
            padding: 10px 12px;
            margin: 10px 0;
        }
        .amount-highlight .label {
            text-transform: uppercase;
            font-size: 11px;
            color: #334155;
            letter-spacing: .4px;
        }
        .amount-highlight .value {
            font-size: 22px;
            font-weight: 700;
            color: #0f172a;
            margin-top: 3px;
        }
        .attendance-summary {
            width: 100%;
            border-collapse: collapse;
            margin: 6px 0 10px;
        }
        .attendance-summary th, .attendance-summary td {
            border: 1px solid #d1d5db;
            padding: 7px 8px;
            text-align: center;
        }
        .attendance-summary th {
            background: #f8fafc;
            text-transform: uppercase;
            font-size: 11px;
        }
        .daily-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }
        .daily-table th, .daily-table td {
            border: 1px solid #d1d5db;
            padding: 6px 6px;
        }
        .daily-table thead th {
            background: #f8fafc;
            text-transform: uppercase;
            font-size: 10px;
        }
        .footer-note {
            margin-top: 12px;
            font-size: 10px;
            color: #6b7280;
        }
        @media print {
            body {
                background: #ffffff;
            }
            .no-print {
                display: none !important;
            }
            .page {
                width: auto;
                min-height: auto;
                margin: 0;
                border: 0;
                padding: 0;
            }
            @page {
                size: A4;
                margin: 12mm;
            }
        }
    </style>
</head>
<body>
    <div class="top-actions no-print">
        <button onclick="window.print()" class="btn btn-primary btn-sm">Print</button>
    </div>

    <div class="page">
        <div class="slip-header">
            <div class="grid-2" style="margin-top:0;">
                <div class="col">
                    <p class="company-name">{{ optional($payroll['customer']->business)->name ?? config('app.name') }}</p>
                    <p class="muted" style="margin:0;">Attendance Location: {{ $payroll['customer']->attendance_location ?: '-' }}</p>
                </div>
                <div class="col text-right">
                    <h1 class="slip-title">Salary Slip</h1>
                    <p class="muted" style="margin:4px 0 0;">Period: {{ \Carbon\Carbon::parse($selectedMonth . '-01')->format('F Y') }}</p>
                </div>
            </div>
        </div>

        <div class="grid-2">
            <div class="col" style="padding-right:6px;">
                <div class="info-box">
                    <div class="head">Employee Details</div>
                    <div class="body">
                        <div class="kv-row">
                            <div class="kv-label">Employee Name</div>
                            <div class="kv-value">{{ $payroll['customer']->name }}</div>
                        </div>
                        <div class="kv-row">
                            <div class="kv-label">Employee Code</div>
                            <div class="kv-value">{{ $payroll['customer']->employee_id ?? ('EMP-' . $payroll['customer']->id) }}</div>
                        </div>
                        <div class="kv-row">
                            <div class="kv-label">Shift Time</div>
                            <div class="kv-value">
                                {{ $payroll['customer']->shift_start_time ?: '-' }} to {{ $payroll['customer']->shift_end_time ?: '-' }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col" style="padding-right:0; padding-left:6px;">
                <div class="info-box">
                    <div class="head">Payroll Details</div>
                    <div class="body">
                        <div class="kv-row">
                            <div class="kv-label">Pay Month</div>
                            <div class="kv-value">{{ \Carbon\Carbon::parse($selectedMonth . '-01')->format('F Y') }}</div>
                        </div>
                        <div class="kv-row">
                            <div class="kv-label">Generated On</div>
                            <div class="kv-value">{{ now()->format('d M Y, h:i A') }}</div>
                        </div>
                        <div class="kv-row">
                            <div class="kv-label">Status</div>
                            <div class="kv-value">Processed</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <table class="salary-table">
            <thead>
                <tr>
                    <th>Earnings</th>
                    <th class="text-right">Amount</th>
                    <th>Deductions</th>
                    <th class="text-right">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Base Salary</td>
                    <td class="text-right">@format_currency($payroll['base_salary'])</td>
                    <td>Absent Deduction</td>
                    <td class="text-right">@format_currency($payroll['absent_deduction'])</td>
                </tr>
                <tr>
                    <td>Overtime Pay</td>
                    <td class="text-right">@format_currency($payroll['overtime_pay'])</td>
                    <td>Late Deduction</td>
                    <td class="text-right">@format_currency($payroll['late_deduction'])</td>
                </tr>
                <tr>
                    <td>TA/DA Total</td>
                    <td class="text-right">@format_currency($payroll['total_tada_bill'])</td>
                    <td>Other Deduction</td>
                    <td class="text-right">@format_currency(0)</td>
                </tr>
                <tr>
                    <td>Commission ({{ $payroll['commission_percent'] }}%)</td>
                    <td class="text-right">@format_currency($payroll['commission_amount'])</td>
                    <td>Deduction Notes</td>
                    <td class="text-right">-</td>
                </tr>
                <tr>
                    <th>Total Earnings</th>
                    <th class="text-right">@format_currency($payroll['gross_earnings'])</th>
                    <th>Total Deductions</th>
                    <th class="text-right">@format_currency($payroll['absent_deduction'] + $payroll['late_deduction'])</th>
                </tr>
            </tbody>
        </table>

        <table class="salary-table">
            <tbody>
                <tr>
                    <td>Monthly Sales</td>
                    <td class="text-right">@format_currency($payroll['monthly_sales_amount'])</td>
                    <td>Target</td>
                    <td class="text-right">@format_currency($payroll['monthly_target_amount'])</td>
                </tr>
                <tr>
                    <td>Excess Sales</td>
                    <td class="text-right">@format_currency($payroll['excess_sales_amount'])</td>
                    <td>Commission Rate</td>
                    <td class="text-right">{{ $payroll['commission_percent'] }}%</td>
                </tr>
            </tbody>
        </table>

        <div class="amount-highlight">
            <div class="label">Net Payable</div>
            <div class="value">@format_currency($payroll['net_payable'])</div>
        </div>

        <table class="attendance-summary">
            <thead>
                <tr>
                    <th>Present Days</th>
                    <th>Holiday Days</th>
                    <th>Approved Leave</th>
                    <th>Absent Days</th>
                    <th>Worked Hours</th>
                    <th>Late Hours</th>
                    <th>Overtime Hours</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{ $payroll['present_days'] }}</td>
                    <td>{{ $payroll['holiday_days'] }}</td>
                    <td>{{ $payroll['approved_leave_days'] }}</td>
                    <td>{{ $payroll['absent_days'] }}</td>
                    <td>{{ $payroll['worked_hours'] }}</td>
                    <td>{{ $payroll['late_hours'] }}</td>
                    <td>{{ $payroll['overtime_hours'] }}</td>
                </tr>
            </tbody>
        </table>

        <table class="daily-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Check In</th>
                    <th>Check Out</th>
                    <th>Worked (Hr)</th>
                    <th>Late (Hr)</th>
                    <th>OT (Hr)</th>
                    <th>TA/DA</th>
                </tr>
            </thead>
            <tbody>
                @forelse($payroll['daily_rows'] as $day)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($day['date'])->format('d M Y') }}</td>
                        <td>{{ $day['in_time'] ? @format_datetime($day['in_time']) : '-' }}</td>
                        <td>{{ $day['out_time'] ? @format_datetime($day['out_time']) : '-' }}</td>
                        <td class="text-right">{{ $day['worked_hours'] }}</td>
                        <td class="text-right">{{ $day['late_hours'] }}</td>
                        <td class="text-right">{{ $day['overtime_hours'] }}</td>
                        <td class="text-right">@format_currency($day['tada_bill_amount'])</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center">No attendance data for this month</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="footer-note">
            This is a system-generated salary slip.
        </div>
    </div>
</body>
</html>
