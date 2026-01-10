@extends('layouts.app')
@section('title', __( 'user.users' ))

@section('content')

<!-- Content Header (Page header) -->
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang( 'user.users' )
        <small class="tw-text-sm md:tw-text-base tw-text-gray-700 tw-font-semibold">@lang( 'user.manage_users' )</small>
    </h1>
    <!-- <ol class="breadcrumb">
        <li><a href="#"><i class="fa fa-dashboard"></i> Level</a></li>
        <li class="active">Here</li>
    </ol> -->
</section>

<!-- Main content -->
<section class="content">
    @component('components.widget', ['class' => 'box-primary', 'title' => __( 'user.all_users' )])
        @can('user.create')
            @slot('tool')
                <div class="box-tools">
                    <a class="tw-dw-btn tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-font-bold tw-text-white tw-border-none tw-rounded-full" href="{{action([\App\Http\Controllers\ManageUserController::class, 'create'])}}">
                        <svg  xmlns="http://www.w3.org/2000/svg"  width="24"  height="24"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-plus"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 5l0 14" /><path d="M5 12l14 0" /></svg>                        @lang( 'messages.add' )
                    </a>
                 </div>
            @endslot
        @endcan
        @can('user.view')
            <div class="table-responsive">
                <table class="table table-bordered table-striped" id="users_table">
                    <thead>
                        <tr>
                            <th>@lang( 'business.username' )</th>
                            <th>@lang( 'user.name' )</th>
                            <th>@lang( 'user.role' )</th>
                            <th>@lang( 'business.email' )</th>
                            <th>@lang( 'lang_v1.location' )</th>
                            <th>@lang( 'messages.action' )</th>
                        </tr>
                    </thead>
                </table>
            </div>
        @endcan
    @endcomponent

    <div class="modal fade user_modal" tabindex="-1" role="dialog" 
    	aria-labelledby="gridSystemModalLabel">
    </div>

    <!-- Location Map Modal -->
    <div class="modal fade" id="location_map_modal" tabindex="-1" role="dialog" aria-labelledby="locationMapModalLabel">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                    <h4 class="modal-title" id="locationMapModalLabel">@lang('lang_v1.user_location') - <span id="modal_user_name"></span></h4>
                </div>
                <div class="modal-body">
                    <div id="user_location_map" style="height: 450px; width: 100%;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">@lang('messages.close')</button>
                </div>
            </div>
        </div>
    </div>

</section>
<!-- /.content -->
@stop
@section('javascript')
@php
    $api_key = env('GOOGLE_MAP_API_KEY', 'AIzaSyCHN4k8-SIkVpHGWUgqW3laDbCkPY6BIa0');
@endphp
<script src="https://maps.googleapis.com/maps/api/js?key={{ $api_key }}&libraries=places" async defer></script>
<script type="text/javascript">
    var userLocationMap;
    var userLocationMarker;

    //Roles table
    $(document).ready( function(){
        var users_table = $('#users_table').DataTable({
                    processing: true,
                    serverSide: true,
                    fixedHeader:false,
                    ajax: '/users',
                    columnDefs: [ {
                        "targets": [4, 5],
                        "orderable": false,
                        "searchable": false
                    } ],
                    "columns":[
                        {"data":"username"},
                        {"data":"full_name"},
                        {"data":"role"},
                        {"data":"email"},
                        {"data":"location"},
                        {"data":"action"}
                    ]
                });
        $(document).on('click', 'button.delete_user_button', function(){
            swal({
              title: LANG.sure,
              text: LANG.confirm_delete_user,
              icon: "warning",
              buttons: true,
              dangerMode: true,
            }).then((willDelete) => {
                if (willDelete) {
                    var href = $(this).data('href');
                    var data = $(this).serialize();
                    $.ajax({
                        method: "DELETE",
                        url: href,
                        dataType: "json",
                        data: data,
                        success: function(result){
                            if(result.success == true){
                                toastr.success(result.msg);
                                users_table.ajax.reload();
                            } else {
                                toastr.error(result.msg);
                            }
                        }
                    });
                }
             });
        });

        // Handle location map modal
        $('#location_map_modal').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget);
            var latitude = parseFloat(button.data('latitude'));
            var longitude = parseFloat(button.data('longitude'));
            var userName = button.data('user');

            $('#modal_user_name').text(userName);

            // Wait for Google Maps to load
            if (typeof google !== 'undefined' && typeof google.maps !== 'undefined') {
                initUserLocationMap(latitude, longitude);
            } else {
                // If Google Maps is not loaded yet, wait for it
                var checkGoogleMaps = setInterval(function() {
                    if (typeof google !== 'undefined' && typeof google.maps !== 'undefined') {
                        clearInterval(checkGoogleMaps);
                        initUserLocationMap(latitude, longitude);
                    }
                }, 100);
            }
        });

        function initUserLocationMap(latitude, longitude) {
            var mapOptions = {
                center: { lat: latitude, lng: longitude },
                zoom: 15,
                mapTypeId: google.maps.MapTypeId.ROADMAP
            };

            // Initialize map
            userLocationMap = new google.maps.Map(document.getElementById('user_location_map'), mapOptions);

            // Add marker
            userLocationMarker = new google.maps.Marker({
                position: { lat: latitude, lng: longitude },
                map: userLocationMap,
                title: `@lang("lang_v1.user_location")`,
                animation: google.maps.Animation.DROP
            });

            // Add info window
            var infoWindow = new google.maps.InfoWindow({
                content: '<div><strong>@lang("lang_v1.user_location")</strong><br>Lat: ' + latitude + '<br>Lng: ' + longitude + '</div>'
            });

            userLocationMarker.addListener('click', function() {
                infoWindow.open(userLocationMap, userLocationMarker);
            });

            // Open info window by default
            infoWindow.open(userLocationMap, userLocationMarker);
        }

        // Clean up map when modal is closed
        $('#location_map_modal').on('hidden.bs.modal', function () {
            if (userLocationMap) {
                userLocationMap = null;
            }
            if (userLocationMarker) {
                userLocationMarker = null;
            }
        });
    });
</script>
@endsection
