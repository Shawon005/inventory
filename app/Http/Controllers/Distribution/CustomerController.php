<?php

namespace App\Http\Controllers\Distribution;

use App\BusinessLocation;
use App\Customer;
use App\CustomerSale;
use App\CustomerWorkLocation;
use App\Http\Controllers\Controller;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use App\Zone;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    protected array $weekdays = ['saturday', 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
    
    // protected $customerRepository;

    public function __construct(CustomerRepositoryInterface $customerRepository )
    {
        
        $this->customerRepository = $customerRepository;
    }

    public function index(Request $request)
    {
        if (! auth()->user()->can('customer.view')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');
        $employeeType = $this->normalizeEmployeeType($request->input('employee_type'));
        $isDsr = $employeeType === Customer::TYPE_DSR;
        $permittedLocations = auth()->user()->permitted_locations();

        $request->validate([
            'location_id' => [
                'nullable',
                'integer',
                Rule::exists('business_locations', 'id')->where(function ($query) use ($business_id, $permittedLocations) {
                    $query->where('business_id', $business_id);
                    if ($permittedLocations !== 'all') {
                        $query->whereIn('id', (array) $permittedLocations);
                    }
                }),
            ],
            'zone_id' => [
                'nullable',
                'integer',
                Rule::exists('zones', 'id')->where(function ($query) use ($business_id, $permittedLocations) {
                    $query->where('business_id', $business_id);
                    if ($permittedLocations !== 'all') {
                        $query->whereIn('location_id', (array) $permittedLocations);
                    }
                }),
            ],
            'assigned_sr_customer_id' => [
                'nullable',
                'integer',
                Rule::exists('customers', 'id')->where(function ($query) use ($business_id) {
                    $query->where('business_id', $business_id)
                        ->where('employee_type', Customer::TYPE_SR);
                }),
            ],
        ]);

        $customersQuery = Customer::where('business_id', $business_id)
            ->where('employee_type', $employeeType)
            ->with([
                'createdBy',
                'contact.zone.businessLocation',
                'stockOwner.contact.zone.businessLocation',
                'assignedSalesRepresentative.contact.zone.businessLocation',
                'assignedSalesRepresentative.stockOwner.contact.zone.businessLocation',
                'deliverySalesRepresentative',
            ]);

        if ($isDsr) {
            $customersQuery->when($request->filled('assigned_sr_customer_id'), function ($query) use ($request) {
                $query->where('assigned_sr_customer_id', $request->input('assigned_sr_customer_id'));
            });
            if ($request->filled('zone_id') || $request->filled('location_id')) {
                $customersQuery->whereHas('assignedSalesRepresentative', function ($query) use ($request) {
                    $this->applyTerritoryFilters($query, $request->input('zone_id'), $request->input('location_id'));
                });
            }
        } else {
            $this->applyTerritoryFilters($customersQuery, $request->input('zone_id'), $request->input('location_id'));
        }

        $customers = $customersQuery
            ->orderBy('name')
            ->get();

        $locations = BusinessLocation::forDropdown($business_id, false);
        $zonesQuery = Zone::where('business_id', $business_id)
            ->with('businessLocation')
            ->when($request->filled('location_id'), fn ($query) => $query->where('location_id', $request->input('location_id')));
        if ($permittedLocations !== 'all') {
            $zonesQuery->whereIn('location_id', (array) $permittedLocations);
        }
        $zones = $zonesQuery->orderBy('name')->get()->mapWithKeys(function ($zone) {
            $locationName = optional($zone->businessLocation)->name;

            return [$zone->id => ($locationName ? $locationName.' - ' : '').$zone->name];
        });
        $salesRepresentatives = Customer::where('business_id', $business_id)
            ->where('employee_type', Customer::TYPE_SR)
            ->orderBy('name')
            ->pluck('name', 'id');

        return view('distribution.customers.index', compact(
            'customers',
            'employeeType',
            'isDsr',
            'locations',
            'zones',
            'salesRepresentatives'
        ));
    }

    public function create(Request $request)
    {
       
        if (! auth()->user()->can('customer.create')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');
        $employeeType = $this->normalizeEmployeeType($request->input('employee_type'));
        $isDsr = $employeeType === Customer::TYPE_DSR;
        $customersQuery = Customer::where('business_id', $business_id)
            ->where('employee_type', Customer::TYPE_SR)
            ->orderBy('name');
        if ($isDsr) {
            $customersQuery->whereDoesntHave('deliverySalesRepresentative');
        }
        $customers = $customersQuery->get();

        return view('distribution.customers.create', compact('customers', 'employeeType', 'isDsr'));
    }

    // public function store(Request $request)
    // {
    //     if (! auth()->user()->can('customer.create')) {
    //         abort(403, 'Unauthorized action.');
    //     }

    //     $request->validate([
    //         'name' => 'required|string|max:255',
    //         'phone' => 'required|string|max:50',
    //         'email' => 'required|email|unique:customers,email',
    //         'password' => 'required|min:8|confirmed',
    //         'address' => 'nullable|string',
    //         'status' => 'in:active,inactive',
    //     ]);

    //     $business_id = request()->session()->get('user.business_id');

    //     $this->customerRepository->create([
    //         'business_id' => $business_id,
    //         'name' => $request->name,
    //         'phone' => $request->phone,
    //         'email' => $request->email,
    //         'password' => bcrypt($request->password),
    //         'address' => $request->address,
    //         'status' => $request->status ?? 'active',
    //         'created_by' => auth()->id(),
    //     ]);

    //     $output = ['success' => true, 'msg' => __('lang_v1.success')];

    //     return redirect()->action([\App\Http\Controllers\Distribution\CustomerController::class, 'index'])->with('status', $output);
    // }

    public function store(Request $request)
    {
        if (! auth()->user()->can('customer.create')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');
        $employeeType = $request->input('employee_type', Customer::TYPE_SR);
        $isDsr = $employeeType === Customer::TYPE_DSR;

        $request->validate([
            'employee_type' => ['required', Rule::in([Customer::TYPE_SR, Customer::TYPE_DSR])],
            'name' => $isDsr ? 'required|string|max:255' : 'required_without:contact_id|string|max:255',
            'phone' => $isDsr ? 'nullable|string|max:50' : 'required_without:contact_id|string|max:50',
            'email' => $isDsr ? 'nullable' : 'required_without:contact_id|email|unique:customers,email',
            'password' => $isDsr ? 'nullable' : 'required|min:6|confirmed',
            'address' => 'nullable|string',
            'status' => 'in:active,inactive',
            'contact_id' => [
                'nullable',
                Rule::exists('contacts', 'id')->where(function ($query) use ($business_id) {
                    $query->where('business_id', $business_id)
                        ->where('is_dealer', true)
                        ->whereIn('type', ['customer', 'both'])
                        ->whereNull('deleted_at');
                }),
            ],
            'profile_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'work_latitude' => 'nullable|numeric|between:-90,90',
            'work_longitude' => 'nullable|numeric|between:-180,180',
            'allowed_radius_meters' => 'nullable|integer|min:10|max:5000',
            'shift_start_time' => ['nullable', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'shift_end_time' => ['nullable', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'salary' => 'nullable|numeric|min:0',
            'base_salary' => 'nullable|numeric|min:0',
            'late_deduction_per_hour' => 'nullable|numeric|min:0',
            'overtime_pay_per_hour' => 'nullable|numeric|min:0',
            'attendance_location' => 'nullable|string|max:255',
            'tada_bill_amount' => 'nullable|numeric|min:0',
            'monthly_target_amount' => 'nullable|numeric|min:0',
            'commission_percent' => 'nullable|numeric|min:0|max:100',
            'assigned_sr_customer_id' => $isDsr ? [
                'required',
                Rule::exists('customers', 'id')->where(function ($query) use ($business_id) {
                    $query->where('business_id', $business_id)
                        ->where('employee_type', Customer::TYPE_SR);
                }),
                Rule::unique('customers', 'assigned_sr_customer_id'),
            ] : ['nullable'],
            'stock_owner_customer_id' => [
                'nullable',
                Rule::exists('customers', 'id')->where(function ($query) use ($business_id) {
                    $query->where('business_id', $business_id);
                }),
            ],
            'day_locations' => 'nullable|array',
            'day_locations.*.location_name' => 'nullable|string|max:255',
            'day_locations.*.latitude' => 'nullable|numeric|between:-90,90',
            'day_locations.*.longitude' => 'nullable|numeric|between:-180,180',
            'day_locations.*.allowed_radius_meters' => 'nullable|integer|min:10|max:5000',
        ]);

        $data = [
            'business_id' => $business_id,
            'employee_type' => $employeeType,
            'assigned_sr_customer_id' => $isDsr ? $request->assigned_sr_customer_id : null,
            'password' => $isDsr ? null : bcrypt($request->password),
            'address' => $request->address,
            'status' => $request->status ?? 'active',
            'created_by' => auth()->id(),
            'work_latitude' => $employeeType === Customer::TYPE_DSR ? null : $request->work_latitude,
            'work_longitude' => $employeeType === Customer::TYPE_DSR ? null : $request->work_longitude,
            'allowed_radius_meters' => $employeeType === Customer::TYPE_DSR ? 100 : ($request->allowed_radius_meters ?? 100),
            'shift_start_time' => $employeeType === Customer::TYPE_DSR ? null : $request->shift_start_time,
            'shift_end_time' => $employeeType === Customer::TYPE_DSR ? null : $request->shift_end_time,
            'base_salary' => $request->salary ?? $request->base_salary ?? 0,
            'late_deduction_per_hour' => $employeeType === Customer::TYPE_DSR ? 0 : ($request->late_deduction_per_hour ?? 0),
            'overtime_pay_per_hour' => $employeeType === Customer::TYPE_DSR ? 0 : ($request->overtime_pay_per_hour ?? 0),
            'attendance_location' => $employeeType === Customer::TYPE_DSR ? null : $request->attendance_location,
            'tada_bill_amount' => $employeeType === Customer::TYPE_DSR ? 0 : ($request->tada_bill_amount ?? 0),
            'monthly_target_amount' => $employeeType === Customer::TYPE_DSR ? 0 : ($request->monthly_target_amount ?? 0),
            'commission_percent' => $employeeType === Customer::TYPE_DSR ? 0 : ($request->commission_percent ?? 0),
            'stock_owner_customer_id' => ! $isDsr && $request->filled('stock_owner_customer_id') ? $request->stock_owner_customer_id : null,
        ];

        if (! $isDsr && $request->filled('contact_id')) {
            $contact = \App\Contact::where('business_id', $business_id)
                ->dealers()
                ->findOrFail($request->contact_id);
           
            $data['contact_id'] = $contact->id;
            $data['name'] = $contact->name==''?$contact->supplier_business_name:$contact->name;
            $data['phone'] = $contact->mobile ?? $request->phone;
            $data['email'] = $contact->email ?? $request->email;
        } else {
            $data['name'] = $request->name;
            $data['phone'] = $request->phone;
            $data['email'] = $isDsr ? null : $request->email;
        }

        $newProfileImage = null;
        if ($request->hasFile('profile_image')) {
            $newProfileImage = $request->file('profile_image')->store('customer_profiles', 'public');
            $data['profile_image'] = $newProfileImage;
        }

        try {
            DB::beginTransaction();
            $customer = $this->customerRepository->create($data);
            if ($customer->uses_attendance) {
                $this->syncDayLocations($customer, (array) $request->input('day_locations', []));
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            if ($newProfileImage) {
                Storage::disk('public')->delete($newProfileImage);
            }

            \Log::error('Distribution customer creation failed', ['message' => $e->getMessage()]);

            return redirect()->back()->withInput()->with('status', [
                'success' => false,
                'msg' => 'Customer creation failed: '.$e->getMessage(),
            ]);
        }

        $output = ['success' => true, 'msg' => __('lang_v1.success')];

        return redirect()->action(
            [\App\Http\Controllers\Distribution\CustomerController::class, 'index'],
            $employeeType === Customer::TYPE_DSR ? ['employee_type' => Customer::TYPE_DSR] : []
        )->with('status', $output);
    }
    // public function show($id)
    // {
    //     $customer = Customer::with(['stocks.product', 'sales.product'])->findOrFail($id);
    //     // dd($customer);
    //     if ($customer->business_id != request()->session()->get('user.business_id')) {
    //         abort(403, 'Unauthorized action.');
    //     }

    //     return view('distribution.customers.show', compact('customer'));
    // }
    public function show(Request $request, $id)
    {
        $customer = Customer::with([
            'contact.zone.businessLocation',
            'stockOwner.contact.zone.businessLocation',
            'createdBy',
            'assignedSalesRepresentative',
            'deliverySalesRepresentative',
        ])
            ->findOrFail($id);
    
        if ($customer->business_id != request()->session()->get('user.business_id')) {
            abort(403, 'Unauthorized action.');
        }
    
        // ✅ Build sales query separately
        $isDsr = $customer->employee_type === Customer::TYPE_DSR;
        if ($isDsr) {
            return view('distribution.customers.show', compact('customer', 'isDsr'));
        }

        $salesQuery = CustomerSale::where('customer_id', $id);
        $stockCustomer = Customer::with(['stocks.product'])->findOrFail($customer->resolved_stock_owner_id);
    
        // ✅ Date filter
        if ($request->from_date && $request->to_date) {
            $salesQuery->whereBetween('created_at', [
                $request->from_date . ' 00:00:00',
                $request->to_date . ' 23:59:59'
            ]);
        }
    
        $sales = $salesQuery
            ->with('product')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        $allSalesQuery = CustomerSale::where('customer_id', $customer->id);
        $stockValue = $stockCustomer->stocks->sum(function ($stock) {
            return (float) $stock->quantity * (float) $stock->purchase_price;
        });
        $salesCount = (clone $allSalesQuery)->count();
        $totalSales = (float) (clone $allSalesQuery)->sum('sale_price');
        $monthlySales = (float) CustomerSale::where('customer_id', $customer->id)
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->sum('sale_price');
        $targetAmount = (float) ($customer->monthly_target_amount ?? 0);
        $profileStats = [
            'sales_count' => $salesCount,
            'total_sales' => $totalSales,
            'stock_items' => $stockCustomer->stocks->where('quantity', '>', 0)->count(),
            'stock_value' => $stockValue,
            'achievement_percentage' => $targetAmount > 0 ? min(($monthlySales / $targetAmount) * 100, 100) : 0,
        ];
    
        return view('distribution.customers.show', compact('customer', 'sales', 'stockCustomer', 'profileStats', 'isDsr'));
    }

    public function dailySalesReport(Request $request, $id)
    {
        $request->validate([
            'date' => 'nullable|date',
        ]);

        $customer = $this->findReportCustomer($id);
        $selectedDate = Carbon::parse($request->input('date', now()->toDateString()));
        $sales = $this->salesForPeriod($customer, $selectedDate->copy()->startOfDay(), $selectedDate->copy()->endOfDay());

        $rows = $sales->map(function ($sale) use ($customer) {
            $amounts = $this->saleStatusAmounts($sale);

            return array_merge([
                'shop_name' => $sale->sold_to ?: '-',
                'location' => $sale->resaler_address ?: ($customer->attendance_location ?: $customer->address),
                'bill_no' => 'CS-'.str_pad((string) $sale->id, 6, '0', STR_PAD_LEFT),
            ], $amounts);
        })->values();

        $monthlyTarget = (float) ($customer->monthly_target_amount ?? 0);
        $targetAmount = $selectedDate->daysInMonth > 0 ? $monthlyTarget / $selectedDate->daysInMonth : 0;

        return $this->renderSalesReport($request, $customer, 'daily', $selectedDate, $rows, $targetAmount);
    }

    public function monthlySalesReport(Request $request, $id)
    {
        $request->validate([
            'month' => 'nullable|date_format:Y-m',
        ]);

        $customer = $this->findReportCustomer($id);
        $selectedMonth = Carbon::createFromFormat('Y-m-d', $request->input('month', now()->format('Y-m')).'-01');
        $sales = $this->salesForPeriod($customer, $selectedMonth->copy()->startOfMonth(), $selectedMonth->copy()->endOfMonth());

        $rows = $sales->groupBy(function ($sale) {
            return $sale->created_at->toDateString();
        })->map(function ($daySales, $date) use ($customer) {
            $areas = $daySales->pluck('resaler_address')->filter()->unique()->implode(', ');
            $amounts = [
                'order_amount' => 0,
                'delivery_amount' => 0,
                'return_amount' => 0,
            ];

            foreach ($daySales as $sale) {
                $saleAmounts = $this->saleStatusAmounts($sale);
                foreach ($amounts as $key => $value) {
                    $amounts[$key] += $saleAmounts[$key];
                }
            }

            return array_merge([
                'date' => Carbon::parse($date),
                'location' => $areas ?: ($customer->attendance_location ?: $customer->address),
                'due_products' => '',
            ], $amounts);
        })->sortBy('date')->values();

        return $this->renderSalesReport(
            $request,
            $customer,
            'monthly',
            $selectedMonth,
            $rows,
            (float) ($customer->monthly_target_amount ?? 0)
        );
    }
    public function edit($id)
    {
        if (! auth()->user()->can('customer.create')) {
            abort(403, 'Unauthorized action.');
        }

        $customer = Customer::findOrFail($id);

        if ($customer->business_id != request()->session()->get('user.business_id')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');
        $isDsr = $customer->employee_type === Customer::TYPE_DSR;
        $customersQuery = Customer::where('business_id', $business_id)
            ->where('employee_type', Customer::TYPE_SR)
            ->orderBy('name');
        if ($isDsr) {
            $customersQuery->where(function ($query) use ($customer) {
                $query->whereDoesntHave('deliverySalesRepresentative')
                    ->orWhere('id', $customer->assigned_sr_customer_id);
            });
        }
        $customers = $customersQuery->get();

        return view('distribution.customers.edit', compact('customer', 'customers', 'isDsr'));
    }

    public function update(Request $request, $id)
    {
        if (! auth()->user()->can('customer.create')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        $customer = Customer::findOrFail($id);
        $isDsr = $customer->employee_type === Customer::TYPE_DSR;

        if ($customer->business_id != request()->session()->get('user.business_id')) {
            abort(403, 'Unauthorized action.');
        }
       
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => $isDsr ? 'nullable|string|max:50' : 'required|string|max:50',
            'email' => $isDsr ? 'nullable' : 'required|email|unique:customers,email,'.$id,
            'address' => 'nullable|string',
            'status' => 'in:active,inactive',
            'profile_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'work_latitude' => 'nullable|numeric|between:-90,90',
            'work_longitude' => 'nullable|numeric|between:-180,180',
            'allowed_radius_meters' => 'nullable|integer|min:10|max:5000',
            'shift_start_time' => ['nullable', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'shift_end_time' => ['nullable', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'salary' => 'nullable|numeric|min:0',
            'base_salary' => 'nullable|numeric|min:0',
            'late_deduction_per_hour' => 'nullable|numeric|min:0',
            'overtime_pay_per_hour' => 'nullable|numeric|min:0',
            'attendance_location' => 'nullable|string|max:255',
            'tada_bill_amount' => 'nullable|numeric|min:0',
            'monthly_target_amount' => 'nullable|numeric|min:0',
            'commission_percent' => 'nullable|numeric|min:0|max:100',
            'assigned_sr_customer_id' => $isDsr ? [
                'required',
                Rule::exists('customers', 'id')->where(function ($query) use ($business_id) {
                    $query->where('business_id', $business_id)
                        ->where('employee_type', Customer::TYPE_SR);
                }),
                Rule::unique('customers', 'assigned_sr_customer_id')->ignore($id),
            ] : ['nullable'],
            'stock_owner_customer_id' => [
                'nullable',
                Rule::exists('customers', 'id')->where(function ($query) use ($business_id) {
                    $query->where('business_id', $business_id);
                }),
            ],
            'day_locations' => 'nullable|array',
            'day_locations.*.location_name' => 'nullable|string|max:255',
            'day_locations.*.latitude' => 'nullable|numeric|between:-90,90',
            'day_locations.*.longitude' => 'nullable|numeric|between:-180,180',
            'day_locations.*.allowed_radius_meters' => 'nullable|integer|min:10|max:5000',
        ]);

        $data = [
            'name' => $request->name,
            'phone' => $request->phone,
            'email' => $isDsr ? null : $request->email,
            'password' => $isDsr ? null : $customer->password,
            'contact_id' => $isDsr ? null : $customer->contact_id,
            'assigned_sr_customer_id' => $isDsr ? $request->assigned_sr_customer_id : null,
            'address' => $request->address,
            'status' => $request->status ?? 'active',
            'work_latitude' => $isDsr ? null : $request->work_latitude,
            'work_longitude' => $isDsr ? null : $request->work_longitude,
            'allowed_radius_meters' => $isDsr ? 100 : ($request->allowed_radius_meters ?? 100),
            'shift_start_time' => $isDsr ? null : $request->shift_start_time,
            'shift_end_time' => $isDsr ? null : $request->shift_end_time,
            'base_salary' => $request->salary ?? $request->base_salary ?? 0,
            'late_deduction_per_hour' => $isDsr ? 0 : ($request->late_deduction_per_hour ?? 0),
            'overtime_pay_per_hour' => $isDsr ? 0 : ($request->overtime_pay_per_hour ?? 0),
            'attendance_location' => $isDsr ? null : $request->attendance_location,
            'tada_bill_amount' => $isDsr ? 0 : ($request->tada_bill_amount ?? 0),
            'monthly_target_amount' => $isDsr ? 0 : ($request->monthly_target_amount ?? 0),
            'commission_percent' => $isDsr ? 0 : ($request->commission_percent ?? 0),
            'stock_owner_customer_id' => ! $isDsr && $request->filled('stock_owner_customer_id') ? $request->stock_owner_customer_id : null,
        ];

        if (! $isDsr && $request->filled('password')) {
            $request->validate(['password' => 'min:8|confirmed']);
            $data['password'] = bcrypt($request->password);
        }

        $newProfileImage = null;
        $oldProfileImage = $customer->profile_image;
        if ($request->hasFile('profile_image')) {
            $newProfileImage = $request->file('profile_image')->store('customer_profiles', 'public');
            $data['profile_image'] = $newProfileImage;
        }

        try {
            DB::beginTransaction();
            $this->customerRepository->update($customer, $data);
            if ($isDsr) {
                $customer->tokens()->delete();
            }
            if ($customer->uses_attendance) {
                $this->syncDayLocations($customer, (array) $request->input('day_locations', []));
            } else {
                $customer->workLocations()->delete();
            }
            DB::commit();

            if ($newProfileImage && $oldProfileImage) {
                Storage::disk('public')->delete($oldProfileImage);
            }
        } catch (\Throwable $e) {
            DB::rollBack();

            if ($newProfileImage) {
                Storage::disk('public')->delete($newProfileImage);
            }

            \Log::error('Distribution customer update failed', [
                'customer_id' => $customer->id,
                'message' => $e->getMessage(),
            ]);

            return redirect()->back()->withInput()->with('status', [
                'success' => false,
                'msg' => 'Update failed: '.$e->getMessage(),
            ]);
        }

        $output = ['success' => true, 'msg' => __('lang_v1.success')];

        return redirect()->action(
            [\App\Http\Controllers\Distribution\CustomerController::class, 'index'],
            $isDsr ? ['employee_type' => Customer::TYPE_DSR] : []
        )->with('status', $output);
    }

    public function updateProfileImage(Request $request, $id)
    {
        if (! auth()->user()->can('customer.update') && ! auth()->user()->can('customer.create')) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'profile_image' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $businessId = $request->session()->get('user.business_id');
        $customer = Customer::where('business_id', $businessId)->findOrFail($id);
        $newProfileImage = $request->file('profile_image')->store('customer_profiles', 'public');
        $oldProfileImage = $customer->profile_image;

        try {
            $customer->update(['profile_image' => $newProfileImage]);

            if ($oldProfileImage) {
                Storage::disk('public')->delete($oldProfileImage);
            }
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($newProfileImage);
            throw $e;
        }

        return redirect()->back()->with('status', [
            'success' => true,
            'msg' => 'Profile image updated successfully.',
        ]);
    }

    protected function syncDayLocations(Customer $customer, array $dayLocations): void
    {
        foreach ($this->weekdays as $weekday) {
            $row = $dayLocations[$weekday] ?? [];
            $lat = $row['latitude'] ?? null;
            $lng = $row['longitude'] ?? null;

            if ($lat === null || $lat === '' || $lng === null || $lng === '') {
                CustomerWorkLocation::where('customer_id', $customer->id)->where('weekday', $weekday)->delete();
                continue;
            }

            CustomerWorkLocation::updateOrCreate(
                ['customer_id' => $customer->id, 'weekday' => $weekday],
                [
                    'location_name' => $row['location_name'] ?? null,
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'allowed_radius_meters' => $row['allowed_radius_meters'] ?? 100,
                    'is_active' => true,
                ]
            );
        }
    }

    protected function normalizeEmployeeType($employeeType): string
    {
        return $employeeType === Customer::TYPE_DSR ? Customer::TYPE_DSR : Customer::TYPE_SR;
    }

    protected function applyTerritoryFilters($query, $zoneId = null, $locationId = null): void
    {
        if (empty($zoneId) && empty($locationId)) {
            return;
        }

        $zoneFilter = function ($query) use ($zoneId, $locationId) {
            if (! empty($zoneId)) {
                $query->where('zones.id', $zoneId);
            }
            if (! empty($locationId)) {
                $query->where('zones.location_id', $locationId);
            }
        };

        $query->where(function ($query) use ($zoneFilter) {
            $query->whereHas('contact.zone', $zoneFilter)
                ->orWhereHas('stockOwner.contact.zone', $zoneFilter);
        });
    }

    protected function findReportCustomer($id): Customer
    {
        if (! auth()->user()->can('customer.view')) {
            abort(403, 'Unauthorized action.');
        }

        $businessId = request()->session()->get('user.business_id');

        return Customer::with(['business', 'stockOwner'])
            ->where('business_id', $businessId)
            ->where('employee_type', Customer::TYPE_SR)
            ->findOrFail($id);
    }

    protected function salesForPeriod(Customer $customer, Carbon $start, Carbon $end)
    {
        return CustomerSale::where('customer_id', $customer->id)
            ->whereBetween('created_at', [$start->toDateTimeString(), $end->toDateTimeString()])
            ->orderBy('created_at')
            ->get();
    }

    protected function saleStatusAmounts(CustomerSale $sale): array
    {
        $status = strtolower((string) $sale->status);
        $amount = (float) $sale->sale_price;
        $isReturned = in_array($status, ['cancelled', 'canceled'], true);
        $isDelivered = $status === 'delivered' || $status === '';

        return [
            'order_amount' => $isReturned ? 0 : $amount,
            'delivery_amount' => $isDelivered ? $amount : 0,
            'return_amount' => $isReturned ? $amount : 0,
        ];
    }

    protected function renderSalesReport(Request $request, Customer $customer, string $reportType, Carbon $period, $rows, float $targetAmount)
    {
        $totals = [
            'order_amount' => (float) $rows->sum('order_amount'),
            'delivery_amount' => (float) $rows->sum('delivery_amount'),
            'return_amount' => (float) $rows->sum('return_amount'),
        ];
        $achievedAmount = $totals['order_amount'];
        $achievementPercentage = $targetAmount > 0 ? ($achievedAmount / $targetAmount) * 100 : 0;

        return view('distribution.customers.sales_report', [
            'customer' => $customer,
            'reportType' => $reportType,
            'period' => $period,
            'rows' => $rows,
            'targetAmount' => $targetAmount,
            'achievedAmount' => $achievedAmount,
            'achievementPercentage' => $achievementPercentage,
            'totals' => $totals,
            'autoPrint' => $request->boolean('print'),
        ]);
    }
}
