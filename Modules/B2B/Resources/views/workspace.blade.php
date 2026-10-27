@extends('layouts.app')
@section('title', $title)
@section('css')
<link rel="stylesheet" href="{{ asset('modules/manufacturing/manufacturing.css?v='.$asset_v) }}">
<link rel="stylesheet" href="{{ asset('modules/b2b/workspace.css?v='.$asset_v) }}">
@endsection
@section('content')
<div id="b2b-workspace" data-config="{{ json_encode($config) }}">
    <section class="content-header mfg-heading">
        <div><p class="mfg-eyebrow">WHOLESALE / ALLOCATED STOCK</p><h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">{{ $title }}</h1><p class="text-muted">Move selected quantities into B2B stock, then sell at a price you enter.</p></div>
        <div class="mfg-heading-actions">
            @can('b2b.stock.transfer')<button class="btn btn-primary" type="button" data-b2b-action="transfer">Transfer stock</button>@endcan
            @if($screen === 'customers')@can('b2b.customers.manage')<button class="btn btn-default" type="button" data-b2b-action="customer">Add business customer</button>@endcan @endif
            @if($screen === 'reports')@can('view_export_buttons')<a class="btn btn-default" id="b2b-export" href="{{ route('b2b.reports.export') }}">Export CSV</a>@endcan @endif
        </div>
    </section>
    <section class="content mfg-content">
        <nav class="mfg-nav" aria-label="B2B Management">
            @foreach(['dashboard'=>['Overview','dashboard.view'],'products'=>['Products','products.manage'],'inventory'=>['B2B inventory','stock.transfer'],'transfers'=>['Transfers','stock.transfer'],'customers'=>['Customers','customers.manage'],'pos'=>['B2B POS','pos.access'],'sales'=>['Sales & invoices','sales.create'],'dues'=>['Payments & dues','payments.record'],'movements'=>['Movements','stock.transfer'],'reports'=>['Reports','reports.view']] as $key=>$tab)
                @can('b2b.'.$tab[1])<a href="{{ route('b2b.'.$key) }}" class="{{ $screen === $key ? 'active' : '' }}">{{ $tab[0] }}</a>@endcan
            @endforeach
        </nav>
        <div id="b2b-feedback" class="alert" role="alert" hidden></div>
        <div class="mfg-card b2b-workflow"><strong>Main stock</strong><span aria-hidden="true">&rarr;</span><span>Select products &amp; quantities</span><span aria-hidden="true">&rarr;</span><strong>B2B stock</strong><span aria-hidden="true">&rarr;</span><span>Enter a price &amp; sell</span></div>
        <div class="mfg-card">
            <form id="b2b-filters" class="mfg-filters">
                <div class="form-group"><label for="b2b-location">Warehouse</label><select name="location_id" id="b2b-location" class="form-control"><option value="">{{ $screen === 'pos' ? 'Choose warehouse' : 'All permitted warehouses' }}</option>@foreach($locations as $l)<option value="{{ $l->id }}">{{ $l->name }}</option>@endforeach</select></div>
                <div class="form-group mfg-search"><label for="b2b-search">Search</label><input type="search" class="form-control" name="search" id="b2b-search" placeholder="{{ in_array($screen,['sales','dues','reports','dashboard']) ? 'Invoice number' : 'Product, SKU or customer' }}"></div>
                @if(in_array($screen,['dashboard','sales','dues','reports','movements']))
                <div class="form-group"><label for="b2b-from">From</label><input type="date" name="from" id="b2b-from" class="form-control"></div>
                <div class="form-group"><label for="b2b-to">To</label><input type="date" name="to" id="b2b-to" class="form-control"></div>
                @endif
                <button type="submit" class="btn btn-default">Apply</button>
            </form>
        </div>
        @if(in_array($screen,['dashboard','reports']))<div id="b2b-metrics" class="mfg-metrics" aria-live="polite"></div>@endif
        @if($screen === 'pos')
        <div class="b2b-pos-layout">
            <div class="mfg-card"><div class="mfg-section-title"><h2>Products in B2B stock</h2><span id="b2b-count"></span></div><div id="b2b-loading" role="status">Loading products...</div><div id="b2b-table" class="table-responsive"></div><div id="b2b-pagination" class="mfg-pagination"></div></div>
            <div class="mfg-card b2b-cart"><h2>New B2B sale</h2><form id="b2b-sale-form">
                <div class="form-group"><label for="b2b-sale-customer">Business customer</label><select id="b2b-sale-customer" class="form-control" required>
                    <option value="">Choose business customer</option>
                    @foreach($posCustomers as $customer)
                        <option value="{{ $customer->id }}">{{ $customer->supplier_business_name ?: ($customer->name ?: 'Customer #'.$customer->id) }}{{ $customer->mobile ? ' - '.$customer->mobile : '' }}</option>
                    @endforeach
                </select>@if($posCustomers->isEmpty())<p class="help-block">No active customers available. Add or enable a customer in Business Customers, then reopen B2B POS.</p>@endif</div>
                <p class="text-muted">Enter the final unit price for each item. Each price starts blank. No separate tax or discount is added.</p>
                <div class="table-responsive"><table class="table"><thead><tr><th>Product</th><th>Qty</th><th>Manual unit price</th><th>Total</th><th></th></tr></thead><tbody id="b2b-cart-lines"></tbody></table></div>
                <div class="b2b-total">Sale total <strong id="b2b-cart-total">0.0000</strong></div>
                <h3 class="b2b-small-heading">Payments</h3><div id="b2b-pos-payments" class="b2b-payments"></div>
                <div class="mfg-actions"><button type="button" class="btn btn-default btn-sm" data-b2b-action="add-payment">Add split payment</button><button type="button" class="btn btn-default btn-sm" data-b2b-action="pay-full">Pay full total</button></div>
                <p class="text-muted" id="b2b-cart-due">Unpaid amount: 0.0000. Credit sales require permission and customer credit.</p>
                <div class="form-group"><label for="b2b-sale-notes">Notes</label><textarea id="b2b-sale-notes" class="form-control" rows="2" maxlength="2000"></textarea></div>
                @can('b2b.sales.create')<button type="submit" class="btn btn-primary btn-block" id="b2b-checkout">Complete B2B sale</button>@endcan
            </form></div>
        </div>
        @else
        <div class="mfg-card"><div class="mfg-section-title"><h2>{{ in_array($screen,['dashboard','reports']) ? 'B2B invoices' : $title }}</h2><span id="b2b-count"></span></div><div id="b2b-loading" role="status">Loading records...</div><div id="b2b-table" class="table-responsive"></div><div id="b2b-pagination" class="mfg-pagination"></div></div>
        @endif
    </section>
    <div class="modal fade" id="b2b-dialog" tabindex="-1" role="dialog" aria-labelledby="b2b-dialog-title" data-backdrop="static">
        <div class="modal-dialog modal-lg" role="document"><div class="modal-content">
            <div class="modal-header"><button type="button" class="close" data-dismiss="modal" aria-label="Close">&times;</button><h3 id="b2b-dialog-title"></h3></div>
            <form id="b2b-dialog-form"><div class="modal-body"><div id="b2b-form-errors" class="alert alert-danger" role="alert" hidden></div><div id="b2b-dialog-body"></div></div><div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Close</button><button type="submit" class="btn btn-primary" id="b2b-submit">Save</button></div></form>
        </div></div>
    </div>
</div>
@endsection
@section('javascript')
<script src="{{ asset('modules/manufacturing/decimal.js?v='.$asset_v) }}"></script>
<script src="{{ asset('modules/b2b/workspace.js?v='.filemtime(public_path('modules/b2b/workspace.js'))) }}"></script>
@endsection
