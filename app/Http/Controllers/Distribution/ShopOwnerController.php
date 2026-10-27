<?php

namespace App\Http\Controllers\Distribution;

use App\Contact;
use App\Customer;
use App\CustomerSale;
use App\Http\Controllers\Controller;
use App\ShopOwner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ShopOwnerController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeView();
        $businessId = (int) $request->session()->get('user.business_id');

        $ownersQuery = ShopOwner::query()
            ->whereHas('distributor', fn ($query) => $query->where('business_id', $businessId));

        $roads = (clone $ownersQuery)
            ->whereNotNull('road_name')
            ->where('road_name', '<>', '')
            ->distinct()
            ->orderBy('road_name')
            ->pluck('road_name');

        $owners = $ownersQuery
            ->with(['phones', 'distributor'])
            ->withCount('sales')
            ->withSum('sales as sales_total', 'sale_price')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim((string) $request->input('search'));
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%")
                        ->orWhere('road_name', 'like', "%{$search}%")
                        ->orWhereHas('phones', fn ($query) => $query->where('phone', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('distributor_id'), function ($query) use ($request) {
                $query->where('distributor_id', $request->integer('distributor_id'));
            })
            ->when($request->filled('dealer_id'), function ($query) use ($request, $businessId) {
                $query->whereHas('distributor.contact', function ($query) use ($request, $businessId) {
                    $query->whereKey($request->integer('dealer_id'))
                        ->where('business_id', $businessId)
                        ->whereIn('type', ['customer', 'both'])
                        ->dealers();
                });
            })
            ->when($request->filled('road_name'), function ($query) use ($request) {
                $query->where('road_name', $request->input('road_name'));
            })
            ->when($request->filled('visit_day'), function ($query) use ($request) {
                $query->where('visit_day', $request->input('visit_day'));
            })
            ->latest('id')
            ->paginate(20)
            ->appends($request->query());

        $distributors = $this->distributors($businessId);
        $dealers = Contact::where('business_id', $businessId)
            ->whereIn('type', ['customer', 'both'])
            ->dealers()
            ->orderBy('name')
            ->pluck('name', 'id');

        return view('distribution.shop_owners.index', compact('owners', 'distributors', 'roads', 'dealers'));
    }

    public function create(Request $request)
    {
        $this->authorizeManage();
        $businessId = (int) $request->session()->get('user.business_id');
        $distributors = $this->distributors($businessId);

        return view('distribution.shop_owners.create', compact('distributors'));
    }

    public function store(Request $request)
    {
        $this->authorizeManage();
        $businessId = (int) $request->session()->get('user.business_id');
        $data = $this->validateOwner($request, $businessId);

        $owner = DB::transaction(function () use ($data) {
            $phones = $data['phones'];
            unset($data['phones']);
            $owner = ShopOwner::create($data);
            $owner->phones()->createMany(collect($phones)->map(fn ($phone) => ['phone' => $phone])->all());

            return $owner;
        });

        return redirect()->route('distribution.shop-owners.show', $owner)->with('status', [
            'success' => true,
            'msg' => 'Shop owner created successfully.',
        ]);
    }

    public function show(Request $request, $id)
    {
        $this->authorizeView();
        $businessId = (int) $request->session()->get('user.business_id');
        $owner = $this->findOwner($id, $businessId);

        $request->validate([
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'status' => ['nullable', Rule::in(['ordered', 'delivered', 'cancelled'])],
        ]);

        $allSales = CustomerSale::where('shop_owner_id', $owner->id);
        $activeSales = (clone $allSales)->whereNotIn('status', ['cancelled', 'canceled']);
        $deliveredSales = (clone $allSales)->where(function ($query) {
            $query->where('status', 'delivered')->orWhereNull('status')->orWhere('status', '');
        });

        $grossSales = (float) (clone $activeSales)->sum('sale_price');
        $deliveredAmount = (float) (clone $deliveredSales)->sum('sale_price');
        $returnAmount = (float) (clone $allSales)->sum('return_amount');
        $orderCount = (clone $allSales)->count();
        $thisMonthSales = (float) (clone $activeSales)
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->sum('sale_price');

        $stats = [
            'order_count' => $orderCount,
            'gross_sales' => $grossSales,
            'delivered_sales' => $deliveredAmount,
            'return_amount' => $returnAmount,
            'net_sales' => max(0, $deliveredAmount - $returnAmount),
            'this_month_sales' => $thisMonthSales,
            'average_order' => $orderCount > 0 ? $grossSales / $orderCount : 0,
            'sr_count' => (clone $allSales)->distinct()->count('customer_id'),
            'last_visit' => (clone $allSales)->max('created_at'),
        ];

        $salesQuery = CustomerSale::where('shop_owner_id', $owner->id)
            ->with(['customer', 'product'])
            ->when($request->filled('from_date'), fn ($query) => $query->whereDate('created_at', '>=', $request->input('from_date')))
            ->when($request->filled('to_date'), fn ($query) => $query->whereDate('created_at', '<=', $request->input('to_date')))
            ->when($request->filled('status'), function ($query) use ($request) {
                $status = $request->input('status');
                $status === 'cancelled'
                    ? $query->whereIn('status', ['cancelled', 'canceled'])
                    : $query->where('status', $status);
            })
            ->latest('created_at');

        $visits = $salesQuery->paginate(15)->appends($request->query());
        $monthlyPerformance = $this->monthlyPerformance($owner);

        return view('distribution.shop_owners.show', compact('owner', 'stats', 'visits', 'monthlyPerformance'));
    }

    public function edit(Request $request, $id)
    {
        $this->authorizeManage();
        $businessId = (int) $request->session()->get('user.business_id');
        $owner = $this->findOwner($id, $businessId);
        $distributors = $this->distributors($businessId);

        return view('distribution.shop_owners.edit', compact('owner', 'distributors'));
    }

    public function update(Request $request, $id)
    {
        $this->authorizeManage();
        $businessId = (int) $request->session()->get('user.business_id');
        $owner = $this->findOwner($id, $businessId);
        $data = $this->validateOwner($request, $businessId, $owner);

        DB::transaction(function () use ($owner, $data) {
            $phones = $data['phones'];
            unset($data['phones']);
            $owner->update($data);
            $owner->phones()->delete();
            $owner->phones()->createMany(collect($phones)->map(fn ($phone) => ['phone' => $phone])->all());
        });

        return redirect()->route('distribution.shop-owners.show', $owner)->with('status', [
            'success' => true,
            'msg' => 'Shop owner updated successfully.',
        ]);
    }

    protected function validateOwner(Request $request, int $businessId, ?ShopOwner $owner = null): array
    {
        $validated = $request->validate([
            'distributor_id' => [
                'required',
                'integer',
                Rule::exists('customers', 'id')->where(fn ($query) => $query->where('business_id', $businessId)->where('employee_type', Customer::TYPE_SR)),
            ],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('shop_owners', 'email')->ignore(optional($owner)->id)],
            'phones' => ['required', 'array', 'min:1', 'max:10'],
            'phones.*' => ['required', 'string', 'distinct', 'max:30', 'regex:/^\+?[0-9\s().-]{7,30}$/'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'address' => ['required', 'string', 'max:2000'],
            'road_name' => ['nullable', 'string', 'max:255'],
            'visit_day' => ['nullable', Rule::in(array_keys(ShopOwner::VISIT_DAYS))],
        ]);

        $validated['email'] = ($validated['email'] ?? null) ?: null;

        $validated['phones'] = collect($validated['phones'])
            ->map(fn ($phone) => trim($phone))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $validated;
    }

    protected function findOwner($id, int $businessId): ShopOwner
    {
        return ShopOwner::with(['phones', 'distributor.contact.zone.businessLocation'])
            ->whereHas('distributor', fn ($query) => $query->where('business_id', $businessId))
            ->findOrFail($id);
    }

    protected function distributors(int $businessId)
    {
        return Customer::where('business_id', $businessId)
            ->where('employee_type', Customer::TYPE_SR)
            ->orderBy('name')
            ->pluck('name', 'id');
    }

    protected function monthlyPerformance(ShopOwner $owner)
    {
        $start = now()->startOfMonth()->subMonths(5);
        $rows = CustomerSale::where('shop_owner_id', $owner->id)
            ->where('created_at', '>=', $start)
            ->get(['sale_price', 'return_amount', 'status', 'created_at']);

        return collect(range(5, 0))->map(function ($monthsAgo) use ($rows) {
            $month = now()->startOfMonth()->subMonths($monthsAgo);
            $monthRows = $rows->filter(fn ($sale) => $sale->created_at->format('Y-m') === $month->format('Y-m'));
            $gross = (float) $monthRows->whereNotIn('status', ['cancelled', 'canceled'])->sum('sale_price');
            $returns = (float) $monthRows->sum('return_amount');

            return [
                'label' => $month->format('M Y'),
                'orders' => $monthRows->count(),
                'amount' => max(0, $gross - $returns),
            ];
        });
    }

    protected function authorizeView(): void
    {
        if (! auth()->user()->can('customer.view')) {
            abort(403, 'Unauthorized action.');
        }
    }

    protected function authorizeManage(): void
    {
        if (! auth()->user()->can('customer.create') && ! auth()->user()->can('customer.update')) {
            abort(403, 'Unauthorized action.');
        }
    }
}
