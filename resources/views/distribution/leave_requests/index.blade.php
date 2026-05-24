@extends('layouts.app')
@section('title', 'Distribution Leave Requests')

@section('content')
<section class="content-header">
    <h1>Distribution Leave Requests</h1>
</section>

<section class="content">
    @if(session('status'))
        <div class="alert alert-{{ session('status.success') ? 'success' : 'danger' }}">
            {{ session('status.msg') }}
        </div>
    @endif

    <div class="box box-primary">
        <div class="box-body">
            <form method="GET" class="row" style="margin-bottom: 10px;">
                <div class="col-md-4">
                    <label>Employee</label>
                    <select name="customer_id" class="form-control select2" style="width:100%;">
                        <option value="">All</option>
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}" {{ (string) request('customer_id') === (string) $customer->id ? 'selected' : '' }}>{{ $customer->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label>Status</label>
                    <select name="status" class="form-control">
                        <option value="">All</option>
                        <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>Approved</option>
                        <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Rejected</option>
                    </select>
                </div>
                <div class="col-md-3" style="margin-top: 25px;">
                    <button class="btn btn-primary" type="submit">Filter</button>
                    <a href="{{ route('distribution.leave-requests.index') }}" class="btn btn-default">Reset</a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Employee</th>
                            <th>From</th>
                            <th>To</th>
                            <th>Days</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th>Admin Note</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($leaveRequests as $leave)
                            <tr>
                                <td>{{ @format_datetime($leave->created_at) }}</td>
                                <td>{{ $leave->customer->name ?? '-' }}</td>
                                <td>{{ optional($leave->from_date)->toDateString() }}</td>
                                <td>{{ optional($leave->to_date)->toDateString() }}</td>
                                <td>{{ $leave->days_count }}</td>
                                <td>{{ $leave->reason ?: '-' }}</td>
                                <td>
                                    <span class="label label-{{ $leave->status === 'approved' ? 'success' : ($leave->status === 'rejected' ? 'danger' : 'warning') }}">
                                        {{ ucfirst($leave->status) }}
                                    </span>
                                </td>
                                <td>{{ $leave->admin_note ?: '-' }}</td>
                                <td>
                                    @if($leave->status === 'pending')
                                        <form action="{{ route('distribution.leave-requests.approve', $leave->id) }}" method="POST" style="display:inline;">
                                            @csrf
                                            <button class="btn btn-xs btn-success" type="submit">Approve</button>
                                        </form>
                                        <form action="{{ route('distribution.leave-requests.reject', $leave->id) }}" method="POST" style="display:inline;">
                                            @csrf
                                            <button class="btn btn-xs btn-danger" type="submit">Reject</button>
                                        </form>
                                    @else
                                        -
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="text-center">No leave requests found</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $leaveRequests->appends(request()->query())->links() }}
        </div>
    </div>
</section>
@endsection
