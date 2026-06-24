<?php

namespace App\Http\Controllers\Distribution;

use App\Customer;
use App\CustomerSale;
use App\CustomerWorkLocation;
use App\Http\Controllers\Controller;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    protected array $weekdays = ['saturday', 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
    
    // protected $customerRepository;

    public function __construct(CustomerRepositoryInterface $customerRepository )
    {
        
        $this->customerRepository = $customerRepository;
    }

    public function index()
    {
        if (! auth()->user()->can('customer.view')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');
        $customers = Customer::where('business_id', $business_id)
            ->with('createdBy')
            ->orderBy('name')
            ->get();

        return view('distribution.customers.index', compact('customers'));
    }

    public function create()
    {
       
        if (! auth()->user()->can('customer.create')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');
        $customers = Customer::where('business_id', $business_id)
            ->orderBy('name')
            ->get();

        return view('distribution.customers.create', compact('customers'));
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

        $request->validate([
            'name' => 'required_without:contact_id|string|max:255',
            'phone' => 'required_without:contact_id|string|max:50',
            'email' => 'required_without:contact_id|email|unique:customers,email',
            'password' => 'required|min:6|confirmed',
            'address' => 'nullable|string',
            'status' => 'in:active,inactive',
            'contact_id' => 'nullable|exists:contacts,id',
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
            'password' => bcrypt($request->password),
            'address' => $request->address,
            'status' => $request->status ?? 'active',
            'created_by' => auth()->id(),
            'work_latitude' => $request->work_latitude,
            'work_longitude' => $request->work_longitude,
            'allowed_radius_meters' => $request->allowed_radius_meters ?? 100,
            'shift_start_time' => $request->shift_start_time,
            'shift_end_time' => $request->shift_end_time,
            'base_salary' => $request->salary ?? $request->base_salary ?? 0,
            'late_deduction_per_hour' => $request->late_deduction_per_hour ?? 0,
            'overtime_pay_per_hour' => $request->overtime_pay_per_hour ?? 0,
            'attendance_location' => $request->attendance_location,
            'tada_bill_amount' => $request->tada_bill_amount ?? 0,
            'monthly_target_amount' => $request->monthly_target_amount ?? 0,
            'commission_percent' => $request->commission_percent ?? 0,
            'stock_owner_customer_id' => $request->filled('stock_owner_customer_id') ? $request->stock_owner_customer_id : null,
        ];

        if ($request->filled('contact_id')) {
            $contact = \App\Contact::find($request->contact_id);
           
            $data['contact_id'] = $contact->id;
            $data['name'] = $contact->name==''?$contact->supplier_business_name:$contact->name;
            $data['phone'] = $contact->mobile ?? $request->phone;
            $data['email'] = $contact->email ?? $request->email;
        } else {
            $data['name'] = $request->name;
            $data['phone'] = $request->phone;
            $data['email'] = $request->email;
        }
      
        $customer = $this->customerRepository->create($data);
        $this->syncDayLocations($customer, (array) $request->input('day_locations', []));

        $output = ['success' => true, 'msg' => __('lang_v1.success')];

        return redirect()->action([\App\Http\Controllers\Distribution\CustomerController::class, 'index'])->with('status', $output);
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
        $customer = Customer::with(['stocks.product', 'stockOwner.stocks.product'])
            ->findOrFail($id);
    
        if ($customer->business_id != request()->session()->get('user.business_id')) {
            abort(403, 'Unauthorized action.');
        }
    
        // ✅ Build sales query separately
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
    
        return view('distribution.customers.show', compact('customer', 'sales', 'stockCustomer'));
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
        $customers = Customer::where('business_id', $business_id)
            ->orderBy('name')
            ->get();

        return view('distribution.customers.edit', compact('customer', 'customers'));
    }

    public function update(Request $request, $id)
    {
        if (! auth()->user()->can('customer.create')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        $customer = Customer::findOrFail($id);

        if ($customer->business_id != request()->session()->get('user.business_id')) {
            abort(403, 'Unauthorized action.');
        }
       
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:50',
            'email' => 'required|email|unique:customers,email,'.$id,
            'address' => 'nullable|string',
            'status' => 'in:active,inactive',
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
            'email' => $request->email,
            'address' => $request->address,
            'status' => $request->status ?? 'active',
            'work_latitude' => $request->work_latitude,
            'work_longitude' => $request->work_longitude,
            'allowed_radius_meters' => $request->allowed_radius_meters ?? 100,
            'shift_start_time' => $request->shift_start_time,
            'shift_end_time' => $request->shift_end_time,
            'base_salary' => $request->salary ?? $request->base_salary ?? 0,
            'late_deduction_per_hour' => $request->late_deduction_per_hour ?? 0,
            'overtime_pay_per_hour' => $request->overtime_pay_per_hour ?? 0,
            'attendance_location' => $request->attendance_location,
            'tada_bill_amount' => $request->tada_bill_amount ?? 0,
            'monthly_target_amount' => $request->monthly_target_amount ?? 0,
            'commission_percent' => $request->commission_percent ?? 0,
            'stock_owner_customer_id' => $request->filled('stock_owner_customer_id') ? $request->stock_owner_customer_id : null,
        ];

        if ($request->filled('password')) {
            $request->validate(['password' => 'min:8|confirmed']);
            $data['password'] = bcrypt($request->password);
        }

        try {
            $this->customerRepository->update($customer, $data);
            $this->syncDayLocations($customer, (array) $request->input('day_locations', []));
        } catch (\Throwable $e) {
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

        return redirect()->action([\App\Http\Controllers\Distribution\CustomerController::class, 'index'])->with('status', $output);
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
}
