<div class="product-bulk-actions" style="display: flex; flex-wrap: wrap; gap: 4px; width: 100%;">
    @can('product.delete')
        {!! Form::open([
            'url' => action([\App\Http\Controllers\ProductController::class, 'massDestroy']),
            'method' => 'post',
            'class' => 'mass_delete_form',
        ]) !!}
        {!! Form::hidden('selected_rows', null, ['class' => 'selected_rows']); !!}
        {!! Form::submit(__('lang_v1.delete_selected'), [
            'class' => 'tw-dw-btn tw-dw-btn-outline tw-dw-btn-xs tw-dw-btn-error delete-selected',
        ]) !!}
        {!! Form::close() !!}
    @endcan

    @can('product.update')
        @if(config('constants.enable_product_bulk_edit'))
            {!! Form::open([
                'url' => action([\App\Http\Controllers\ProductController::class, 'bulkEdit']),
                'method' => 'post',
                'class' => 'bulk_edit_form',
            ]) !!}
            {!! Form::hidden('selected_products', null, ['class' => 'selected_products_for_edit']); !!}
            <button type="submit" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-primary edit-selected">
                <i class="fa fa-edit"></i> {{ __('lang_v1.bulk_edit') }}
            </button>
            {!! Form::close() !!}
        @endif

        <button type="button" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-accent update_product_location" data-type="add">
            @lang('lang_v1.add_to_location')
        </button>
        <button type="button" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-neutral update_product_location" data-type="remove">
            @lang('lang_v1.remove_from_location')
        </button>
    @endcan

    {!! Form::open([
        'url' => action([\App\Http\Controllers\ProductController::class, 'massDeactivate']),
        'method' => 'post',
        'class' => 'mass_deactivate_form',
    ]) !!}
    {!! Form::hidden('selected_products', null, ['class' => 'selected_products']); !!}
    {!! Form::submit(__('lang_v1.deactivate_selected'), [
        'class' => 'tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-warning deactivate-selected',
    ]) !!}
    {!! Form::close() !!}
    @show_tooltip(__('lang_v1.deactive_product_tooltip'))

    @if($is_woocommerce)
        <button type="button" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-warning toggle_woocomerce_sync">
            @lang('lang_v1.woocommerce_sync')
        </button>
    @endif
</div>
