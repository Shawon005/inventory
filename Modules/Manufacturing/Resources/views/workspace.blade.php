@extends('layouts.app')
@section('title', $title)
@section('css')
<link rel="stylesheet" href="{{ asset('modules/manufacturing/manufacturing.css?v='.$asset_v) }}">
@endsection
@section('content')
<div id="manufacturing-workspace" data-config="{{ json_encode($config) }}">
    <section class="content-header mfg-heading">
        <div><p class="mfg-eyebrow">INVENTORY / MANUFACTURING</p><h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">{{ $title }}</h1><p class="text-muted">Plan materials, run production, and track every receipt.</p></div>
        <div class="mfg-heading-actions">
            @if(in_array($screen, ['materials','components','products']))
                @can('product.create')<a href="{{ action([\App\Http\Controllers\ProductController::class, 'create'], ['manufacturing_type' => $screen === 'materials' ? 'raw_material' : 'component']) }}" class="btn btn-primary"><i class="fa fa-plus"></i> Add product</a>@endcan
                @can('purchase.create')<a class="btn btn-default" href="{{ url('purchases/create') }}">Receive purchase</a>@endcan
                @can('manufacturing.stock.adjust')<button type="button" class="btn btn-default" data-mfg-action="adjust">Stock adjustment</button>@endcan
            @elseif($screen === 'boms')
                @can('manufacturing.bom.manage')<button type="button" class="btn btn-primary" data-mfg-action="new-bom"><i class="fa fa-plus"></i> Create BOM</button>@endcan
            @elseif(in_array($screen, ['dashboard','orders','history']))
                @can('manufacturing.orders.create')<button type="button" class="btn btn-primary" data-mfg-action="new-order"><i class="fa fa-plus"></i> Plan production</button>@endcan
            @elseif($screen === 'reports')
                @can('view_export_buttons')<a class="btn btn-default" id="mfg-export" href="{{ route('manufacturing.reports.export') }}"><i class="fa fa-download"></i> Export CSV</a>@endcan
            @endif
        </div>
    </section>
    <section class="content mfg-content">
        <nav class="mfg-nav" aria-label="Manufacturing">
            @foreach(['dashboard'=>['manufacturing.dashboard','Overview'], 'materials'=>['manufacturing.materials','Raw materials'], 'components'=>['manufacturing.components','Components'], 'boms'=>['manufacturing.boms.index','BOMs'], 'orders'=>['manufacturing.orders.index','Production orders'], 'history'=>['manufacturing.history','History'], 'movements'=>['manufacturing.movements','Stock movements'], 'reports'=>['manufacturing.reports','Reports']] as $key=>$tab)
                @if($key !== 'reports' || auth()->user()->can('manufacturing.reports.view'))<a href="{{ route($tab[0]) }}" class="{{ $screen === $key || ($screen === 'order' && $key === 'orders') ? 'active' : '' }}" @if($screen === $key) aria-current="page" @endif>{{ $tab[1] }}</a>@endif
            @endforeach
        </nav>
        <div id="mfg-feedback" class="alert" role="alert" hidden></div>
        @if($screen === 'product')
            @include('manufacturing::product_details', ['manufacturing_product_id' => $id])
        @else
        @if($screen !== 'order')
        @component('components.widget', ['class' => 'box-primary'])
            <form id="mfg-filters" class="mfg-filters" autocomplete="off">
                <div class="form-group"><label for="mfg-location">Warehouse</label><select id="mfg-location" name="location_id" class="form-control select2"><option value="">All permitted warehouses</option>@foreach($locations as $location)<option value="{{ $location->id }}">{{ $location->name }}{{ $location->is_active ? '' : ' (inactive)' }}</option>@endforeach</select></div>
                @if(in_array($screen, ['materials','components','products','boms','orders']))<div class="form-group mfg-search"><label for="mfg-search">Search</label><input id="mfg-search" name="search" class="form-control" type="search" placeholder="{{ $screen === 'orders' ? 'Order number' : 'Name or SKU' }}"></div>@endif
                @if(in_array($screen, ['materials','components','products']))<div class="form-group"><label for="mfg-product-type">Product type</label><select id="mfg-product-type" name="type" class="form-control"><option value="">All manufacturing products</option><option value="raw_material" @selected($screen==='materials')>Raw material</option><option value="component" @selected($screen==='components')>Manufactured component</option><option value="finished_good">Manufactured finished product</option></select></div>@endif
                @if($screen==='orders')<div class="form-group"><label for="mfg-status">Status</label><select id="mfg-status" name="status" class="form-control"><option value="">All statuses</option>@foreach(['draft','planned','in_progress','partially_completed','completed','cancelled'] as $status)<option value="{{ $status }}">{{ ucwords(str_replace('_',' ',$status)) }}</option>@endforeach</select></div>@endif
                @if(in_array($screen,['history','movements','reports']))<div class="form-group"><label for="mfg-from">From</label><input type="date" id="mfg-from" name="from" class="form-control"></div><div class="form-group"><label for="mfg-to">To</label><input type="date" id="mfg-to" name="to" class="form-control"></div>@endif
                <button class="btn btn-default" type="submit"><i class="fa fa-filter"></i> Apply</button>
            </form>
        @endcomponent
        @endif
        @if(in_array($screen, ['dashboard','reports','order']))<div id="mfg-metrics" class="mfg-metrics" aria-live="polite"></div>@endif
        @if($screen==='order')<div id="mfg-order-header"></div><div id="mfg-requirements"></div>@endif
        @component('components.widget', ['class' => 'box-primary'])
            <div class="mfg-section-title"><h2>{{ $screen === 'dashboard' ? 'Recent production batches' : ($screen === 'order' ? 'Production batches' : ($screen === 'reports' ? 'Production cost report' : $title)) }}</h2><span id="mfg-count" class="text-muted"></span></div>
            <div id="mfg-loading" role="status" class="mfg-loading"><i class="fa fa-spinner fa-spin" aria-hidden="true"></i> Loading records…</div>
            <div id="mfg-table" class="table-responsive"></div><div id="mfg-pagination" class="mfg-pagination"></div>
        @endcomponent
        @if($screen==='order')<div id="mfg-order-audit"></div>@endif
        @endif
    </section>
    <div class="modal fade" id="mfg-dialog" tabindex="-1" role="dialog" aria-labelledby="mfg-dialog-title" aria-hidden="true" data-backdrop="static">
        <div class="modal-dialog modal-lg" role="document"><div class="modal-content">
            <div class="modal-header"><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button><h3 id="mfg-dialog-title" class="modal-title"></h3></div>
            <form id="mfg-dialog-form" autocomplete="off"><div class="modal-body"><div class="mfg-form-errors alert alert-danger" role="alert" hidden></div><div id="mfg-dialog-body"></div></div><div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary" id="mfg-dialog-submit">Save</button></div></form>
        </div></div>
    </div>
    <template id="mfg-location-options"><option value="">Select warehouse</option>
        @foreach($locations as $location)
            @if($location->is_active)<option value="{{ $location->id }}">{{ $location->name }}</option>@endif
        @endforeach
    </template>
</div>
@endsection
@section('javascript')
<script src="{{ asset('modules/manufacturing/decimal.js?v='.$asset_v) }}"></script>
<script src="{{ asset('modules/manufacturing/product.js?v='.$asset_v) }}"></script>
<script src="{{ asset('modules/manufacturing/manufacturing.js?v='.$asset_v) }}"></script>
@endsection
