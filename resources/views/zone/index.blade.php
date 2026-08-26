@extends('layouts.app')
@section('title', 'Zones')

@section('content')
<section class="content-header">
    <h1>Zones <small>Manage zones under each business location</small></h1>
</section>

<section class="content">
    @if(session('status'))
        <div class="alert alert-{{ session('status.success') ? 'success' : 'danger' }}">
            {{ session('status.msg') }}
        </div>
    @endif

    <div class="box box-primary">
        <div class="box-header with-border">
            <div class="row">
                <div class="col-md-4">
                    {!! Form::open(['route' => 'zones.index', 'method' => 'get']) !!}
                    <div class="input-group">
                        {!! Form::select('location_id', $locations, request('location_id'), ['class' => 'form-control select2', 'placeholder' => 'All business locations', 'style' => 'width:100%']) !!}
                        <span class="input-group-btn">
                            <button type="submit" class="btn btn-default">Filter</button>
                        </span>
                    </div>
                    {!! Form::close() !!}
                </div>
                <div class="col-md-8">
                    <a href="{{ route('zones.create', ['location_id' => request('location_id')]) }}"
                        class="tw-dw-btn tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-font-bold tw-text-white tw-border-none tw-rounded-full pull-right">
                        <i class="fa fa-plus"></i> Add Zone
                    </a>
                </div>
            </div>
        </div>
        <div class="box-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Zone</th>
                            <th>Business Location</th>
                            <th>Description</th>
                            <th>Customers</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($zones as $zone)
                            <tr>
                                <td>{{ $zone->name }}</td>
                                <td>{{ optional($zone->businessLocation)->name ?? '-' }}</td>
                                <td>{{ $zone->description ?: '-' }}</td>
                                <td>{{ $zone->contacts_count }}</td>
                                <td>
                                    <span class="label label-{{ $zone->is_active ? 'success' : 'default' }}">
                                        {{ $zone->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td>
                                    <a href="{{ route('zones.edit', $zone->id) }}" class="btn btn-xs btn-primary">
                                        <i class="fa fa-edit"></i> Edit
                                    </a>
                                    {!! Form::open(['route' => ['zones.destroy', $zone->id], 'method' => 'delete', 'style' => 'display:inline']) !!}
                                        <button type="submit" class="btn btn-xs btn-danger" onclick="return confirm('Delete this zone?')">
                                            <i class="fa fa-trash"></i> Delete
                                        </button>
                                    {!! Form::close() !!}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center">No zones found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
@endsection
