@extends('layouts.app')
@section('title', 'Edit Holiday')

@section('content')
<section class="content-header">
    <h1>Edit Holiday</h1>
</section>

<section class="content">
    <div class="box box-primary">
        <div class="box-body">
            <form action="{{ route('distribution.holidays.update', $holiday->id) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Title *</label>
                            <input type="text" name="title" class="form-control" value="{{ old('title', $holiday->title) }}" required>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Start Date *</label>
                            <input type="date" name="start_date" class="form-control" value="{{ old('start_date', optional($holiday->start_date)->toDateString()) }}" required>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>End Date *</label>
                            <input type="date" name="end_date" class="form-control" value="{{ old('end_date', optional($holiday->end_date)->toDateString()) }}" required>
                        </div>
                    </div>
                    <div class="col-md-12">
                        <div class="form-group">
                            <label>Note</label>
                            <textarea name="note" class="form-control" rows="3">{{ old('note', $holiday->note) }}</textarea>
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Update</button>
                <a href="{{ route('distribution.holidays.index') }}" class="btn btn-default">Cancel</a>
            </form>
        </div>
    </div>
</section>
@endsection
