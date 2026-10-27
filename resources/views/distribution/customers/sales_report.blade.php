<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ ucfirst($reportType) }} Sales Report - {{ $customer->name }}</title>
    <link rel="stylesheet" href="{{ asset('bootstrap/css/bootstrap.min.css') }}">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #eef1f5; color: #111; font: 13px Arial, sans-serif; }
        .report-actions {
            width: {{ $reportType === 'monthly' ? '297mm' : '210mm' }};
            max-width: calc(100% - 20px);
            margin: 12px auto;
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 12px;
        }
        .report-actions form { display: flex; align-items: flex-end; gap: 8px; }
        .report-actions label { display: block; margin-bottom: 4px; }
        .report-page {
            width: {{ $reportType === 'monthly' ? '297mm' : '210mm' }};
            min-height: {{ $reportType === 'monthly' ? '210mm' : '297mm' }};
            margin: 0 auto 15px;
            padding: 12mm;
            background: #fff;
            border: 1px solid #bbb;
        }
        .company-name { margin: 0; text-align: center; font-size: 25px; font-weight: 700; }
        .report-title { margin: 2px 0 12px; text-align: center; font-size: 17px; font-weight: 700; }
        .report-info { width: 100%; margin-bottom: 8px; border-collapse: collapse; table-layout: fixed; }
        .report-info td { width: 50%; padding: 2px 6px; vertical-align: top; }
        .report-info .label-text { display: inline-block; min-width: 118px; font-weight: 700; }
        .sales-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .sales-table th, .sales-table td { border: 1px solid #111; padding: 6px 5px; vertical-align: middle; }
        .sales-table th { text-align: center; font-weight: 700; }
        .sales-table .number { text-align: right; white-space: nowrap; }
        .sales-table .serial { width: 45px; text-align: center; }
        .sales-table .bill { width: 90px; text-align: center; }
        .sales-table .amount { width: 100px; }
        .monthly-table { font-size: 11px; }
        .monthly-table .date { width: 72px; text-align: center; }
        .monthly-table .products { width: 150px; }
        .monthly-table .signature { width: 90px; }
        .empty-row td { height: 28px; }
        .signature-row { margin-top: 25px; display: flex; justify-content: space-between; font-weight: 700; }
        .muted { color: #555; }
        @media print {
            body { background: #fff; }
            .no-print { display: none !important; }
            .report-page { width: auto; min-height: auto; margin: 0; padding: 0; border: 0; }
            @page {
                size: A4 {{ $reportType === 'monthly' ? 'landscape' : 'portrait' }};
                margin: 9mm;
            }
        }
    </style>
</head>
<body>
    <div class="report-actions no-print">
        <form method="GET" action="{{ $reportType === 'daily' ? route('distribution.customers.sales-report.daily', $customer->id) : route('distribution.customers.sales-report.monthly', $customer->id) }}">
            <div>
                @if($reportType === 'daily')
                    <label for="report_date">Report Date</label>
                    <input id="report_date" type="date" name="date" class="form-control input-sm" value="{{ $period->toDateString() }}">
                @else
                    <label for="report_month">Report Month</label>
                    <input id="report_month" type="month" name="month" class="form-control input-sm" value="{{ $period->format('Y-m') }}">
                @endif
            </div>
            <button type="submit" class="btn btn-primary btn-sm">View</button>
        </form>
        <div>
            <a href="{{ route('customers.index') }}" class="btn btn-default btn-sm">Customers</a>
            <button type="button" onclick="window.print()" class="btn btn-success btn-sm"><i class="fa fa-print"></i> Print</button>
        </div>
    </div>

    <main class="report-page">
        <h1 class="company-name">{{ optional($customer->business)->name ?? config('app.name') }}</h1>
        <h2 class="report-title">{{ ucfirst($reportType) }} Sales Report</h2>

        <table class="report-info">
            <tr>
                <td><span class="label-text">Name:</span> {{ $customer->name }}</td>
                <td>
                    <span class="label-text">{{ $reportType === 'daily' ? 'Date:' : 'Month:' }}</span>
                    {{ $reportType === 'daily' ? $period->format('d.m.Y') : $period->format('F Y') }}
                </td>
            </tr>
            <tr>
                <td>
                    <span class="label-text">{{ $reportType === 'daily' ? 'Area Name:' : 'Designation:' }}</span>
                    {{ $reportType === 'daily' ? ($customer->attendance_location ?: ($customer->address ?: '-')) : 'Sales Representative' }}
                </td>
                <td><span class="label-text">Sales Target:</span> {{ number_format($targetAmount, 2) }}</td>
            </tr>
            <tr>
                <td>
                    <span class="label-text">Distributor Name:</span>
                    {{ optional($customer->contact)->name ?: (optional($customer->contact)->supplier_business_name ?: (optional($customer->business)->name ?? '-')) }}
                </td>
                <td><span class="label-text">Achieved:</span> {{ number_format($achievedAmount, 2) }}</td>
            </tr>
            <tr>
                <td><span class="label-text">Mobile:</span> {{ $customer->phone ?: '-' }}</td>
                <td>
                    @if($reportType === 'monthly')
                        <span class="label-text">Percentage:</span> {{ number_format($achievementPercentage, 2) }}%
                    @endif
                </td>
            </tr>
        </table>

        @if($reportType === 'daily')
            <table class="sales-table">
                <thead>
                    <tr>
                        <th class="serial">SL.<br>No.</th>
                        <th>Shop Name</th>
                        <th>Location</th>
                        <th class="bill">Bill No.</th>
                        <th class="amount">Order Amount</th>
                        <th class="amount">Delivery Amount</th>
                        <th class="amount">Return Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $row)
                        <tr>
                            <td class="serial">{{ str_pad((string) ($loop->index + 1), 2, '0', STR_PAD_LEFT) }}</td>
                            <td>{{ $row['shop_name'] }}</td>
                            <td>{{ $row['location'] ?: '-' }}</td>
                            <td class="bill">{{ $row['bill_no'] }}</td>
                            <td class="number">{{ $row['order_amount'] ? number_format($row['order_amount'], 2) : '' }}</td>
                            <td class="number">{{ $row['delivery_amount'] ? number_format($row['delivery_amount'], 2) : '' }}</td>
                            <td class="number">{{ $row['return_amount'] ? number_format($row['return_amount'], 2) : '' }}</td>
                        </tr>
                    @endforeach
                    @for($blankRow = $rows->count(); $blankRow < 25; $blankRow++)
                        <tr class="empty-row">
                            <td class="serial">{{ str_pad((string) ($blankRow + 1), 2, '0', STR_PAD_LEFT) }}</td>
                            <td></td><td></td><td></td><td></td><td></td><td></td>
                        </tr>
                    @endfor
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="4" class="number">Total</th>
                        <th class="number">{{ number_format($totals['order_amount'], 2) }}</th>
                        <th class="number">{{ number_format($totals['delivery_amount'], 2) }}</th>
                        <th class="number">{{ number_format($totals['return_amount'], 2) }}</th>
                    </tr>
                </tfoot>
            </table>
            <div class="signature-row">
                <span>Distributor Signature &amp; Date: ____________________</span>
                <span>Authorized Signature: ____________________</span>
            </div>
        @else
            <table class="sales-table monthly-table">
                <thead>
                    <tr>
                        <th class="serial">SL.<br>No.</th>
                        <th class="date">Date</th>
                        <th>Area/Road/Bazar</th>
                        <th class="amount">Order Amount</th>
                        <th class="amount">Delivery Amount</th>
                        <th class="amount">Return Amount</th>
                        <th class="products">Due Products</th>
                        <th class="signature">Signature</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td class="serial">{{ str_pad((string) ($loop->index + 1), 2, '0', STR_PAD_LEFT) }}</td>
                            <td class="date">{{ $row['date']->format('d.m.y') }}</td>
                            <td>{{ $row['location'] ?: '-' }}</td>
                            <td class="number">{{ $row['order_amount'] ? number_format($row['order_amount'], 2) : '' }}</td>
                            <td class="number">{{ $row['delivery_amount'] ? number_format($row['delivery_amount'], 2) : '' }}</td>
                            <td class="number">{{ $row['return_amount'] ? number_format($row['return_amount'], 2) : '' }}</td>
                            <td></td>
                            <td></td>
                        </tr>
                    @empty
                        <tr class="empty-row">
                            <td colspan="8" class="text-center muted">No sales found for this month.</td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="3" class="number">Total</th>
                        <th class="number">{{ number_format($totals['order_amount'], 2) }}</th>
                        <th class="number">{{ number_format($totals['delivery_amount'], 2) }}</th>
                        <th class="number">{{ number_format($totals['return_amount'], 2) }}</th>
                        <th colspan="2"></th>
                    </tr>
                </tfoot>
            </table>
            <div class="signature-row">
                <span>Distributor Signature &amp; Date: ____________________</span>
                <span>Authorized Signature: ____________________</span>
            </div>
        @endif
    </main>

    @if($autoPrint)
        <script>
            window.addEventListener('load', function () {
                window.print();
            });
        </script>
    @endif
</body>
</html>
