@extends('layouts.app')
@section('title', 'Customer GPS Tracking')
@section('content')
@php $api_key = env('GOOGLE_MAP_API_KEY', ''); @endphp
@if(!empty($api_key))
@section('css')
<style>#map { height: 500px; }</style>
@endsection
@endif
<section class="content-header">
    <h1>Customer GPS Tracking
        <small>Live tracking and route history</small>
    </h1>
</section>
<section class="content">
    <div class="box">
        <div class="box-body">
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Customer</label>
                        <select id="customer_id" class="form-control">
                            <option value="">Select Customer</option>
                            @foreach($customers as $c)
                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Date</label>
                        <input type="date" id="track_date" class="form-control" value="{{ date('Y-m-d') }}">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <button type="button" id="load_route" class="btn btn-primary btn-block">Load Route</button>
                    </div>
                </div>
            </div>
            @if(!empty($api_key))
            <div id="map" class="mt-3"></div>
            @else
            <div class="alert alert-warning">
                Set GOOGLE_MAP_API_KEY in .env for map display. You can use Leaflet as alternative (add leaflet.js).
            </div>
            @endif
        </div>
    </div>
</section>
@endsection
@if(!empty($api_key))
@section('javascript')
<script>
$(function() {
    var map, pathLine, markers = [];
    function initMap() {
        map = new google.maps.Map(document.getElementById('map'), {
            center: {lat: 0, lng: 0},
            zoom: 12
        });
    }
    initMap();
    $('#load_route').click(function() {
        var customerId = $('#customer_id').val();
        var date = $('#track_date').val();
        if (!customerId) { alert('Select customer'); return; }
        $.get('{{ route("distribution.tracking.locations") }}', {customer_id: customerId, date: date}, function(res) {
            if (res.success && res.data.length > 0) {
                markers.forEach(m => m.setMap(null));
                markers = [];
                var bounds = new google.maps.LatLngBounds();
                res.data.forEach(function(pt, i) {
                    var pos = {lat: pt.lat, lng: pt.lng};
                    bounds.extend(pos);
                    var m = new google.maps.Marker({position: pos, map: map, title: pt.recorded_at});
                    markers.push(m);
                });
                if (pathLine) pathLine.setMap(null);
                pathLine = new google.maps.Polyline({
                    path: res.data.map(p => ({lat: p.lat, lng: p.lng})),
                    geodesic: true,
                    strokeColor: '#4285F4',
                    strokeOpacity: 1,
                    strokeWeight: 4,
                    map: map
                });
                map.fitBounds(bounds);
            } else {
                alert('No location data for selected date');
            }
        });
    });
});
</script>
@endsection
@endif
