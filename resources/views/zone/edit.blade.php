@extends('layouts.app')
@section('title', 'Edit Zone')

@section('content')
<section class="content-header">
    <h1>Edit Zone <small>{{ $zone->name }}</small></h1>
</section>

<section class="content">
    <div class="box box-primary">
        {!! Form::open(['route' => ['zones.update', $zone->id], 'method' => 'put']) !!}
        <div class="box-body">
            <div class="form-group">
                {!! Form::label('location_id', 'Business Location:*') !!}
                {!! Form::select('location_id', $locations, old('location_id', $zone->location_id), ['class' => 'form-control select2', 'placeholder' => 'Please select', 'required', 'style' => 'width:100%']) !!}
            </div>
            <div class="form-group">
                {!! Form::label('name', 'Zone Name:*') !!}
                {!! Form::text('name', old('name', $zone->name), ['class' => 'form-control', 'required', 'maxlength' => 255]) !!}
            </div>
            <div class="form-group">
                {!! Form::label('description', 'Description:') !!}
                {!! Form::textarea('description', old('description', $zone->description), ['class' => 'form-control', 'rows' => 3, 'maxlength' => 1000]) !!}
            </div>
            <div class="checkbox">
                <label>{!! Form::checkbox('is_active', 1, old('is_active', $zone->is_active)) !!} Active</label>
            </div>
        </div>
        <div class="box-footer">
            <button type="submit" class="btn btn-primary">Update</button>
            <a href="{{ route('zones.index') }}" class="btn btn-default">Cancel</a>
        </div>
        {!! Form::close() !!}
    </div>
</section>
@endsection
