<?php

namespace Modules\B2B\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\B2B\Services\Access;
use Modules\B2B\Services\Catalog;
use Modules\B2B\Services\CustomerService;
use Modules\B2B\Services\InventoryService;
use Modules\B2B\Services\ReadService;
use Modules\B2B\Services\SalesService;

class WorkspaceController extends Controller
{
    public function __construct(private Access $access, private Catalog $catalog, private ReadService $read, private SalesService $sales) {}

    public function page(Request $request, string $screen = 'dashboard')
    {
        $titles = ['dashboard' => 'B2B dashboard', 'products' => 'B2B products', 'inventory' => 'B2B inventory', 'transfers' => 'B2B stock transfers', 'customers' => 'Business customers', 'pos' => 'B2B POS', 'sales' => 'B2B sales & invoices', 'dues' => 'Payments & dues', 'reports' => 'B2B reports', 'movements' => 'B2B stock movements'];
        abort_unless(isset($titles[$screen]), 404); $this->access->read($request->user());
        if ($screen === 'dashboard') { $this->access->authorize($request->user(), 'dashboard.view'); }
        if ($screen === 'pos') { $this->access->authorize($request->user(), 'pos.access'); }
        if ($screen === 'reports') { $this->access->authorize($request->user(), 'reports.view'); }
        $locations = $this->read->locations($request->user()); $abilities = [];
        foreach ((new DataController)->user_permissions() as $p) { $abilities[substr($p['value'], 4)] = $request->user()->can($p['value']); }
        $accounts = DB::table('accounts')->where('business_id', $request->user()->business_id)->where('is_closed', 0)->whereNull('deleted_at')->orderBy('name')->get(['id', 'name']);
        $title = $titles[$screen]; $config = ['screen' => $screen, 'base' => url('b2b'), 'abilities' => $abilities, 'locations' => $locations, 'accounts' => $accounts];
        $posCustomers = $screen === 'pos' ? $this->read->posCustomers($request->user()) : collect();
        return view('b2b::workspace', compact('screen', 'title', 'locations', 'config', 'posCustomers'));
    }

    public function data(Request $request, string $resource)
    {
        $this->access->read($request->user()); $filters = $this->filters($request);
        return match ($resource) {
            'products' => response()->json($this->read->products($request->user(), $filters, $request->boolean('pos'))),
            'customers' => response()->json($this->read->customers($request->user(), $filters, $request->boolean('all_contacts'), $request->boolean('pos'))),
            'sales' => response()->json($this->read->sales($request->user(), $filters)),
            'transfers' => response()->json($this->read->transfers($request->user(), $filters)),
            'movements' => response()->json($this->read->movements($request->user(), $filters)),
            'summary' => $this->summary($request, $filters),
            default => abort(404),
        };
    }

    private function summary(Request $request, array $filters)
    {
        abort_unless($request->user()->can('b2b.dashboard.view') || $request->user()->can('b2b.reports.view'), 403);
        return response()->json($this->read->summary($request->user(), $filters));
    }

    public function product(Request $request, int $id)
    {
        $business = $this->access->authorize($request->user(), 'products.manage'); $data = $request->validate(['is_active' => 'required|boolean']);
        return DB::transaction(function () use ($business, $id, $data) {
            DB::table('business')->where('id', $business)->lockForUpdate()->first();
            abort_unless(DB::table('products')->where('business_id', $business)->where('id', $id)->where('enable_stock', 1)->whereIn('type', ['single', 'variable'])->exists(), 404);
            DB::table('b2b_product_settings')->updateOrInsert(['business_id' => $business, 'product_id' => $id], ['is_active' => $data['is_active'], 'created_at' => now(), 'updated_at' => now()]);
            return response()->json(['product_id' => $id, 'is_active' => (bool) $data['is_active']]);
        });
    }

    public function transfer(Request $request) { return response()->json(app(InventoryService::class)->transfer($request->user(), $this->payload($request), $this->key($request)), 201); }
    public function transferDetails(Request $request, int $id)
    {
        $this->access->read($request->user());
        $transfer = DB::table('b2b_transfers')->where('id', $id)->where('business_id', $request->user()->business_id)->first(); abort_unless($transfer, 404);
        $this->access->location($request->user(), $transfer->main_location_id, false);
        $items = DB::table('transaction_sell_lines as s')->join('products as p', 'p.id', '=', 's.product_id')->join('variations as v', 'v.id', '=', 's.variation_id')->where('s.transaction_id', $transfer->out_transaction_id)->get(['s.quantity', 's.variation_id', 'p.name', 'v.name as variation_name', 'v.sub_sku']);
        return response()->json(['transfer' => $transfer, 'items' => $items]);
    }
    public function customer(Request $request) { return response()->json(app(CustomerService::class)->save($request->user(), $this->payload($request), $this->key($request)), 201); }
    public function checkout(Request $request) { return response()->json($this->sales->checkout($request->user(), $this->payload($request), $this->key($request)), 201); }
    public function payment(Request $request, int $id) { return response()->json($this->sales->recordPayment($request->user(), $id, $this->payload($request), $this->key($request)), 201); }
    public function returnItems(Request $request, int $id) { return response()->json($this->sales->returnItems($request->user(), $id, $this->payload($request), $this->key($request)), 201); }

    public function invoice(Request $request, int $id)
    {
        $this->access->read($request->user()); $sale = $this->sales->sale($request->user(), $id); $sale->paid = $this->sales->paid($id); $sale->due = $this->sales->due($sale);
        $items = DB::table('transaction_sell_lines as l')->join('b2b_sale_line_details as b', 'b.sell_line_id', '=', 'l.id')->where('l.transaction_id', $id)->get(['l.*', 'b.product_name', 'b.sku', 'b.unit_name', 'b.returned_total', 'b.price_source']);
        $payments = DB::table('transaction_payments')->where('transaction_id', $id)->orderBy('id')->get(['id', 'amount', 'method', 'is_return', 'paid_on', 'payment_ref_no']);
        $returns = DB::table('b2b_returns')->where('sale_transaction_id', $id)->orderBy('id')->get(['id', 'total', 'reason', 'created_at']);
        if ($request->expectsJson()) { return response()->json(['sale' => $sale, 'items' => $items, 'payments' => $payments, 'returns' => $returns]); }
        $company = DB::table('business')->where('id', $request->user()->business_id)->value('name'); $customer = json_decode($sale->customer_snapshot, true);
        return view('b2b::invoice', compact('sale', 'items', 'payments', 'returns', 'company', 'customer'));
    }

    public function export(Request $request)
    {
        $this->access->authorize($request->user(), 'reports.view'); abort_unless($request->user()->can('view_export_buttons'), 403); $filters = $this->filters($request);
        $query = $this->read->salesQuery($request->user(), $filters)->select('t.id', 't.invoice_no', 't.transaction_date', 't.final_total', 'b.returned_total', 'b.cost_total', 'b.returned_cost', 'b.due_date');
        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w'); fputcsv($out, ['Invoice', 'Date', 'Gross total', 'Returns', 'Cost', 'Returned cost', 'Paid net', 'Due', 'Due date']);
            foreach ($query->orderBy('t.id')->cursor() as $s) { fputcsv($out, [$s->invoice_no, $s->transaction_date, $s->final_total, $s->returned_total, $s->cost_total, $s->returned_cost, $this->sales->paid($s->id), $this->sales->due($s), $s->due_date]); }
            fclose($out);
        }, 'b2b-sales.csv', ['Content-Type' => 'text/csv']);
    }

    private function filters(Request $r): array { return $r->validate(['search' => 'nullable|string|max:191', 'location_id' => 'nullable|integer|min:1', 'contact_id' => 'nullable|integer|min:1', 'id' => 'nullable|integer|min:1', 'from' => 'nullable|date_format:Y-m-d', 'to' => ['nullable', 'date_format:Y-m-d', ...($r->filled('from') ? ['after_or_equal:from'] : [])], 'unpaid' => 'nullable|boolean', 'enrolled' => 'nullable|boolean', 'include_inactive' => 'nullable|boolean']); }
    private function payload(Request $r): array { return $r->except('_token', 'idempotency_key'); }
    private function key(Request $r): string { return (string) ($r->header('Idempotency-Key') ?: $r->input('idempotency_key', '')); }
}
