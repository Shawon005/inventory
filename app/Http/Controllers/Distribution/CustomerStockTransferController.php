<?php

namespace App\Http\Controllers\Distribution;

use App\Customer;
use App\Http\Controllers\Controller;
use App\Services\CustomerStockTransferService;
use App\Variation;
use Illuminate\Http\Request;

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
        $customers = Customer::where('business_id', $business_id)->active()->orderBy('name')->get();
        $locations = \App\BusinessLocation::where('business_id', $business_id)->get();

        return view('distribution.stock_transfers.create', compact('customers', 'locations'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
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
}
