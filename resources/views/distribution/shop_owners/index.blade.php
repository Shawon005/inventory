@extends('layouts.app')
@section('title', 'Shop Owners')

@section('content')
<style>
    .owner-summary { background:linear-gradient(135deg,#172554,#1d4ed8); border:0; border-radius:14px; color:#fff; overflow:hidden; }
    .owner-summary .box-body { padding:24px; }
    .owner-summary h2 { margin:0 0 6px; font-size:26px; font-weight:700; }
    .owner-summary p { margin:0; color:#dbeafe; }
    .owner-filter { border-radius:12px; box-shadow:0 6px 24px rgba(15,23,42,.06); border:1px solid #e5e7eb; }
    .owner-table > thead > tr > th { background:#f8fafc; color:#475569; font-size:12px; text-transform:uppercase; letter-spacing:.04em; border-bottom:1px solid #e2e8f0; padding:12px; }
    .owner-table > tbody > tr > td { vertical-align:middle; padding:13px 12px; border-color:#eef2f7; }
    .owner-avatar { width:42px; height:42px; border-radius:12px; display:inline-flex; align-items:center; justify-content:center; background:#dbeafe; color:#1d4ed8; font-weight:800; font-size:17px; margin-right:10px; }
    .owner-name { display:flex; align-items:center; }
    .owner-name strong { display:block; color:#f7f7f7;}
    .owner-name small { color:#64748b; }
    .sales-amount { color:#15803d; font-weight:700; }
    .empty-owners { text-align:center; padding:60px 20px; color:#64748b; }
    .empty-owners i { display:block; font-size:48px; color:#cbd5e1; margin-bottom:15px; }
</style>

<section class="content-header">
    <h1>Shop Owners <small>App-registered shops and sales performance</small></h1>
</section>

<section class="content">
    @if(session('status'))
        <div class="alert alert-{{ session('status.success') ? 'success' : 'danger' }}">{{ session('status.msg') }}</div>
    @endif

    <div class="box owner-summary">
        <div class="box-body">
            <div class="row">
                <div class="col-sm-8">
                    <h2><i class="fa fa-building-o"></i> {{ number_format($owners->total()) }} Shop Owners</h2>
                    <p>Review owner details, the registering SR, shop location, and linked mobile sales.</p>
                </div>
                <div class="col-sm-4 text-right" style="padding-top:4px;">
                    @if(auth()->user()->can('customer.create') || auth()->user()->can('customer.update'))
                        <a href="{{ route('distribution.shop-owners.create') }}" class="btn btn-default btn-lg" style="color:#1d4ed8;border:0;font-weight:700;"><i class="fa fa-plus"></i> Add Shop Owner</a>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="box owner-filter">
        <div class="box-body">
            <form method="GET" action="{{ route('distribution.shop-owners.index') }}">
                <div class="row">
                    <div class="col-md-4">
                        <label for="search">Search owners</label>
                        <div class="input-group">
                            <span class="input-group-addon"><i class="fa fa-search"></i></span>
                            <input type="text" class="form-control" id="search" name="search" value="{{ request('search') }}" placeholder="Name, email, phone, address, or road">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <label for="distributor_id">Registered by SR</label>
                        <select class="form-control select2" id="distributor_id" name="distributor_id" style="width:100%;">
                            <option value="">All sales representatives</option>
                            @foreach($distributors as $id => $name)
                                <option value="{{ $id }}" {{ (string) request('distributor_id') === (string) $id ? 'selected' : '' }}>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="dealer_id">Dealer</label>
                        <select class="form-control select2" id="dealer_id" name="dealer_id" style="width:100%;">
                            <option value="">All dealers</option>
                            @foreach($dealers as $id => $name)
                                <option value="{{ $id }}" {{ (string) request('dealer_id') === (string) $id ? 'selected' : '' }}>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="road_name">Road Name</label>
                        <select class="form-control select2" id="road_name" name="road_name" style="width:100%;">
                            <option value="">All roads</option>
                            @foreach($roads as $road)
                                <option value="{{ $road }}" {{ request('road_name') === $road ? 'selected' : '' }}>{{ $road }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="visit_day">Visit Day</label>
                        <select class="form-control" id="visit_day" name="visit_day">
                            <option value="">All visit days</option>
                            @foreach(\App\ShopOwner::VISIT_DAYS as $value => $label)
                                <option value="{{ $value }}" {{ request('visit_day') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12" style="padding-top:15px;">
                        <button class="btn btn-primary" type="submit"><i class="fa fa-filter"></i> Apply</button>
                        <a class="btn btn-default" href="{{ route('distribution.shop-owners.index') }}">Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="box owner-filter">
        <div class="box-body no-padding">
            <div class="table-responsive">
                <table class="table owner-table" style="margin-bottom:0;">
                    <thead>
                        <tr>
                            <th>Shop Owner</th>
                            <th>Contact</th>
                            <th>Registered By</th>
                            <th>Location</th>
                            <th>Road Name</th>
                            <th>Visit Day</th>
                            <th class="text-center">Sale Visits</th>
                            <th class="text-right">Gross Sales</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($owners as $owner)
                            <tr>
                                <td>
                                    <div class="owner-name">
                                        <span class="owner-avatar">{{ strtoupper(mb_substr($owner->name, 0, 1)) }}</span>
                                        <span><strong>{{ $owner->name }}</strong><small>Added {{ optional($owner->created_at)->format('d M Y') }}</small></span>
                                    </div>
                                </td>
                                <td>
                                    <div><i class="fa fa-phone text-muted"></i> {{ optional($owner->phones->first())->phone ?: '-' }}</div>
                                    <small class="text-muted">{{ $owner->email ?: '-' }}</small>
                                </td>
                                <td><strong>{{ optional($owner->distributor)->name ?: '-' }}</strong></td>
                                <td title="{{ $owner->address }}">{{ \Illuminate\Support\Str::limit($owner->address, 34) }}</td>
                                <td>{{ $owner->road_name ?: '-' }}</td>
                                <td>{{ \App\ShopOwner::VISIT_DAYS[$owner->visit_day] ?? '-' }}</td>
                                <td class="text-center"><span class="label label-info">{{ number_format($owner->sales_count) }}</span></td>
                                <td class="text-right sales-amount">@format_currency($owner->sales_total ?? 0)</td>
                                <td class="text-right" style="white-space:nowrap;">
                                    <a href="{{ route('distribution.shop-owners.show', $owner) }}" class="btn btn-xs btn-info"><i class="fa fa-eye"></i> Details</a>
                                    @if(auth()->user()->can('customer.create') || auth()->user()->can('customer.update'))
                                        <a href="{{ route('distribution.shop-owners.edit', $owner) }}" class="btn btn-xs btn-primary"><i class="fa fa-edit"></i> Edit</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9"><div class="empty-owners"><i class="fa fa-building-o"></i><strong>No shop owners found</strong><br><span>Owners added from the SR app will appear here automatically.</span></div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($owners->hasPages())
            <div class="box-footer text-center">{{ $owners->links() }}</div>
        @endif
    </div>
</section>
@endsection
