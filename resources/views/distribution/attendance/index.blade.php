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
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($attendances as $row)
                            <tr>
                                <td>{{ optional($row->attendance_date)->toDateString() }}</td>
                                <td>{{ $row->customer->name ?? '-' }}</td>
                                <td>{{ $row->in_time ? @format_datetime($row->in_time) : '-' }}</td>
                                <td>{{ $row->out_time ? @format_datetime($row->out_time) : '-' }}</td>
                                <td>{{ $row->is_late ? ($row->late_minutes.' min') : 'No' }}</td>
                                <td>
                                    @php($approvalStatus = $row->late_approval_status ?? 'approved')
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
                            <tr><td colspan="11" class="text-center">No attendance found</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $attendances->appends(request()->query())->links() }}
        </div>
    </div>
</section>
@endsection
