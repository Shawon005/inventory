@if(\Illuminate\Support\Facades\Schema::hasTable('mfg_product_settings') && (auth()->user()->can('manufacturing.materials.manage') || auth()->user()->can('manufacturing.components.manage')))
@php
    $mfgProduct = $product ?? $duplicate_product ?? null;
    $mfgSettings = $mfgProduct ? \Illuminate\Support\Facades\DB::table('mfg_product_settings')->where('business_id', auth()->user()->business_id)->where('product_id', $mfgProduct->id)->first() : null;
    $mfgType = old('manufacturing_type', $mfgProduct->manufacturing_type ?? request('manufacturing_type', 'normal'));
    $mfgCanEdit = $mfgType === 'normal' || auth()->user()->can($mfgType === 'raw_material' ? 'manufacturing.materials.manage' : 'manufacturing.components.manage');
@endphp
<div class="col-sm-12 mfg-product-fields" data-boms-url="{{ route('manufacturing.boms.index') }}">
    <div class="panel panel-default">
        <div class="panel-heading"><strong>Manufacturing &amp; production</strong></div>
        <div class="panel-body">
            <fieldset @disabled(!$mfgCanEdit)>
            @if($errors->any())<div class="alert alert-danger" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            <div class="row">
                <div class="col-sm-4 form-group">
                    <label for="manufacturing_type">Product type</label>
                    <select id="manufacturing_type" name="manufacturing_type" class="form-control select2">
                        <option value="normal" @selected($mfgType === 'normal')>Normal product</option>
                        @can('manufacturing.materials.manage')<option value="raw_material" @selected($mfgType === 'raw_material')>Raw material</option>@endcan
                        @can('manufacturing.components.manage')
                        <option value="component" @selected($mfgType === 'component')>Manufactured component</option>
                        <option value="finished_good" @selected($mfgType === 'finished_good')>Manufactured finished product</option>
                        @endcan
                        @if($mfgType === 'raw_material' && !auth()->user()->can('manufacturing.materials.manage'))<option value="raw_material" selected>Raw material</option>@endif
                        @if(in_array($mfgType, ['component','finished_good']) && !auth()->user()->can('manufacturing.components.manage'))<option value="{{ $mfgType }}" selected>{{ $mfgType === 'component' ? 'Manufactured component' : 'Manufactured finished product' }}</option>@endif
                    </select>
                    <small class="text-muted">The single / variable / combo selector controls the product structure.</small>
                </div>
                <div class="col-sm-4 form-group mfg-production-setting">
                    <label for="manufacturing_allow_purchase">Procurement</label>
                    <select class="form-control" name="manufacturing_allow_purchase" id="manufacturing_allow_purchase">
                        <option value="1" @selected(old('manufacturing_allow_purchase', $mfgSettings->allow_purchase ?? 1) == 1)>External purchases allowed</option>
                        <option value="0" @selected(old('manufacturing_allow_purchase', $mfgSettings->allow_purchase ?? 1) == 0)>Manufacture internally only</option>
                    </select>
                </div>
                @can('manufacturing.bom.manage')
                <div class="col-sm-4 form-group mfg-template-setting">
                    <label for="manufacturing_bom_template_id">Assign a recipe template</label>
                    <select name="manufacturing_bom_template_id" id="manufacturing_bom_template_id" class="form-control"><option value="">Keep current BOM / configure after saving</option></select>
                    <small class="text-muted">Copies an active recipe to this single output. Manage each variable output in Manufacturing Details.</small>
                </div>
                @endcan
                <div class="col-sm-12 form-group mfg-production-setting">
                    <label for="manufacturing_notes">Production settings / notes</label>
                    <textarea id="manufacturing_notes" name="manufacturing_notes" class="form-control" rows="2" maxlength="10000">{{ old('manufacturing_notes', $mfgSettings->production_notes ?? '') }}</textarea>
                    <p class="help-block">Saving a product or assigning its recipe creates no stock. Receive production, purchase stock, or record an authorized opening balance separately.</p>
                </div>
            </div>
            </fieldset>
        </div>
    </div>
</div>
@endif
