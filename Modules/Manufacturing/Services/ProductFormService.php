<?php

namespace Modules\Manufacturing\Services;

use App\Product;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductFormService
{
    public function __construct(private ManufacturingAccess $access, private BomService $boms) {}

    /** Check new form fields before the legacy controller uploads or replaces any files. */
    public function validateRequest(User $user, Request $request, ?Product $product = null, bool $checkFullForm = false): array
    {
        if (! $request->has('manufacturing_type')) { return []; }
        abort_unless(Schema::hasTable('mfg_product_settings'), 503, 'Install the manufacturing migrations before configuring manufacturing.');
        $data = $request->validate([
            'manufacturing_type' => ['required', Rule::in(['normal', 'raw_material', 'component', 'finished_good'])],
            'manufacturing_allow_purchase' => 'sometimes|boolean', 'manufacturing_notes' => 'nullable|string|max:10000',
            'manufacturing_bom_template_id' => 'nullable|integer|min:1',
        ]);
        $types = array_diff(array_unique([$product->manufacturing_type ?? 'normal', $data['manufacturing_type']]), ['normal']);
        foreach ($types as $type) { $this->access->authorize($user, $type === 'raw_material' ? 'materials.manage' : 'components.manage'); }
        if ($checkFullForm && $data['manufacturing_type'] !== 'normal' && (! $request->boolean('enable_stock') || ! in_array($request->input('type', $product->type ?? 'single'), ['single', 'variable'], true))) {
            throw ValidationException::withMessages(['manufacturing_type' => 'Enable stock tracking and choose a single or variable product structure for manufacturing.']);
        }
        if (! empty($data['manufacturing_bom_template_id'])) {
            $this->access->authorize($user, 'bom.manage');
            $structure = $request->input('type', $product->type ?? 'single');
            if ($structure !== 'single' || ! in_array($data['manufacturing_type'], ['component', 'finished_good'], true)) {
                throw ValidationException::withMessages(['manufacturing_bom_template_id' => 'Recipe templates require a single manufactured output. Configure variable outputs in Manufacturing Details.']);
            }
            $this->boms->snapshot($user->business_id, $data['manufacturing_bom_template_id']);
        }
        return $data;
    }

    /** Called inside the existing product transaction, after variations have been saved. */
    public function save(User $user, Product $product, Request $request): void
    {
        if (! $request->has('manufacturing_type')) { return; }
        abort_unless((int) $product->business_id === $this->access->business($user), 403);
        $data = $this->validateRequest($user, $request, $product);
        $types = array_diff(array_unique([$product->manufacturing_type ?? 'normal', $data['manufacturing_type']]), ['normal']);
        if (! $types && empty($data['manufacturing_bom_template_id'])) { return; }
        foreach ($types ?: ['normal'] as $type) { $this->access->authorize($user, $type === 'raw_material' ? 'materials.manage' : 'components.manage'); }
        DB::table('business')->where('id', $user->business_id)->lockForUpdate()->first();
        if ($data['manufacturing_type'] !== 'normal' && (! $product->enable_stock || ! in_array($product->type, ['single', 'variable'], true))) {
            throw ValidationException::withMessages(['manufacturing_type' => 'Manufacturing requires a stock-tracked single or variable product.']);
        }
        $ids = $product->variations()->pluck('id');
        if (($product->manufacturing_type ?? 'normal') !== $data['manufacturing_type'] && app(ProductReferences::class)->variationIsReferenced($ids)) {
            throw ValidationException::withMessages(['manufacturing_type' => 'This classification is referenced by a BOM. Archive the product instead of changing its manufacturing role.']);
        }
        $product->manufacturing_type = $data['manufacturing_type']; $product->save();
        DB::table('mfg_product_settings')->updateOrInsert(['business_id' => $user->business_id, 'product_id' => $product->id], [
            'allow_purchase' => $request->boolean('manufacturing_allow_purchase', true),
            'production_notes' => $data['manufacturing_notes'] ?? null, 'updated_at' => now(),
        ]);
        if (! empty($data['manufacturing_bom_template_id'])) {
            $this->access->authorize($user, 'bom.manage');
            if ($product->type !== 'single' || ! in_array($product->manufacturing_type, ['component', 'finished_good'], true) || $ids->count() !== 1) {
                throw ValidationException::withMessages(['manufacturing_bom_template_id' => 'Assign BOMs to individual variations from Manufacturing Details. Recipe templates on this form require one manufactured output.']);
            }
            $template = $this->boms->snapshot($user->business_id, $data['manufacturing_bom_template_id']);
            $payload = ['name' => $product->name, 'variation_id' => $ids->first(), 'output_quantity' => (string) $template['output_quantity'], 'items' => []];
            foreach (['labor_cost', 'machine_cost', 'overhead_cost', 'other_cost'] as $cost) { $payload[$cost] = (string) $template[$cost]; }
            foreach ($template['items'] as $item) { $payload['items'][] = ['variation_id' => $item['variation_id'], 'unit_id' => $item['unit_id'], 'quantity' => (string) $item['quantity'], 'wastage_percent' => (string) $item['wastage_percent']]; }
            $bomId = DB::table('mfg_boms')->where('business_id', $user->business_id)->where('variation_id', $ids->first())->value('id');
            $this->boms->save($user, $payload, 'product-template-'.Str::uuid(), $bomId);
        }
    }
}
