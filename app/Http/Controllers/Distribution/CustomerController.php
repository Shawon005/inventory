<?php

namespace App\Http\Controllers\Distribution;

use App\Customer;
use App\CustomerSale;
use App\Http\Controllers\Controller;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    
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

        return view('distribution.customers.create');
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
            'shift_start_time' => 'nullable|date_format:H:i',
            'shift_end_time' => 'nullable|date_format:H:i',
            'salary' => 'nullable|numeric|min:0',
            'base_salary' => 'nullable|numeric|min:0',
            'late_deduction_per_hour' => 'nullable|numeric|min:0',
            'overtime_pay_per_hour' => 'nullable|numeric|min:0',
            'attendance_location' => 'nullable|string|max:255',
        ]);

        $business_id = request()->session()->get('user.business_id');

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
      
        $this->customerRepository->create($data);

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
        $customer = Customer::with(['stocks.product'])
            ->findOrFail($id);
    
        if ($customer->business_id != request()->session()->get('user.business_id')) {
            abort(403, 'Unauthorized action.');
        }
    
        // ✅ Build sales query separately
        $salesQuery = CustomerSale::where('customer_id', $id);
    
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
    
        return view('distribution.customers.show', compact('customer', 'sales'));
    }
    public function edit($id)
    {
        $customer = Customer::findOrFail($id);

        if ($customer->business_id != request()->session()->get('user.business_id')) {
            abort(403, 'Unauthorized action.');
        }

        return view('distribution.customers.edit', compact('customer'));
    }

    public function update(Request $request, $id)
    {
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
            'shift_start_time' => 'nullable|date_format:H:i',
            'shift_end_time' => 'nullable|date_format:H:i',
            'salary' => 'nullable|numeric|min:0',
            'base_salary' => 'nullable|numeric|min:0',
            'late_deduction_per_hour' => 'nullable|numeric|min:0',
            'overtime_pay_per_hour' => 'nullable|numeric|min:0',
            'attendance_location' => 'nullable|string|max:255',
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
        ];

        if ($request->filled('password')) {
            $request->validate(['password' => 'min:8|confirmed']);
            $data['password'] = bcrypt($request->password);
        }

        $this->customerRepository->update($customer, $data);

        $output = ['success' => true, 'msg' => __('lang_v1.success')];

        return redirect()->action([\App\Http\Controllers\Distribution\CustomerController::class, 'index'])->with('status', $output);
    }
}
