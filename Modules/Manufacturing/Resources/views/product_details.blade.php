@php $mfgTabsId = 'mfg-'.str_replace('-', '', (string) \Illuminate\Support\Str::uuid()); @endphp
<link rel="stylesheet" href="{{ asset('modules/manufacturing/manufacturing.css?v='.$asset_v) }}">
<div class="mfg-product-details panel panel-default" data-product-id="{{ $manufacturing_product_id }}" data-url="{{ route('manufacturing.products.details.data', $manufacturing_product_id) }}" data-base="{{ url('manufacturing') }}">
    <div class="panel-heading"><strong>Manufacturing details</strong>
        <a class="btn btn-default btn-xs pull-right" href="{{ route('manufacturing.products.details', $manufacturing_product_id) }}">Open full details</a>
    </div>
    <ul class="nav nav-tabs" role="tablist">
        <li class="active" role="presentation"><a href="#{{ $mfgTabsId }}-production" data-toggle="tab" role="tab">Manufacturing Details</a></li>
        <li role="presentation"><a href="#{{ $mfgTabsId }}-usage" data-toggle="tab" role="tab">Component usage</a></li>
        <li role="presentation"><a href="#{{ $mfgTabsId }}-purchases" data-toggle="tab" role="tab">Purchase history</a></li>
    </ul>
    <div class="panel-body">
        <div class="mfg-detail-message text-muted" role="status">Loading manufacturing details…</div>
        <div class="tab-content">
            <div class="tab-pane active" role="tabpanel" id="{{ $mfgTabsId }}-production"><div class="mfg-detail-summary"></div><div class="mfg-detail-stock table-responsive"></div><div class="mfg-detail-orders table-responsive"></div></div>
            <div class="tab-pane" role="tabpanel" id="{{ $mfgTabsId }}-usage"><div class="mfg-detail-usage table-responsive"></div></div>
            <div class="tab-pane" role="tabpanel" id="{{ $mfgTabsId }}-purchases"><div class="mfg-detail-purchases table-responsive"></div></div>
        </div>
    </div>
</div>
