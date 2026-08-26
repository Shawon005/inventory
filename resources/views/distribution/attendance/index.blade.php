@extends('layouts.app')
@section('title', 'Distribution Attendance')

@section('content')
<section class="content-header">
    <h1>Distribution Attendance</h1>
</section>

<section class="content">
    <div class="box box-primary">
        <div class="box-body">
            <form method="GET" class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Employee</label>
                        <select name="customer_id" class="form-control select2" style="width:100%;">
                            <option value="">All</option>
                            @foreach($customers as $customer)
                                <option value="{{ $customer->id }}" {{ request('customer_id') == $customer->id ? 'selected' : '' }}>{{ $customer->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Date</label>
                        <input type="date" name="date" class="form-control" value="{{ request('date') }}">
                    </div>
                </div>
                <div class="col-md-2" style="margin-top:25px;">
                    <button class="btn btn-primary" type="submit">Filter</button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Employee</th>
                            <th>In Time</th>
                            <th>Out Time</th>
                            <th>Late</th>
                            <th>Approval</th>
                            <th>Distance (m)</th>
                            <th>TA/DA Bill</th>
                            <th>Location</th>
                            <th>Image</th>
                            <th>Shop Visits</th>
                            <th>Sales</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($attendances as $row)
                            @php
                                $attendanceDate = optional($row->attendance_date)->toDateString();
                                $dailyVisits = $visitsByAttendance->get($row->customer_id.'|'.$attendanceDate, collect());
                            @endphp
                            <tr>
                                <td>{{ optional($row->attendance_date)->toDateString() }}</td>
                                <td>{{ $row->customer->name ?? '-' }}</td>
                                <td>{{ $row->in_time ? @format_datetime($row->in_time) : '-' }}</td>
                                <td>{{ $row->out_time ? @format_datetime($row->out_time) : '-' }}</td>
                                <td>{{ $row->is_late ? ($row->late_minutes.' min') : 'No' }}</td>
                                <td>
                                    @php
                                        $approvalStatus = $row->late_approval_status ?? 'approved';
                                    @endphp
                                    @if($approvalStatus === 'pending')
                                        <span class="label label-warning">Pending</span>
                                    @elseif($approvalStatus === 'rejected')
                                        <span class="label label-danger">Rejected</span>
                                    @else
                                        <span class="label label-success">Approved</span>
                                    @endif
                                </td>
                                <td>{{ $row->distance_meters ? number_format($row->distance_meters, 2) : '-' }}</td>
                                <td>{{ $row->in_time ? number_format((float) ($row->tada_bill_amount ?? 0), 2) : '-' }}</td>
                                <td>{{ $row->latitude }}, {{ $row->longitude }}</td>
                                <td>
                                    @if($row->image_path)
                                        <a href="{{ asset('storage/'.$row->image_path) }}" target="_blank">View</a>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>
                                    @if($dailyVisits->isNotEmpty())
                                        <button type="button" class="btn btn-xs btn-success" data-toggle="modal" data-target="#attendance_visits_{{ $row->id }}">
                                            <i class="fa fa-map-marker"></i> {{ $dailyVisits->count() }} {{ \Illuminate\Support\Str::plural('Visit', $dailyVisits->count()) }}
                                        </button>
                                    @else
                                        <span class="label label-default">0 Visits</span>
                                    @endif
                                </td>
                                <td>
                                    @if($row->out_time)
                                        <button type="button" class="btn btn-xs btn-info" data-toggle="modal" data-target="#attendance_sales_{{ $row->id }}">
                                            <i class="fa fa-eye"></i> View Sales
                                        </button>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>
                                    @if($row->in_time)
                                        <form method="POST" action="{{ route('distribution.attendance.tada-bill.update', $row->id) }}" class="form-inline">
                                            @csrf
                                            <input type="number" name="tada_bill_amount" value="{{ $row->tada_bill_amount ?? 0 }}" min="0" step="0.01" class="form-control input-sm" style="width: 110px;">
                                            <button type="submit" class="btn btn-xs btn-primary">Update</button>
                                        </form>
                                        @if(($row->late_approval_status ?? 'approved') === 'pending')
                                            <div style="margin-top:6px;">
                                                <form method="POST" action="{{ route('distribution.attendance.approve-late', $row->id) }}" style="display:inline-block;">
                                                    @csrf
                                                    <button type="submit" class="btn btn-xs btn-success">Approve</button>
                                                </form>
                                                <form method="POST" action="{{ route('distribution.attendance.reject-late', $row->id) }}" style="display:inline-block;">
                                                    @csrf
                                                    <button type="submit" class="btn btn-xs btn-danger">Reject</button>
                                                </form>
                                            </div>
                                        @endif
                                    @else
                                        -
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="13" class="text-center">No attendance found</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $attendances->appends(request()->query())->links() }}
        </div>
    </div>

    <div class="box box-info">
        <div class="box-header with-border">
            <h3 class="box-title">Friday Holiday TA/DA</h3>
        </div>
        <div class="box-body">
            <p class="text-muted">
                Friday TA/DA is paid when the employee has approved attendance on Thursday or Saturday.
                It is missed only when both days are absent. Until Saturday is complete, the automatic result remains pending.
                This table shows pending and missed records; paid records are hidden.
            </p>

            <form method="GET" class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Employee</label>
                        <select name="customer_id" class="form-control select2" style="width:100%;">
                            <option value="">All</option>
                            @foreach($customers as $customer)
                                <option value="{{ $customer->id }}" {{ request('customer_id') == $customer->id ? 'selected' : '' }}>{{ $customer->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Month</label>
                        <input type="month" name="holiday_tada_month" class="form-control" value="{{ $holidayTadaMonth }}">
                    </div>
                </div>
                <div class="col-md-3" style="margin-top:25px;">
                    <button class="btn btn-info" type="submit">Show Holiday TA/DA</button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Friday</th>
                            <th>Employee</th>
                            <th>Status</th>
                            <th>Admin Edit</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($holidayTadaRows->whereIn('effective_status', ['pending', 'missed']) as $holidayRow)
                            <tr>
                                <td>{{ $holidayRow['holiday_date'] }}</td>
                                <td>{{ $holidayRow['customer']->name }}</td>
                                <td>
                                    @if($holidayRow['effective_status'] === 'missed')
                                        <span class="label label-danger">Missed</span>
                                    @else
                                        <span class="label label-warning">Pending</span>
                                    @endif
                                </td>
                                <td>
                                    <form method="POST" action="{{ route('distribution.attendance.holiday-tada.update', ['customer' => $holidayRow['customer']->id, 'holidayDate' => $holidayRow['holiday_date']]) }}" class="form-inline">
                                        @csrf
                                        <select name="override_status" class="form-control input-sm" style="width:90px;">
                                            <option value="auto" {{ $holidayRow['override_status'] === 'auto' ? 'selected' : '' }}>Auto</option>
                                            <option value="paid" {{ $holidayRow['override_status'] === 'paid' ? 'selected' : '' }}>Pay</option>
                                            <option value="missed" {{ $holidayRow['override_status'] === 'missed' ? 'selected' : '' }}>Miss</option>
                                        </select>
                                        <input
                                            type="number"
                                            name="tada_bill_amount"
                                            value="{{ $holidayRow['override_status'] === 'paid' ? $holidayRow['override_amount'] : ($holidayRow['customer']->tada_bill_amount ?? 0) }}"
                                            min="0"
                                            step="0.01"
                                            class="form-control input-sm"
                                            style="width:100px;"
                                            title="Amount used when Pay is selected"
                                        >
                                        <button type="submit" class="btn btn-xs btn-primary">Update</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center">No pending or missed Friday holiday TA/DA found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @foreach($attendances as $row)
        @php
            $attendanceDate = optional($row->attendance_date)->toDateString();
            $dailyVisits = $visitsByAttendance->get($row->customer_id.'|'.$attendanceDate, collect());
        @endphp
        @if($dailyVisits->isNotEmpty())
            <div class="modal fade" id="attendance_visits_{{ $row->id }}" tabindex="-1" role="dialog" aria-labelledby="attendance_visits_label_{{ $row->id }}">
                <div class="modal-dialog modal-lg" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                            <h4 class="modal-title" id="attendance_visits_label_{{ $row->id }}">
                                Shop Visits - {{ $row->customer->name ?? '-' }} ({{ $attendanceDate }})
                            </h4>
                        </div>
                        <div class="modal-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th>Visit Time</th>
                                            <th>Matched Shop</th>
                                            <th>Distance</th>
                                            <th>SR Location</th>
                                            <th>Shop Location</th>
                                            <th>Map</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($dailyVisits as $visit)
                                            <tr>
                                                <td>@format_datetime($visit->visited_at)</td>
                                                <td>
                                                    <strong>{{ $visit->shop_name }}</strong>
                                                    @if($visit->shopOwner && $visit->shopOwner->address)
                                                        <br><small class="text-muted">{{ $visit->shopOwner->address }}</small>
                                                    @endif
                                                </td>
                                                <td><span class="label label-success">{{ number_format($visit->distance_meters, 2) }} m</span></td>
                                                <td>{{ number_format($visit->latitude, 6) }}, {{ number_format($visit->longitude, 6) }}</td>
                                                <td>{{ number_format($visit->shop_latitude, 6) }}, {{ number_format($visit->shop_longitude, 6) }}</td>
                                                <td>
                                                    <a href="https://www.google.com/maps/dir/?api=1&origin={{ $visit->latitude }},{{ $visit->longitude }}&destination={{ $visit->shop_latitude }},{{ $visit->shop_longitude }}" target="_blank" rel="noopener" class="btn btn-xs btn-info">
                                                        <i class="fa fa-map"></i> View
                                                    </a>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        @endif
        @if($row->out_time)
            @php
                $dailySales = $salesByAttendance->get($row->customer_id.'|'.$attendanceDate, collect());
            @endphp
            <div class="modal fade" id="attendance_sales_{{ $row->id }}" tabindex="-1" role="dialog" aria-labelledby="attendance_sales_label_{{ $row->id }}">
                <div class="modal-dialog modal-lg" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                            <h4 class="modal-title" id="attendance_sales_label_{{ $row->id }}">
                                Sales - {{ $row->customer->name ?? '-' }} ({{ $attendanceDate }})
                            </h4>
                        </div>
                        <div class="modal-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th>Shop Owner Name</th>
                                            <th>Sale Items</th>
                                            <th>Total Quantity</th>
                                            <th>Total Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($dailySales as $sale)
                                            @php
                                                $items = json_decode($sale->items ?? '[]', true);
                                                $items = is_array($items) && count($items) ? $items : [[
                                                    'name' => optional($sale->product)->name,
                                                    'quantity' => $sale->quantity,
                                                ]];
                                            @endphp
                                            <tr>
                                                <td>{{ $sale->sold_to ?: '-' }}</td>
                                                <td>
                                                    @foreach($items as $item)
                                                        <div>
                                                            {{ $item['name'] ?? '-' }}
                                                            &times; {{ number_format((float) ($item['quantity'] ?? 0), 2) }}
                                                        </div>
                                                    @endforeach
                                                </td>
                                                <td>{{ number_format((float) $sale->quantity, 2) }}</td>
                                                <td>{{ number_format((float) $sale->sale_price, 2) }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="4" class="text-center">No sales found for this attendance date.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                    @if($dailySales->isNotEmpty())
                                        <tfoot>
                                            <tr>
                                                <th colspan="2" class="text-right">Total</th>
                                                <th>{{ number_format((float) $dailySales->sum('quantity'), 2) }}</th>
                                                <th>{{ number_format((float) $dailySales->sum('sale_price'), 2) }}</th>
                                            </tr>
                                        </tfoot>
                                    @endif
                                </table>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    @endforeach
</section>
@endsection
