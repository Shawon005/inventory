@extends('layouts.app')
@section('title', 'Distribution Payroll')

@section('content')
<section class="content-header">
    <h1>Distribution Payroll</h1>
</section>

<section class="content">
    <div class="box box-primary">
        <div class="box-body">
            <form method="GET" class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Month</label>
                        <input type="month" name="month" class="form-control" value="{{ $selectedMonth }}">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Employee</label>
                        <select name="customer_id" class="form-control select2" style="width:100%;">
                            <option value="">All</option>
                            @foreach($customersForFilter as $customer)
                                <option value="{{ $customer->id }}" {{ (string) request('customer_id') === (string) $customer->id ? 'selected' : '' }}>
                                    {{ $customer->name }} ({{ strtoupper($customer->employee_type ?? 'sr') }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <p class="form-control-static text-muted">Use table length/search below</p>
                    </div>
                </div>
                <div class="col-md-2" style="margin-top:25px;">
                    <button class="btn btn-primary" type="submit">Filter</button>
                    <a class="btn btn-default" href="{{ route('distribution.payroll.index') }}">Reset</a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-bordered table-striped" id="distribution_payroll_table" style="width: 100%;">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Type</th>
                            <th>Present Days</th>
                            <th>Holiday Days</th>
                            <th>Approved Leave</th>
                            <th>Absent Days</th>
                            <th>Worked Hours</th>
                            <th>Late Hours</th>
                            <th>Overtime Hours</th>
                            <th>Base Salary</th>
                            <th>Absent Deduction</th>
                            <th>Late Deduction</th>
                            <th>Overtime Pay</th>
                            <th>TA/DA Total</th>
                            <th>Monthly Sales</th>
                            <th>Target</th>
                            <th>Excess Sales</th>
                            <th>Commission</th>
                            <th>Net Payable</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            <tr>
                                <td>{{ $row['customer']->name }}</td>
                                <td>{{ strtoupper($row['customer']->employee_type ?? 'sr') }}</td>
                                <td>{{ $row['uses_attendance'] ? $row['present_days'] : 'N/A' }}</td>
                                <td>{{ $row['uses_attendance'] ? $row['holiday_days'] : 'N/A' }}</td>
                                <td>{{ $row['uses_attendance'] ? $row['approved_leave_days'] : 'N/A' }}</td>
                                <td>{{ $row['uses_attendance'] ? $row['absent_days'] : 'N/A' }}</td>
                                <td>{{ $row['uses_attendance'] ? $row['worked_hours'] : 'N/A' }}</td>
                                <td>{{ $row['uses_attendance'] ? $row['late_hours'] : 'N/A' }}</td>
                                <td>{{ $row['uses_attendance'] ? $row['overtime_hours'] : 'N/A' }}</td>
                                <td>@format_currency($row['base_salary'])</td>
                                <td>@format_currency($row['absent_deduction'])</td>
                                <td>@format_currency($row['late_deduction'])</td>
                                <td>@format_currency($row['overtime_pay'])</td>
                                <td>@format_currency($row['total_tada_bill'])</td>
                                <td>@format_currency($row['monthly_sales_amount'])</td>
                                <td>@format_currency($row['monthly_target_amount'])</td>
                                <td>@format_currency($row['excess_sales_amount'])</td>
                                <td>@format_currency($row['commission_amount'])</td>
                                <td><strong>@format_currency($row['net_payable'])</strong></td>
                                <td>
                                    <a href="{{ route('distribution.payroll.show', ['customer' => $row['customer']->id, 'month' => $selectedMonth]) }}" class="btn btn-xs btn-info">View</a>
                                    <a href="{{ route('distribution.payroll.print', ['customer' => $row['customer']->id, 'month' => $selectedMonth]) }}" class="btn btn-xs btn-default" target="_blank">Print</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="20" class="text-center">No employees found</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
@endsection

@section('javascript')
<script type="text/javascript">
    $(document).ready(function () {
        $('#distribution_payroll_table').DataTable({
            aaSorting: [[0, 'asc']],
            pageLength: 25
        });
    });
</script>
@endsection
