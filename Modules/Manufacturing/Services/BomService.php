<?php

namespace Modules\Manufacturing\Services;

use App\Support\Decimal as D;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class BomService
{
    public function __construct(private ManufacturingAccess $access, private ManufacturingCatalog $catalog, private PostingService $postings) {}

    public function save(User $user, array $data, string $key, ?int $bomId = null): array
    {
        $businessId = $this->access->authorize($user, 'bom.manage');
        Validator::make($data, [
            'name' => 'required|string|max:191', 'variation_id' => 'required|integer|min:1',
            'output_quantity' => 'required', 'items' => 'required|array|min:1|max:200',
            'items.*.variation_id' => 'required|integer|min:1|distinct', 'items.*.unit_id' => 'required|integer|min:1',
            'items.*.quantity' => 'required',
        ])->validate();
        return $this->postings->run($user, 'bom.save', $key, ['bom_id' => $bomId, 'data' => $data], function () use ($user, $businessId, $data, $bomId) {
            $output = $this->catalog->variation($businessId, $data['variation_id'], true);
            $outputQuantity = D::positive(D::value($data['output_quantity']), 'output_quantity');
            if (! $output->allow_decimal && D::cmp($outputQuantity, bcadd($outputQuantity, '0', 0)) !== 0) {
                throw ValidationException::withMessages(['output_quantity' => 'The output unit does not allow fractional quantities.']);
            }
            $items = [];
            foreach ($data['items'] as $input) {
                $variation = $this->catalog->variation($businessId, $input['variation_id']);
                $quantity = D::positive(D::value($input['quantity']));
                $unit = DB::table('units')->where('id', $input['unit_id'])->where('business_id', $businessId)->first();
                if ($unit && ! $unit->allow_decimal && D::cmp($quantity, bcadd($quantity, '0', 0)) !== 0) {
                    throw ValidationException::withMessages(['items' => 'Fractional quantities are not allowed in this input unit.']);
                }
                $waste = D::positive(D::value($input['wastage_percent'] ?? '0'), 'wastage_percent', true);
                if (D::cmp($waste, '100') > 0) {
                    throw ValidationException::withMessages(['wastage_percent' => 'Expected wastage must not exceed 100%.']);
                }
                $multiplier = $this->catalog->multiplier($businessId, $input['unit_id'], $variation->unit_id);
                $base = D::value(bcmul(bcmul($quantity, $multiplier, 12), bcadd('1', bcdiv($waste, '100', 12), 12), 12));
                D::positive($base, 'items');
                $items[] = ['variation_id' => $variation->id, 'unit_id' => $input['unit_id'], 'quantity' => $quantity,
                    'unit_multiplier' => $multiplier, 'wastage_percent' => $waste, 'base_quantity' => $base];
            }
            if ($bomId) {
                $bom = DB::table('mfg_boms')->where('business_id', $businessId)->where('id', $bomId)->lockForUpdate()->first();
                abort_unless($bom, 404);
                if ((int) $bom->variation_id !== (int) $data['variation_id']) {
                    throw ValidationException::withMessages(['variation_id' => 'A BOM revision cannot change its output variation.']);
                }
                DB::table('mfg_boms')->where('id', $bomId)->update(['name' => $data['name'], 'is_active' => true, 'updated_at' => now()]);
            } else {
                if (DB::table('mfg_boms')->where('business_id', $businessId)->where('variation_id', $data['variation_id'])->exists()) {
                    throw ValidationException::withMessages(['variation_id' => 'This output already has a BOM. Create a new revision of it.']);
                }
                $bomId = DB::table('mfg_boms')->insertGetId(['business_id' => $businessId, 'variation_id' => $data['variation_id'], 'name' => $data['name'], 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
            }
            $version = (int) DB::table('mfg_bom_versions')->where('bom_id', $bomId)->max('version') + 1;
            DB::table('mfg_bom_versions')->where('bom_id', $bomId)->update(['is_active' => false]);
            $costs = [];
            foreach (['labor_cost', 'machine_cost', 'overhead_cost', 'other_cost'] as $field) {
                $costs[$field] = D::positive(D::value($data[$field] ?? '0'), $field, true);
            }
            $versionId = DB::table('mfg_bom_versions')->insertGetId($costs + [
                'bom_id' => $bomId, 'version' => $version, 'output_quantity' => $outputQuantity,
                'is_active' => true, 'created_by' => $user->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($items as $item) { DB::table('mfg_bom_items')->insert($item + ['bom_version_id' => $versionId]); }
            $this->assertAcyclic($businessId);
            return ['bom_id' => $bomId, 'bom_version_id' => $versionId, 'version' => $version];
        });
    }

    public function setActive(User $user, int $id, bool $active): void
    {
        $businessId = $this->access->authorize($user, 'bom.manage');
        DB::transaction(function () use ($businessId, $id, $active) {
            DB::table('business')->where('id', $businessId)->lockForUpdate()->first();
            abort_unless(DB::table('mfg_boms')->where('business_id', $businessId)->where('id', $id)->exists(), 404);
            DB::table('mfg_boms')->where('id', $id)->update(['is_active' => $active, 'updated_at' => now()]);
            if ($active) { $this->assertAcyclic($businessId); }
        }, 3);
    }

    public function snapshot(int $businessId, int $versionId, bool $requireActive = true): array
    {
        $q = DB::table('mfg_bom_versions as v')->join('mfg_boms as b', 'b.id', '=', 'v.bom_id')->where('b.business_id', $businessId)->where('v.id', $versionId);
        if ($requireActive) { $q->where('b.is_active', 1)->where('v.is_active', 1); }
        $row = $q->select('v.*', 'b.variation_id', 'b.name')->first();
        abort_unless($row, 404);
        $items = DB::table('mfg_bom_items')->where('bom_version_id', $versionId)->orderBy('variation_id')->get()->map(function ($item) use ($businessId) {
            $v = $this->catalog->variation($businessId, $item->variation_id);
            return (array) $item + ['product_id' => $v->product_id, 'name' => $v->name, 'allow_decimal' => (bool) $v->allow_decimal, 'estimated_unit_cost' => D::value((string) $v->dpp_inc_tax)];
        })->all();
        return (array) $row + ['items' => $items];
    }

    public function requirements(array $snapshot, string $quantity): array
    {
        D::positive($quantity);
        $cost = '0.0000'; $materials = [];
        foreach ($snapshot['items'] as $item) {
            $required = $this->catalog->quantity((string) $item['base_quantity'], $quantity, (string) $snapshot['output_quantity'], $item['allow_decimal']);
            $cost = D::add($cost, D::mul($required, $item['estimated_unit_cost']));
            $materials[] = ['variation_id' => $item['variation_id'], 'product_id' => $item['product_id'], 'name' => $item['name'], 'quantity' => $required];
        }
        $expenses = [];
        foreach (['labor_cost', 'machine_cost', 'overhead_cost', 'other_cost'] as $field) {
            $expenses[$field] = $this->catalog->quantity((string) $snapshot[$field], $quantity, (string) $snapshot['output_quantity']);
            $cost = D::add($cost, $expenses[$field]);
        }
        return ['materials' => $materials, 'expenses' => $expenses, 'expected_cost' => $cost];
    }

    public function explode(int $businessId, int $versionId, string $quantity, array $path = []): array
    {
        $snapshot = $this->snapshot($businessId, $versionId);
        if (in_array($snapshot['variation_id'], $path, true) || count($path) >= 30) {
            throw ValidationException::withMessages(['bom' => 'Circular or excessively deep BOM.']);
        }
        $path[] = $snapshot['variation_id']; $leaves = [];
        foreach ($this->requirements($snapshot, $quantity)['materials'] as $material) {
            $child = DB::table('mfg_boms as b')->join('mfg_bom_versions as v', 'v.bom_id', '=', 'b.id')->where('b.business_id', $businessId)->where('b.variation_id', $material['variation_id'])->where('b.is_active', 1)->where('v.is_active', 1)->value('v.id');
            $parts = $child ? $this->explode($businessId, $child, $material['quantity'], $path) : [$material];
            foreach ($parts as $part) {
                $id = $part['variation_id'];
                if (isset($leaves[$id])) { $leaves[$id]['quantity'] = D::add($leaves[$id]['quantity'], $part['quantity']); }
                else { $leaves[$id] = $part; }
            }
        }
        return array_values($leaves);
    }

    private function assertAcyclic(int $businessId): void
    {
        $edges = DB::table('mfg_boms as b')->join('mfg_bom_versions as v', 'v.bom_id', '=', 'b.id')->join('mfg_bom_items as i', 'i.bom_version_id', '=', 'v.id')
            ->where('b.business_id', $businessId)->where('b.is_active', 1)->where('v.is_active', 1)->get(['b.variation_id as output', 'i.variation_id as input']);
        $graph = []; foreach ($edges as $edge) { $graph[$edge->output][] = $edge->input; }
        $visited = []; $visiting = [];
        $visit = function ($id, int $depth = 0) use (&$visit, &$visited, &$visiting, $graph) {
            if (isset($visiting[$id]) || $depth > 30) { throw ValidationException::withMessages(['items' => 'A BOM cannot contain circular dependencies or exceed 30 levels.']); }
            if (isset($visited[$id])) { return; }
            $visiting[$id] = true;
            foreach ($graph[$id] ?? [] as $child) { $visit($child, $depth + 1); }
            unset($visiting[$id]); $visited[$id] = true;
        };
        foreach (array_keys($graph) as $id) { $visit($id); }
    }
}
