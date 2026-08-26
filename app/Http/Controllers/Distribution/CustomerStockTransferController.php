<?php

namespace App\Http\Controllers\Distribution;

use App\Customer;
use App\Http\Controllers\Controller;
use App\Services\CustomerStockTransferService;
use App\Variation;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerStockTransferController extends Controller
{
    protected $transferService;

    public function __construct(CustomerStockTransferService $transferService)
    {
        $this->transferService = $transferService;
    }

    public function index()
    {
        $business_id = request()->session()->get('user.business_id');

        $transfers = \App\CustomerStockTransfer::whereHas('customer', function ($q) use ($business_id) {
            $q->where('business_id', $business_id);
        })
            ->with(['customer', 'product', 'createdBy'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('distribution.stock_transfers.index', compact('transfers'));
    }

    public function create()
    {
        $business_id = request()->session()->get('user.business_id');
        $customers = Customer::where('business_id', $business_id)
            ->where('employee_type', Customer::TYPE_SR)
            ->active()
            ->orderBy('name')
            ->get();
        $locations = \App\BusinessLocation::where('business_id', $business_id)->get();

        return view('distribution.stock_transfers.create', compact('customers', 'locations'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'customer_id' => [
                'required',
                Rule::exists('customers', 'id')->where(function ($query) {
                    $query->where('business_id', request()->session()->get('user.business_id'))
                        ->where('employee_type', Customer::TYPE_SR);
                }),
            ],
            'product_id' => 'required|exists:products,id',
            'variation_id' => 'nullable|exists:variations,id',
            'location_id' => 'required|exists:business_locations,id',
            'quantity' => 'required|numeric|min:0.0001',
            'transfer_price' => 'required|numeric|min:0',
        ]);

        $variationId = $request->variation_id;
        if (! $variationId) {
            $variation = Variation::where('product_id', $request->product_id)->first();
            $variationId = $variation ? $variation->id : null;
        }

        try {
            $this->transferService->transfer([
                'customer_id' => $request->customer_id,
                'product_id' => $request->product_id,
                'variation_id' => $variationId,
                'location_id' => $request->location_id,
                'quantity' => $request->quantity,
                'transfer_price' => $request->transfer_price,
                'created_by' => auth()->id(),
            ]);

            $output = ['success' => true, 'msg' => __('lang_v1.success')];
        } catch (\Exception $e) {
            $output = ['success' => false, 'msg' => $e->getMessage()];
        }

        return redirect()->back()->with('status', $output);
    }

    public function edit($id)
    {
        $business_id = request()->session()->get('user.business_id');
        
        $transfer = \App\CustomerStockTransfer::with('customer', 'product')->findOrFail($id);
        
        // Check if belongs to this business
        if ($transfer->customer->business_id != $business_id) {
            abort(403, 'Unauthorized action.');
        }
        
        $customers = Customer::where('business_id', $business_id)
            ->where('employee_type', Customer::TYPE_SR)
            ->active()
            ->orderBy('name')
            ->get();
        $locations = \App\BusinessLocation::where('business_id', $business_id)->get();
        
        return view('distribution.stock_transfers.edit', compact('transfer', 'customers', 'locations'));
    }

    public function update(Request $request, $id)
    {
        $business_id = request()->session()->get('user.business_id');
        
        $transfer = \App\CustomerStockTransfer::findOrFail($id);
        
        if ($transfer->customer->business_id != $business_id) {
            abort(403, 'Unauthorized action.');
        }
        
        $request->validate([
            'customer_id' => [
                'required',
                Rule::exists('customers', 'id')->where(function ($query) use ($business_id) {
                    $query->where('business_id', $business_id)
                        ->where('employee_type', Customer::TYPE_SR);
                }),
            ],
            'product_id' => 'required|exists:products,id',
            'variation_id' => 'nullable|exists:variations,id',
            'location_id' => 'required|exists:business_locations,id',
            'quantity' => 'required|numeric|min:0.0001',
            'transfer_price' => 'required|numeric|min:0',
        ]);
        
        $transfer->update([
            'customer_id' => $request->customer_id,
            'product_id' => $request->product_id,
            'variation_id' => $request->variation_id,
            'location_id' => $request->location_id,
            'quantity' => $request->quantity,
            'transfer_price' => $request->transfer_price,
            'total_amount' => $request->quantity * $request->transfer_price,
        ]);
        
        $output = ['success' => true, 'msg' => __('lang_v1.success')];
        return redirect()->route('distribution.stock-transfers.index')->with('status', $output);
    }

   public function destroy($id)
    {
        $business_id = request()->session()->get('user.business_id');
        
        $transfer = \App\CustomerStockTransfer::findOrFail($id);
        
        if ($transfer->customer->business_id != $business_id) {
            abort(403, 'Unauthorized action.');
        }
        
        try {
            // Reverse the stock transfer
            $this->transferService->reverseTransfer($transfer);
            
            $output = ['success' => true, 'msg' => __('lang_v1.success')];
        } catch (\Exception $e) {
            $output = ['success' => false, 'msg' => $e->getMessage()];
        }
        
        return redirect()->route('distribution.stock-transfers.index')->with('status', $output);
    }

/**
 * Reverse a stock transfer (return stock to main inventory and remove from customer).
 *
 * @throws \Exception
 */
    
}
