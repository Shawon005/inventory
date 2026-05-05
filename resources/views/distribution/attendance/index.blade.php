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
                            <th>Date & Time</th>
                            <th>Employee</th>
                            <th>Type</th>
                            <th>Late</th>
                            <th>Distance (m)</th>
                            <th>Location</th>
                            <th>Image</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($attendances as $row)
                            <tr>
                                <td>{{ @format_datetime($row->attendance_time) }}</td>
                                <td>{{ $row->customer->name ?? '-' }}</td>
                                <td><span class="label label-{{ $row->type === 'in' ? 'success' : 'info' }}">{{ strtoupper($row->type) }}</span></td>
                                <td>{{ $row->is_late ? ($row->late_minutes.' min') : 'No' }}</td>
                                <td>{{ $row->distance_meters ? number_format($row->distance_meters, 2) : '-' }}</td>
                                <td>{{ $row->latitude }}, {{ $row->longitude }}</td>
                                <td>
                                    @if($row->image_path)
                                        <a href="{{ asset('storage/'.$row->image_path) }}" target="_blank">View</a>
                                    @else
                                        -
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center">No attendance found</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $attendances->appends(request()->query())->links() }}
        </div>
    </div>
</section>
@endsection
