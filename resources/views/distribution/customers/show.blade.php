@extends('layouts.app')
@section('title', $customer->name.($isDsr ? ' - DSR Profile' : ' - SR Profile'))

@section('content')
@php
    $nameParts = preg_split('/\s+/', trim($customer->name));
    $initials = collect($nameParts)->filter()->take(2)->map(function ($part) {
        return strtoupper(substr($part, 0, 1));
    })->implode('');
    $zone = $customer->zone;
@endphp

<style>
    .customer-profile-page { color: #1f2937; }
    .profile-hero { background: linear-gradient(135deg, #172554 0%, #1d4ed8 55%, #0ea5e9 100%); border-radius: 14px; color: #fff; padding: 28px; margin-bottom: 20px; box-shadow: 0 12px 30px rgba(30,64,175,.2); }
    .profile-hero-inner { display: flex; align-items: center; gap: 22px; flex-wrap: wrap; }
    .profile-avatar { width: 126px; height: 126px; border-radius: 50%; object-fit: cover; border: 5px solid rgba(255,255,255,.9); box-shadow: 0 8px 20px rgba(15,23,42,.3); background: #dbeafe; color: #1e3a8a; display: flex; align-items: center; justify-content: center; font-size: 38px; font-weight: 800; }
    .profile-identity { flex: 1; min-width: 240px; }
    .profile-identity h1 { margin: 0 0 7px; color: #fff; font-size: 30px; font-weight: 750; }
    .profile-meta { display: flex; flex-wrap: wrap; gap: 8px 18px; color: rgba(255,255,255,.88); }
    .profile-meta span i { margin-right: 6px; }
    .profile-actions { display: flex; flex-direction: column; align-items: flex-end; gap: 10px; }
    .profile-actions .btn { border-radius: 7px; }
    .photo-upload { background: rgba(255,255,255,.12); padding: 10px; border-radius: 9px; width: 275px; }
    .photo-upload input { color: #fff; max-width: 180px; display: inline-block; font-size: 11px; }
    .customer-profile-page .metric-card { background: #fff !important; border-radius: 11px; padding: 18px; margin-bottom: 20px; border: 1px solid #e5e7eb; box-shadow: 0 4px 15px rgba(15,23,42,.09); min-height: 112px; }
    .metric-icon { float: left; width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-right: 14px; font-size: 20px; }
    .metric-label { color: #6b7280; font-size: 12px; text-transform: uppercase; letter-spacing: .05em; font-weight: 700; }
    .metric-value { font-size: 23px; font-weight: 750; color: #111827; margin-top: 5px; }
    .customer-profile-page .profile-panel { background: #fff !important; border: 1px solid #dbe3ee; border-radius: 12px; overflow: hidden; box-shadow: 0 5px 18px rgba(15,23,42,.1); margin-bottom: 20px; color: #1f2937 !important; }
    .customer-profile-page .profile-panel .box-header { background: #f8fafc !important; color: #172033 !important; padding: 16px 18px; border-bottom: 1px solid #dbe3ee; }
    .customer-profile-page .profile-panel .box-title { color: #172033 !important; font-weight: 700; font-size: 16px; }
    .customer-profile-page .profile-panel .box-body { background: #fff !important; color: #1f2937 !important; padding: 18px; max-height: none; overflow: visible; }
    .detail-row { display: flex; gap: 12px; padding: 12px 0; border-bottom: 1px solid #e5e7eb; }
    .detail-row:last-child { border-bottom: 0; }
    .detail-icon { width: 32px; color: #2563eb; text-align: center; }
    .detail-label { color: #64748b !important; font-size: 12px; margin-bottom: 2px; }
    .detail-value { color: #111827 !important; font-weight: 650; overflow-wrap: anywhere; }
    .performance-bar { height: 9px; background: #e5e7eb; border-radius: 9px; overflow: hidden; margin-top: 8px; }
    .performance-bar span { display: block; height: 100%; background: linear-gradient(90deg, #2563eb, #06b6d4); }
    .customer-profile-page .profile-table { background: #fff !important; color: #1f2937 !important; }
    .customer-profile-page .profile-table thead th { background: #eef2f7 !important; color: #334155 !important; font-size: 12px; font-weight: 750; text-transform: uppercase; letter-spacing: .04em; border: 0 !important; border-bottom: 2px solid #cbd5e1 !important; padding: 11px 9px; }
    .customer-profile-page .profile-table tbody td { background: #fff !important; color: #334155 !important; vertical-align: middle !important; border-top: 1px solid #e2e8f0 !important; padding: 11px 9px; }
    .customer-profile-page .profile-table tbody tr:hover td { background: #f1f5f9 !important; }
    .customer-profile-page .profile-table td strong { color: #0f172a !important; }
    .customer-profile-page .profile-panel .text-muted { color: #64748b !important; }
    .item-pill { display: inline-block; padding: 3px 7px; margin: 2px; background: #eff6ff; color: #1d4ed8; border-radius: 12px; font-size: 11px; }
    .filter-bar { display: flex; flex-wrap: wrap; gap: 8px; justify-content: flex-end; margin-bottom: 14px; }
    .filter-bar .form-control { width: auto; min-width: 145px; background: #fff !important; color: #111827 !important; border-color: #cbd5e1; }
    @media (max-width: 767px) {
        .profile-hero { padding: 20px; text-align: center; }
        .profile-hero-inner { justify-content: center; }
        .profile-meta { justify-content: center; }
        .profile-actions { align-items: center; width: 100%; }
        .photo-upload { width: 100%; }
        .filter-bar { justify-content: stretch; }
        .filter-bar .form-control { width: 100%; }
    }
</style>

<section class="content customer-profile-page">
    @if(session('status'))
        <div class="alert alert-{{ session('status.success') ? 'success' : 'danger' }}">{{ session('status.msg') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <div class="profile-hero">
        <div class="profile-hero-inner">
            @if($customer->profile_image_url)
                <img class="profile-avatar" src="{{ $customer->profile_image_url }}" alt="{{ $customer->name }}">
            @else
                <div class="profile-avatar">{{ $initials ?: 'DC' }}</div>
            @endif

            <div class="profile-identity">
                <div style="margin-bottom:8px;">
                    <span class="label label-{{ $customer->status === 'active' ? 'success' : 'default' }}">{{ ucfirst($customer->status) }}</span>
                    <span style="margin-left:7px;color:rgba(255,255,255,.75);">{{ $isDsr ? 'Delivery Sales Representative' : 'Sales Representative' }} #{{ $customer->id }}</span>
                </div>
                <h1>{{ $customer->name }}</h1>
                <div class="profile-meta">
                    @unless($isDsr)
                    <span><i class="fa fa-envelope"></i>{{ $customer->email }}</span>
                    @endunless
                    <span><i class="fa fa-phone"></i>{{ $customer->phone }}</span>
                    @if($isDsr)
                    <span><i class="fa fa-user"></i>Assigned SR: {{ optional($customer->assignedSalesRepresentative)->name ?: '-' }}</span>
                    @else
                    <span><i class="fa fa-map-marker"></i>{{ $zone ? $zone->name : 'No zone assigned' }}</span>
                    @if($zone && $zone->businessLocation)<span><i class="fa fa-building"></i>{{ $zone->businessLocation->name }}</span>@endif
                    @endif
                </div>
            </div>

            <div class="profile-actions">
                <div>
                    <a href="{{ action([\App\Http\Controllers\Distribution\CustomerController::class, 'edit'], [$customer->id]) }}" class="btn btn-warning btn-sm"><i class="fa fa-edit"></i> Edit Profile</a>
                    <a href="{{ action([\App\Http\Controllers\Distribution\CustomerController::class, 'index'], $isDsr ? ['employee_type' => 'dsr'] : []) }}" class="btn btn-default btn-sm"><i class="fa fa-arrow-left"></i> {{ $isDsr ? 'DSR List' : 'SR List' }}</a>
                </div>
                @if(auth()->user()->can('customer.update') || auth()->user()->can('customer.create'))
                    {!! Form::open(['route' => ['distribution.customers.profile-image.update', $customer->id], 'method' => 'post', 'files' => true, 'class' => 'photo-upload']) !!}
                        <div style="font-size:12px;font-weight:700;margin-bottom:5px;"><i class="fa fa-camera"></i> {{ $customer->profile_image ? 'Replace photo' : 'Upload photo' }}</div>
                        <input type="file" name="profile_image" accept="image/jpeg,image/png,image/webp" required>
                        <button type="submit" class="btn btn-xs btn-success">Save</button>
                    {!! Form::close() !!}
                @endif
            </div>
        </div>
    </div>

    @unless($isDsr)
    <div class="row">
        <div class="col-sm-6 col-lg-3"><div class="metric-card"><div class="metric-icon" style="background:#dcfce7;color:#15803d;"><i class="fa fa-shopping-cart"></i></div><div class="metric-label">Total Sales</div><div class="metric-value">৳{{ number_format($profileStats['total_sales'], 2) }}</div></div></div>
        <div class="col-sm-6 col-lg-3"><div class="metric-card"><div class="metric-icon" style="background:#dbeafe;color:#1d4ed8;"><i class="fa fa-file-invoice"></i></div><div class="metric-label">Sales Records</div><div class="metric-value">{{ number_format($profileStats['sales_count']) }}</div></div></div>
        <div class="col-sm-6 col-lg-3"><div class="metric-card"><div class="metric-icon" style="background:#fef3c7;color:#b45309;"><i class="fa fa-boxes"></i></div><div class="metric-label">Stock Items</div><div class="metric-value">{{ number_format($profileStats['stock_items']) }}</div></div></div>
        <div class="col-sm-6 col-lg-3"><div class="metric-card"><div class="metric-icon" style="background:#ede9fe;color:#6d28d9;"><i class="fa fa-wallet"></i></div><div class="metric-label">Stock Value</div><div class="metric-value">৳{{ number_format($profileStats['stock_value'], 2) }}</div></div></div>
    </div>
    @endunless

    <div class="row">
        <div class="{{ $isDsr ? 'col-lg-12' : 'col-lg-4' }}">
            <div class="box profile-panel">
                <div class="box-header"><h3 class="box-title"><i class="fa fa-user-circle text-primary"></i> Contact Information</h3></div>
                <div class="box-body">
                    @unless($isDsr)
                    <div class="detail-row"><div class="detail-icon"><i class="fa fa-envelope"></i></div><div><div class="detail-label">Email address</div><div class="detail-value">{{ $customer->email }}</div></div></div>
                    @endunless
                    <div class="detail-row"><div class="detail-icon"><i class="fa fa-phone"></i></div><div><div class="detail-label">Phone number</div><div class="detail-value">{{ $customer->phone ?: '-' }}</div></div></div>
                    <div class="detail-row"><div class="detail-icon"><i class="fa fa-map-marker"></i></div><div><div class="detail-label">Address</div><div class="detail-value">{{ $customer->address ?: '-' }}</div></div></div>
                    @if($isDsr)
                    <div class="detail-row"><div class="detail-icon"><i class="fa fa-user"></i></div><div><div class="detail-label">Assigned SR</div><div class="detail-value">{{ optional($customer->assignedSalesRepresentative)->name ?: '-' }}</div></div></div>
                    @else
                    <div class="detail-row"><div class="detail-icon"><i class="fa fa-map"></i></div><div><div class="detail-label">Zone</div><div class="detail-value">{{ $zone ? $zone->name : '-' }}</div></div></div>
                    <div class="detail-row"><div class="detail-icon"><i class="fa fa-building"></i></div><div><div class="detail-label">Business location</div><div class="detail-value">{{ $zone && $zone->businessLocation ? $zone->businessLocation->name : '-' }}</div></div></div>
                    <div class="detail-row"><div class="detail-icon"><i class="fa fa-cubes"></i></div><div><div class="detail-label">Stock owner</div><div class="detail-value">{{ $stockCustomer->name ?? $customer->name }}</div></div></div>
                    @endif
                </div>
            </div>

            <div class="box profile-panel">
                <div class="box-header"><h3 class="box-title"><i class="fa fa-briefcase text-primary"></i> Employment & Payroll</h3></div>
                <div class="box-body">
                    <div class="detail-row"><div class="detail-icon"><i class="fa fa-money-bill"></i></div><div><div class="detail-label">Base salary</div><div class="detail-value">৳{{ number_format($customer->base_salary ?? 0, 2) }}</div></div></div>
                    @unless($isDsr)
                    <div class="detail-row"><div class="detail-icon"><i class="fa fa-percent"></i></div><div><div class="detail-label">Commission</div><div class="detail-value">{{ number_format($customer->commission_percent ?? 0, 2) }}%</div></div></div>
                    <div class="detail-row"><div class="detail-icon"><i class="fa fa-bus"></i></div><div><div class="detail-label">Daily TA/DA</div><div class="detail-value">৳{{ number_format($customer->tada_bill_amount ?? 0, 2) }}</div></div></div>
                    <div class="detail-row"><div class="detail-icon"><i class="fa fa-bullseye"></i></div><div style="width:100%;"><div class="detail-label">Monthly target</div><div class="detail-value">৳{{ number_format($customer->monthly_target_amount ?? 0, 2) }}</div><div class="performance-bar"><span style="width:{{ $profileStats['achievement_percentage'] }}%;"></span></div><small class="text-muted">{{ number_format($profileStats['achievement_percentage'], 1) }}% achieved</small></div></div>
                    @else
                    <div class="detail-row"><div class="detail-icon"><i class="fa fa-truck"></i></div><div><div class="detail-label">Payroll rule</div><div class="detail-value">Salary only — no attendance deductions</div></div></div>
                    @endunless
                </div>
            </div>

            @unless($isDsr)
            <div class="box profile-panel">
                <div class="box-header"><h3 class="box-title"><i class="fa fa-clock text-primary"></i> Attendance Settings</h3></div>
                <div class="box-body">
                    <div class="detail-row"><div class="detail-icon"><i class="fa fa-map-pin"></i></div><div><div class="detail-label">Attendance location</div><div class="detail-value">{{ $customer->attendance_location ?: '-' }}</div></div></div>
                    <div class="detail-row"><div class="detail-icon"><i class="fa fa-sign-in-alt"></i></div><div><div class="detail-label">Shift</div><div class="detail-value">{{ $customer->shift_start_time ?: '-' }} — {{ $customer->shift_end_time ?: '-' }}</div></div></div>
                    <div class="detail-row"><div class="detail-icon"><i class="fa fa-location-arrow"></i></div><div><div class="detail-label">GPS coordinates</div><div class="detail-value">{{ $customer->work_latitude ?: '-' }}, {{ $customer->work_longitude ?: '-' }}</div></div></div>
                    <div class="detail-row"><div class="detail-icon"><i class="fa fa-crosshairs"></i></div><div><div class="detail-label">Allowed radius</div><div class="detail-value">{{ number_format($customer->allowed_radius_meters ?? 0) }} meters</div></div></div>
                </div>
            </div>
            @endunless
        </div>

        @unless($isDsr)
        <div class="col-lg-8">
            <div class="box profile-panel">
                <div class="box-header"><h3 class="box-title"><i class="fa fa-chart-line text-primary"></i> Sales History</h3></div>
                <div class="box-body">
                    <form method="GET" action="{{ url()->current() }}" class="filter-bar">
                        <input type="date" class="form-control input-sm" name="from_date" value="{{ request('from_date') }}" aria-label="From date">
                        <input type="date" class="form-control input-sm" name="to_date" value="{{ request('to_date') }}" aria-label="To date">
                        <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-filter"></i> Filter</button>
                        <a href="{{ url()->current() }}" class="btn btn-default btn-sm">Reset</a>
                        <a href="{{ route('distribution.customers.sales-report.daily', ['customer' => $customer->id]) }}" class="btn btn-info btn-sm">Daily Report</a>
                        <a href="{{ route('distribution.customers.sales-report.monthly', ['customer' => $customer->id]) }}" class="btn btn-info btn-sm">Monthly Report</a>
                    </form>
                    <div class="table-responsive">
                        <table class="table table-hover profile-table">
                            <thead><tr><th>Sold To</th><th>Items</th><th>Quantity</th><th>Amount</th><th>Date</th></tr></thead>
                            <tbody>
                                @forelse($sales as $sale)
                                    @php($items = json_decode($sale->items, true) ?: [])
                                    <tr>
                                        <td><strong>{{ $sale->sold_to ?: '-' }}</strong><br><small class="text-muted">{{ $sale->resaler_address ?: '' }}</small></td>
                                        <td>@forelse($items as $item)<span class="item-pill">{{ $item['name'] ?? 'Item' }} × {{ number_format($item['quantity'] ?? 0, 0) }}</span>@empty<span class="text-muted">No item details</span>@endforelse</td>
                                        <td>{{ number_format($sale->quantity, 2) }}</td>
                                        <td><strong>৳{{ number_format($sale->sale_price, 2) }}</strong></td>
                                        <td>{{ optional($sale->created_at)->format('d M Y, h:i A') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-center text-muted" style="padding:30px;">No sales found for this period.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="text-center">{{ $sales->appends(request()->query())->links() }}</div>
                </div>
            </div>

            <div class="box profile-panel">
                <div class="box-header"><h3 class="box-title"><i class="fa fa-boxes text-primary"></i> Stock Summary</h3><small class="pull-right text-muted">{{ $stockCustomer->id === $customer->id ? 'Customer-owned stock' : 'Shared stock owned by '.$stockCustomer->name }}</small></div>
                <div class="box-body">
                    <div class="table-responsive">
                        <table class="table table-hover profile-table">
                            <thead><tr><th>Product</th><th class="text-right">Available Quantity</th><th class="text-right">Purchase Price</th><th class="text-right">Stock Value</th></tr></thead>
                            <tbody>
                                @forelse($stockCustomer->stocks as $stock)
                                    <tr><td><strong>{{ optional($stock->product)->name ?? '-' }}</strong></td><td class="text-right">{{ number_format($stock->quantity, 2) }}</td><td class="text-right">৳{{ number_format($stock->purchase_price, 2) }}</td><td class="text-right"><strong>৳{{ number_format($stock->quantity * $stock->purchase_price, 2) }}</strong></td></tr>
                                @empty
                                    <tr><td colspan="4" class="text-center text-muted" style="padding:30px;">No stock is available.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        @endunless
    </div>
</section>
@endsection
