<!-- Left side column. contains the logo and sidebar -->
<aside class="side-bar tw-relative tw-hidden tw-h-full  tw-w-64 xl:tw-w-64 lg:tw-flex lg:tw-flex-col tw-shrink-0" style="background-color: #0e0d0d; ">

    <!-- sidebar: style can be found in sidebar.less -->

    {{-- <a href="{{route('home')}}" class="logo">
		<span class="logo-lg">{{ Session::get('business.name') }}</span>
	</a> --}}

    <a href="{{route('home')}}" 
        class="tw-mb-5 tw-flex tw-items-center tw-justify-center tw-w-full tw-border-r tw-h-15 tw-bg-@if(!empty(session('business.theme_color'))){{session('business.theme_color')}}@else{{'primary'}}@endif-700 tw-shrink-0 tw-border-primary-500/30">
        <p class="tw-text-lg tw-font-medium tw-text-white side-bar-heading tw-text-center">
          <img src="{{asset('')}}img/Nick_dashboard.png" alt="" width="80%" class="tw-mt-5">
          <!-- {{ Session::get('business.name') }} -->
        </p>
    </a>

    <!-- Sidebar Menu -->
    {!! Menu::render('admin-sidebar-menu', 'adminltecustom') !!}

    <!-- /.sidebar-menu -->
    <!-- /.sidebar -->
<style>
    /* .tw-text-gray-600{
        color:white 
    } */
    .tw-bg-gray-200{
        color:#fff;
    }
</style>
</aside>
