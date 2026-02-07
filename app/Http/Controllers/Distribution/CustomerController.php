<?php

namespace App\Http\Controllers\Distribution;

use App\Customer;
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
            'password' => 'required|min:8|confirmed',
            'address' => 'nullable|string',
            'status' => 'in:active,inactive',
            'contact_id' => 'nullable|exists:contacts,id',
        ]);

        $business_id = request()->session()->get('user.business_id');

        $data = [
            'business_id' => $business_id,
            'password' => bcrypt($request->password),
            'address' => $request->address,
            'status' => $request->status ?? 'active',
            'created_by' => auth()->id(),
        ];

        if ($request->filled('contact_id')) {
            $contact = \App\Contact::find($request->contact_id);

            $data['contact_id'] = $contact->id;
            $data['name'] = $contact->name;
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
    public function show($id)
    {
        $customer = Customer::with(['stocks.product', 'sales.product'])->findOrFail($id);
        // dd($customer);
        if ($customer->business_id != request()->session()->get('user.business_id')) {
            abort(403, 'Unauthorized action.');
        }

        return view('distribution.customers.show', compact('customer'));
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
        ]);

        $data = [
            'name' => $request->name,
            'phone' => $request->phone,
            'email' => $request->email,
            'address' => $request->address,
            'status' => $request->status ?? 'active',
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
