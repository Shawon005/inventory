@extends('layouts.app')
@section('title', 'Add Zone')

@section('content')
<section class="content-header">
    <h1>Add Zone <small>Create a zone for a business location</small></h1>
</section>

<section class="content">
    <div class="box box-primary">
        {!! Form::open(['route' => 'zones.store', 'method' => 'post']) !!}
        <div class="box-body">
            <div class="form-group">
                {!! Form::label('location_id', 'Business Location:*') !!}
                {!! Form::select('location_id', $locations, old('location_id', $selectedLocation), ['class' => 'form-control select2', 'placeholder' => 'Please select', 'required', 'style' => 'width:100%']) !!}
            </div>
            <div class="form-group">
                {!! Form::label('name', 'Zone Name:*') !!}
                {!! Form::text('name', old('name'), ['class' => 'form-control', 'required', 'maxlength' => 255, 'placeholder' => 'Enter zone name']) !!}
            </div>
            <div class="form-group">
                {!! Form::label('description', 'Description:') !!}
                {!! Form::textarea('description', old('description'), ['class' => 'form-control', 'rows' => 3, 'maxlength' => 1000]) !!}
            </div>
            <div class="checkbox">
                <label>{!! Form::checkbox('is_active', 1, old('is_active', true)) !!} Active</label>
            </div>
        </div>
        <div class="box-footer">
            <button type="submit" class="btn btn-primary">Save</button>
            <a href="{{ route('zones.index') }}" class="btn btn-default">Cancel</a>
        </div>
        {!! Form::close() !!}
    </div>
</section>
@endsection
