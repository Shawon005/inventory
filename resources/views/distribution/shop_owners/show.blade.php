@extends('layouts.app')
@section('title', $owner->name)

@section('content')
@php
    $maxMonthly = max(1, (float) $monthlyPerformance->max('amount'));
    $zone = optional($owner->distributor)->zone;
@endphp
<style>
    .owner-hero { border:0; border-radius:16px; overflow:hidden; box-shadow:0 10px 30px rgba(15,23,42,.1); }
    .owner-hero .box-body { padding:26px; background:linear-gradient(130deg,#0f172a,#1e40af); color:#fff; }
    .profile-mark { width:74px; height:74px; border-radius:20px; background:rgba(255,255,255,.16); border:1px solid rgba(255,255,255,.25); display:flex; align-items:center; justify-content:center; font-size:30px; font-weight:800; float:left; margin-right:18px; }
    .hero-title { margin:5px 0 4px; font-size:27px; font-weight:800; }
    .hero-subtitle { color:#bfdbfe; margin:0; }
    .hero-actions { padding-top:15px; }
    .metric-card { background:#fff; border:1px solid #e2e8f0; border-radius:13px; padding:18px; margin-bottom:20px; min-height:115px; box-shadow:0 5px 18px rgba(15,23,42,.05); }
    .metric-card .icon { width:40px; height:40px; border-radius:11px; display:flex; align-items:center; justify-content:center; float:right; font-size:18px; }
    .metric-label { color:#64748b; font-size:12px; text-transform:uppercase; letter-spacing:.04em; font-weight:700; }
    .metric-value { color:#0f172a; font-size:23px; line-height:1.3; font-weight:800; margin-top:10px; }
    .profile-box { border:1px solid #e2e8f0; border-radius:13px; box-shadow:0 5px 18px rgba(15,23,42,.05); overflow:hidden; }
    .profile-box .box-header { padding:15px 18px; border-bottom:1px solid #eef2f7; }
    .profile-box .box-body { padding:18px; }
    .info-row { display:flex; padding:11px 0; border-bottom:1px solid #f1f5f9; }
    .info-row:last-child { border:0; }
    .info-row i { width:28px; color:#2563eb; margin-top:3px; }
    .info-row small { display:block; color:#94a3b8; text-transform:uppercase; font-weight:700; font-size:10px; letter-spacing:.05em; }
    .info-row strong { color:#334155; overflow-wrap:anywhere; }
    .month-row { display:grid; grid-template-columns:72px 1fr 90px; gap:10px; align-items:center; margin:14px 0; }
    .month-track { height:9px; background:#e2e8f0; border-radius:10px; overflow:hidden; }
    .month-fill { height:100%; background:linear-gradient(90deg,#2563eb,#22c55e); border-radius:10px; }
    .visit-table > thead > tr > th { background:#f8fafc; color:#64748b; text-transform:uppercase; font-size:11px; letter-spacing:.04em; padding:11px; }
    .visit-table > tbody > tr > td { vertical-align:middle; padding:12px 11px; border-color:#eef2f7; }
    .sr-badge { display:inline-flex; align-items:center; gap:7px; }
    .sr-dot { width:28px; height:28px; display:inline-flex; justify-content:center; align-items:center; border-radius:50%; background:#dbeafe; color:#1d4ed8; font-weight:800; }
    @media(max-width:767px) { .hero-actions { text-align:left!important; } .month-row { grid-template-columns:62px 1fr; } .month-row strong { grid-column:2; } }
</style>

<section class="content-header">
    <h1>Shop Owner Profile <small>Sales and SR visit intelligence</small></h1>
</section>

<section class="content">
    @if(session('status'))
        <div class="alert alert-{{ session('status.success') ? 'success' : 'danger' }}">{{ session('status.msg') }}</div>
    @endif

    <div class="box owner-hero">
        <div class="box-body">
            <div class="row">
                <div class="col-md-8 clearfix">
                    <div class="profile-mark">{{ strtoupper(mb_substr($owner->name, 0, 1)) }}</div>
                    <h2 class="hero-title">{{ $owner->name }}</h2>
                    <p class="hero-subtitle"><i class="fa fa-map-marker"></i> {{ $owner->address }}</p>
                    <p class="hero-subtitle" style="margin-top:4px;"><i class="fa fa-user"></i> Registered by {{ optional($owner->distributor)->name ?: 'Unknown SR' }} on {{ optional($owner->created_at)->format('d M Y') }}</p>
                </div>
                <div class="col-md-4 text-right hero-actions">
                    @if(auth()->user()->can('customer.create') || auth()->user()->can('customer.update'))
                        <a href="{{ route('distribution.shop-owners.edit', $owner) }}" class="btn btn-warning"><i class="fa fa-edit"></i> Edit Owner</a>
                    @endif
                    <a href="{{ route('distribution.shop-owners.index') }}" class="btn btn-default"><i class="fa fa-arrow-left"></i> Owner List</a>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-sm-6 col-lg-3"><div class="metric-card"><span class="icon" style="background:#dcfce7;color:#15803d;"><i class="fa fa-line-chart"></i></span><div class="metric-label">Net Delivered Sales</div><div class="metric-value">@format_currency($stats['net_sales'])</div><small class="text-muted">After returns</small></div></div>
        <div class="col-sm-6 col-lg-3"><div class="metric-card"><span class="icon" style="background:#dbeafe;color:#1d4ed8;"><i class="fa fa-shopping-cart"></i></span><div class="metric-label">Total Sale Visits</div><div class="metric-value">{{ number_format($stats['order_count']) }}</div><small class="text-muted">{{ number_format($stats['sr_count']) }} SR involved</small></div></div>
        <div class="col-sm-6 col-lg-3"><div class="metric-card"><span class="icon" style="background:#fef3c7;color:#b45309;"><i class="fa fa-calendar"></i></span><div class="metric-label">This Month</div><div class="metric-value">@format_currency($stats['this_month_sales'])</div><small class="text-muted">Active orders</small></div></div>
        <div class="col-sm-6 col-lg-3"><div class="metric-card"><span class="icon" style="background:#fee2e2;color:#b91c1c;"><i class="fa fa-undo"></i></span><div class="metric-label">Total Returns</div><div class="metric-value">@format_currency($stats['return_amount'])</div><small class="text-muted">Average order @format_currency($stats['average_order'])</small></div></div>
    </div>

    <div class="row">
        <div class="col-lg-4">
            <div class="box profile-box">
                <div class="box-header"><h3 class="box-title"><i class="fa fa-address-card text-primary"></i> Contact & Territory</h3></div>
                <div class="box-body">
                    <div class="info-row"><i class="fa fa-envelope"></i><div><small>Email</small><strong>{{ $owner->email }}</strong></div></div>
                    <div class="info-row"><i class="fa fa-phone"></i><div><small>Phone Numbers</small><strong>{{ $owner->phones->pluck('phone')->implode(', ') ?: '-' }}</strong></div></div>
                    <div class="info-row"><i class="fa fa-map-marker"></i><div><small>Shop Address</small><strong>{{ $owner->address }}</strong></div></div>
                    <div class="info-row"><i class="fa fa-map"></i><div><small>Zone</small><strong>{{ optional($zone)->name ?: '-' }}</strong></div></div>
                    <div class="info-row"><i class="fa fa-building"></i><div><small>Business Location</small><strong>{{ optional(optional($zone)->businessLocation)->name ?: '-' }}</strong></div></div>
                    <div class="info-row"><i class="fa fa-crosshairs"></i><div><small>GPS Coordinates</small><strong>{{ number_format($owner->latitude, 6) }}, {{ number_format($owner->longitude, 6) }}</strong><br><a href="https://www.google.com/maps?q={{ $owner->latitude }},{{ $owner->longitude }}" target="_blank" rel="noopener">Open on map <i class="fa fa-external-link"></i></a></div></div>
                    <div class="info-row"><i class="fa fa-clock-o"></i><div><small>Last Sale Visit</small><strong>{{ $stats['last_visit'] ? \Carbon\Carbon::parse($stats['last_visit'])->format('d M Y, h:i A') : 'No visits recorded' }}</strong></div></div>
                </div>
            </div>

            <div class="box profile-box">
                <div class="box-header"><h3 class="box-title"><i class="fa fa-bar-chart text-primary"></i> Six-Month Performance</h3></div>
                <div class="box-body">
                    @foreach($monthlyPerformance as $month)
                        <div class="month-row">
                            <span class="text-muted">{{ $month['label'] }}</span>
                            <div class="month-track"><div class="month-fill" style="width:{{ ($month['amount'] / $maxMonthly) * 100 }}%;"></div></div>
                            <strong class="text-right">@format_currency($month['amount'])</strong>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="box profile-box">
                <div class="box-header"><h3 class="box-title"><i class="fa fa-map-signs text-primary"></i> SR Sale Visit History</h3></div>
                <div class="box-body">
                    <form method="GET" action="{{ route('distribution.shop-owners.show', $owner) }}" class="well well-sm">
                        <div class="row">
                            <div class="col-sm-3"><label>From</label><input type="date" name="from_date" class="form-control input-sm" value="{{ request('from_date') }}"></div>
                            <div class="col-sm-3"><label>To</label><input type="date" name="to_date" class="form-control input-sm" value="{{ request('to_date') }}"></div>
                            <div class="col-sm-3"><label>Status</label><select name="status" class="form-control input-sm"><option value="">All statuses</option><option value="ordered" {{ request('status') === 'ordered' ? 'selected' : '' }}>Ordered</option><option value="delivered" {{ request('status') === 'delivered' ? 'selected' : '' }}>Delivered</option><option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Cancelled</option></select></div>
                            <div class="col-sm-3" style="padding-top:25px;"><button class="btn btn-primary btn-sm"><i class="fa fa-filter"></i> Filter</button> <a href="{{ route('distribution.shop-owners.show', $owner) }}" class="btn btn-default btn-sm">Reset</a></div>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table visit-table">
                            <thead><tr><th>Date & Time</th><th>Sales Representative</th><th>Order Details</th><th>Status</th><th class="text-right">Amount</th></tr></thead>
                            <tbody>
                                @forelse($visits as $sale)
                                    @php
                                        $items = json_decode($sale->items, true) ?: [];
                                        $status = strtolower((string) $sale->status) ?: 'delivered';
                                        $statusClass = in_array($status, ['cancelled', 'canceled']) ? 'danger' : ($status === 'delivered' ? 'success' : 'warning');
                                    @endphp
                                    <tr>
                                        <td><strong>{{ optional($sale->created_at)->format('d M Y') }}</strong><br><small class="text-muted">{{ optional($sale->created_at)->format('h:i A') }}</small></td>
                                        <td><span class="sr-badge"><span class="sr-dot">{{ strtoupper(mb_substr(optional($sale->customer)->name ?: '?', 0, 1)) }}</span><span><strong>{{ optional($sale->customer)->name ?: 'Unknown SR' }}</strong><br><small class="text-muted">{{ optional($sale->customer)->phone }}</small></span></span></td>
                                        <td>
                                            @forelse(array_slice($items, 0, 3) as $item)
                                                <div>{{ $item['name'] ?? optional($sale->product)->name ?? 'Item' }} <small class="text-muted">× {{ number_format($item['quantity'] ?? 0, 2) }}</small></div>
                                            @empty
                                                <span>{{ optional($sale->product)->name ?: 'Sale order' }}</span>
                                            @endforelse
                                            @if(count($items) > 3)<small class="text-muted">+{{ count($items) - 3 }} more items</small>@endif
                                        </td>
                                        <td><span class="label label-{{ $statusClass }}">{{ ucfirst($status === 'canceled' ? 'cancelled' : $status) }}</span>@if((float) $sale->return_amount > 0)<br><small class="text-danger">Return: @format_currency($sale->return_amount)</small>@endif</td>
                                        <td class="text-right"><strong>@format_currency($sale->sale_price)</strong><br><small class="text-muted">{{ ucfirst($sale->payment_method ?: 'Not specified') }}</small></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-center text-muted" style="padding:45px;"><i class="fa fa-shopping-cart" style="font-size:32px;display:block;margin-bottom:10px;color:#cbd5e1;"></i>No linked sale visits found. The mobile app should submit <code>shop_owner_id</code> when creating a sale.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="text-center">{{ $visits->links() }}</div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
