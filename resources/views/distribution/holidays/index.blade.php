@extends('layouts.app')
@section('title', 'Distribution Holidays')

@section('content')
<section class="content-header">
    <h1>Distribution Holidays</h1>
</section>

<section class="content">
    @if(session('status'))
        <div class="alert alert-{{ session('status.success') ? 'success' : 'danger' }}">
            {{ session('status.msg') }}
        </div>
    @endif

    <div class="box box-primary">
        <div class="box-header">
            <a href="{{ route('distribution.holidays.create') }}" class="btn btn-primary pull-right">Add Holiday</a>
        </div>
        <div class="box-body">
            <form method="GET" class="row" style="margin-bottom: 10px;">
                <div class="col-md-3">
                    <label>Year</label>
                    <input type="number" min="2000" max="2100" name="year" class="form-control" value="{{ request('year', now()->year) }}">
                </div>
                <div class="col-md-2" style="margin-top: 25px;">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="{{ route('distribution.holidays.index') }}" class="btn btn-default">Reset</a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Note</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($holidays as $holiday)
                            <tr>
                                <td>{{ $holiday->title }}</td>
                                <td>{{ optional($holiday->start_date)->toDateString() }}</td>
                                <td>{{ optional($holiday->end_date)->toDateString() }}</td>
                                <td>{{ $holiday->note ?: '-' }}</td>
                                <td>
                                    <a href="{{ route('distribution.holidays.edit', $holiday->id) }}" class="btn btn-xs btn-primary">Edit</a>
                                    <form action="{{ route('distribution.holidays.destroy', $holiday->id) }}" method="POST" style="display:inline;">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-xs btn-danger" onclick="return confirm('Delete this holiday?')">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center">No holidays found</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $holidays->appends(request()->query())->links() }}
        </div>
    </div>
</section>
@endsection
