@extends('layouts.app')
@section('title', $sale->invoice_no)
@section('css')
<style>
.b2b-invoice{max-width:1000px;margin:24px auto;padding:28px;background:#fff;color:#222;border-radius:10px}.b2b-invoice h1{font-size:26px}.b2b-invoice .table{color:#222}.b2b-invoice th,.b2b-invoice td{padding:10px}.b2b-invoice .table-responsive{overflow:auto}.b2b-invoice .amount{text-align:right;font-variant-numeric:tabular-nums}.b2b-invoice .invoice-actions{display:flex;gap:10px;margin-bottom:20px}.b2b-invoice .invoice-totals{margin-left:auto;max-width:340px}.b2b-invoice p{overflow-wrap:anywhere}@media print{.main-header,.main-sidebar,.main-footer,.invoice-actions,.control-sidebar{display:none!important}.content-wrapper,.right-side{margin:0!important}.b2b-invoice{margin:0;padding:0;max-width:100%}.wrapper{background:white!important}.content-wrapper{background:white!important}}
</style>
@endsection
@section('content')
<section class="content"><article class="b2b-invoice">
    <div class="invoice-actions"><a class="btn btn-default" href="{{ route('b2b.sales') }}">B2B sales</a><button type="button" class="btn btn-primary" onclick="window.print()">Print / Save PDF</button></div>
    <h1>{{ $company }}</h1><h2>B2B invoice {{ $sale->invoice_no }}</h2>
    <p>Date: {{ $sale->transaction_date }} &middot; Payment due: {{ $sale->due_date }}</p>
    <p><strong>{{ $customer['name'] ?? '' }}</strong><br>{{ $customer['mobile'] ?? '' }}<br>{{ $customer['address'] ?? '' }}</p>
    @if(!empty($customer['tax_number']))<p>Customer tax number: {{ $customer['tax_number'] }}</p>@endif
    <div class="table-responsive"><table class="table table-bordered"><thead><tr><th>Product / SKU</th><th>Quantity</th><th>Manual unit price</th><th>Original amount</th><th>Returned qty</th></tr></thead><tbody>
        @foreach($items as $i)<tr><td>{{ $i->product_name }}<br><small>{{ $i->sku }}</small></td><td>{{ $i->quantity }} {{ $i->unit_name }}</td><td class="amount">{{ $i->unit_price_inc_tax }}</td><td class="amount">{{ \App\Support\Decimal::mul((string)$i->quantity, (string)$i->unit_price_inc_tax) }}</td><td>{{ $i->quantity_returned }}</td></tr>@endforeach
    </tbody></table></div>
    <table class="table invoice-totals"><tr><th>Original total</th><td class="amount">{{ $sale->final_total }}</td></tr><tr><th>Returns credited</th><td class="amount">{{ $sale->returned_total }}</td></tr><tr><th>Net total</th><td class="amount">{{ \App\Support\Decimal::sub((string)$sale->final_total, (string)$sale->returned_total) }}</td></tr><tr><th>Paid, less refunds</th><td class="amount">{{ $sale->paid }}</td></tr><tr><th>Due</th><td class="amount">{{ $sale->due }}</td></tr></table>
    @if($sale->additional_notes)<p>{{ $sale->additional_notes }}</p>@endif
    <p>Unit prices are entered manually. No separate tax or discount has been added.</p>
    @if($payments->count())<h3>Payments &amp; refunds</h3><div class="table-responsive"><table class="table"><thead><tr><th>Date</th><th>Reference</th><th>Method</th><th>Type</th><th>Amount</th></tr></thead><tbody>@foreach($payments as $p)<tr><td>{{ $p->paid_on }}</td><td>{{ $p->payment_ref_no }}</td><td>{{ $p->method }}</td><td>{{ $p->is_return ? 'Refund' : 'Collection' }}</td><td>{{ $p->amount }}</td></tr>@endforeach</tbody></table></div>@endif
    @if($returns->count())<h3>Returns</h3>@foreach($returns as $r)<p>{{ $r->created_at }} &middot; {{ $r->total }} &middot; {{ $r->reason }}</p>@endforeach @endif
</article></section>
@endsection
