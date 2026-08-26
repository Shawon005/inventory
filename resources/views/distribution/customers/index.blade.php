@extends('layouts.app')
@section('title', $isDsr ? 'DSR (Delivery Sales Representative)' : 'SR (Sales Representative)')
@section('content')
<section class="content-header">
    <h1>{{ $isDsr ? 'DSR (Delivery Sales Representative)' : 'SR (Sales Representative)' }}
        <small>{{ $isDsr ? 'Manage delivery employees for payroll' : 'Manage sales representatives' }}</small>
    </h1>
</section>
<section class="content">
    @if(session('status'))
        <div class="alert alert-{{ session('status.success') ? 'success' : 'danger' }}">
            {{ session('status.msg') }}
        </div>
    @endif
    <div class="box box-primary">
        <div class="box-header with-border">
            <h3 class="box-title"><i class="fa fa-filter"></i> Filter {{ $isDsr ? 'DSR' : 'SR' }}</h3>
        </div>
        <div class="box-body">
            <form method="GET" action="{{ action([\App\Http\Controllers\Distribution\CustomerController::class, 'index']) }}">
                @if($isDsr)
                    <input type="hidden" name="employee_type" value="dsr">
                @endif
                <div class="row">
                    <div class="col-md-{{ $isDsr ? '3' : '4' }}">
                        <div class="form-group">
                            <label for="location_id">Business Location</label>
                            <select name="location_id" id="location_id" class="form-control select2" style="width:100%;">
                                <option value="">All locations</option>
                                @foreach($locations as $id => $name)
                                    <option value="{{ $id }}" {{ (string) request('location_id') === (string) $id ? 'selected' : '' }}>{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-{{ $isDsr ? '3' : '4' }}">
                        <div class="form-group">
                            <label for="zone_id">Zone</label>
                            <select name="zone_id" id="zone_id" class="form-control select2" style="width:100%;">
                                <option value="">All zones</option>
                                @foreach($zones as $id => $name)
                                    <option value="{{ $id }}" {{ (string) request('zone_id') === (string) $id ? 'selected' : '' }}>{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    @if($isDsr)
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="assigned_sr_customer_id">Assigned SR</label>
                                <select name="assigned_sr_customer_id" id="assigned_sr_customer_id" class="form-control select2" style="width:100%;">
                                    <option value="">All assigned SRs</option>
                                    @foreach($salesRepresentatives as $id => $name)
                                        <option value="{{ $id }}" {{ (string) request('assigned_sr_customer_id') === (string) $id ? 'selected' : '' }}>{{ $name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    @endif
                    <div class="col-md-{{ $isDsr ? '3' : '4' }}" style="padding-top:25px;">
                        <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> Apply Filter</button>
                        <a href="{{ action([\App\Http\Controllers\Distribution\CustomerController::class, 'index'], $isDsr ? ['employee_type' => 'dsr'] : []) }}" class="btn btn-default"><i class="fa fa-refresh"></i> Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <div class="box">
        <div class="box-header">
            <a href="{{ action([\App\Http\Controllers\Distribution\CustomerController::class, 'create'], $isDsr ? ['employee_type' => 'dsr'] : []) }}" class="tw-dw-btn tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-font-bold tw-text-white tw-border-none tw-rounded-full pull-right">
                <i class="fa fa-plus"></i> {{ $isDsr ? 'Add DSR' : 'Add SR' }}
            </a>
        </div>
        <div class="box-body">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>{{ $isDsr ? 'Assigned SR' : 'Assigned DSR' }}</th>
                        <th>Zone</th>
                        <th>Business Location</th>
                        @unless($isDsr)
                        <th>Email</th>
                        @endunless
                        <th>Phone</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($customers as $customer)
                    @php($territoryCustomer = $isDsr ? $customer->assignedSalesRepresentative : $customer)
                    <tr>
                        <td>{{ $customer->id }}</td>
                        <td>{{ $customer->name }}</td>
                        <td>{{ $isDsr ? (optional($customer->assignedSalesRepresentative)->name ?: 'Not assigned') : (optional($customer->deliverySalesRepresentative)->name ?: 'Not assigned') }}</td>
                        <td>{{ optional(optional($territoryCustomer)->zone)->name ?? '-' }}</td>
                        <td>{{ optional(optional(optional($territoryCustomer)->zone)->businessLocation)->name ?? '-' }}</td>
                        @unless($isDsr)
                        <td>{{ $customer->email }}</td>
                        @endunless
                        <td>{{ $customer->phone }}</td>
                        <td><span class="label label-{{ $customer->status == 'active' ? 'success' : 'danger' }}">{{ ucfirst($customer->status) }}</span></td>
                        <td>
                            <a href="{{ action([\App\Http\Controllers\Distribution\CustomerController::class, 'show'], [$customer->id]) }}" class="btn btn-xs btn-info">View</a>
                            <a href="{{ action([\App\Http\Controllers\Distribution\CustomerController::class, 'edit'], [$customer->id]) }}" class="btn btn-xs btn-primary">Edit</a>
                            @if($isDsr)
                                <a href="{{ route('distribution.payroll.show', ['customer' => $customer->id]) }}" class="btn btn-xs btn-success">Payroll</a>
                            @endif
                            @unless($isDsr)
                            <div class="btn-group">
                                <button type="button" class="btn btn-xs btn-success dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    Sales Reports <span class="caret"></span>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-right">
                                    <li>
                                        <a href="{{ route('distribution.customers.sales-report.daily', ['customer' => $customer->id, 'date' => now()->toDateString()]) }}">
                                            <i class="fa fa-eye"></i> View Daily Report
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{ route('distribution.customers.sales-report.daily', ['customer' => $customer->id, 'date' => now()->toDateString(), 'print' => 1]) }}" target="_blank">
                                            <i class="fa fa-print"></i> Print Daily Report
                                        </a>
                                    </li>
                                    <li role="separator" class="divider"></li>
                                    <li>
                                        <a href="{{ route('distribution.customers.sales-report.monthly', ['customer' => $customer->id, 'month' => now()->format('Y-m')]) }}">
                                            <i class="fa fa-eye"></i> View Monthly Report
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{ route('distribution.customers.sales-report.monthly', ['customer' => $customer->id, 'month' => now()->format('Y-m'), 'print' => 1]) }}" target="_blank">
                                            <i class="fa fa-print"></i> Print Monthly Report
                                        </a>
                                    </li>
                                </ul>
                            </div>
                            @endunless
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</section>
@endsection
