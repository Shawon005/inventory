@php
    $phoneValues = old('phones', isset($owner) ? $owner->phones->pluck('phone')->all() : ['']);
@endphp

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label for="name">Shop / Owner Name <span class="text-danger">*</span></label>
            <input type="text" name="name" id="name" class="form-control" maxlength="255" required value="{{ old('name', $owner->name ?? '') }}" placeholder="e.g. Rahim General Store">
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label for="distributor_id">Registered by SR <span class="text-danger">*</span></label>
            <select name="distributor_id" id="distributor_id" class="form-control select2" required style="width:100%;">
                <option value="">Select sales representative</option>
                @foreach($distributors as $id => $name)
                    <option value="{{ $id }}" {{ (string) old('distributor_id', $owner->distributor_id ?? '') === (string) $id ? 'selected' : '' }}>{{ $name }}</option>
                @endforeach
            </select>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label for="email">Email Address <small class="text-muted">(optional)</small></label>
            <input type="email" name="email" id="email" class="form-control" maxlength="255" value="{{ old('email', $owner->email ?? '') }}" placeholder="owner@example.com">
        </div>
    </div>
    <div class="col-md-6">
        <label>Phone Numbers <span class="text-danger">*</span></label>
        <div id="phone-list">
            @foreach($phoneValues as $index => $phone)
                <div class="input-group phone-row" style="margin-bottom:8px;">
                    <span class="input-group-addon"><i class="fa fa-phone"></i></span>
                    <input type="text" name="phones[]" class="form-control" maxlength="30" required value="{{ $phone }}" placeholder="Phone number">
                    <span class="input-group-btn">
                        <button type="button" class="btn btn-{{ $loop->first ? 'primary add-phone' : 'danger remove-phone' }}" title="{{ $loop->first ? 'Add another number' : 'Remove number' }}">
                            <i class="fa fa-{{ $loop->first ? 'plus' : 'minus' }}"></i>
                        </button>
                    </span>
                </div>
            @endforeach
        </div>
        <small class="text-muted">You can save up to 10 phone numbers.</small>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label for="road_name">Road Name</label>
            <input type="text" name="road_name" id="road_name" class="form-control" maxlength="255" value="{{ old('road_name', $owner->road_name ?? '') }}" placeholder="e.g. Mirpur Road">
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label for="visit_day">Visit Day</label>
            <select name="visit_day" id="visit_day" class="form-control">
                <option value="">Select visit day (optional)</option>
                @foreach(\App\ShopOwner::VISIT_DAYS as $value => $label)
                    <option value="{{ $value }}" {{ old('visit_day', $owner->visit_day ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>
</div>

<div class="form-group">
    <label for="address">Full Shop Address <span class="text-danger">*</span></label>
    <textarea name="address" id="address" class="form-control" rows="3" maxlength="2000" required placeholder="Road, area, city">{{ old('address', $owner->address ?? '') }}</textarea>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label for="latitude">Latitude <span class="text-danger">*</span></label>
            <input type="number" name="latitude" id="latitude" class="form-control" step="0.00000001" min="-90" max="90" required value="{{ old('latitude', $owner->latitude ?? '') }}" placeholder="23.81030000">
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label for="longitude">Longitude <span class="text-danger">*</span></label>
            <input type="number" name="longitude" id="longitude" class="form-control" step="0.00000001" min="-180" max="180" required value="{{ old('longitude', $owner->longitude ?? '') }}" placeholder="90.41250000">
        </div>
    </div>
</div>

<div class="well well-sm" style="margin-bottom:20px;">
    <i class="fa fa-map-marker text-primary"></i>
    Coordinates are captured by the mobile app. When entering an owner manually, copy them from your map application.
</div>

<div class="text-right">
    <a href="{{ route('distribution.shop-owners.index') }}" class="btn btn-default">Cancel</a>
    <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> {{ isset($owner) ? 'Update Shop Owner' : 'Save Shop Owner' }}</button>
</div>

@section('javascript')
<script>
$(document).ready(function () {
    $(document).on('click', '.add-phone', function () {
        if ($('#phone-list .phone-row').length >= 10) return;
        $('#phone-list').append('<div class="input-group phone-row" style="margin-bottom:8px;"><span class="input-group-addon"><i class="fa fa-phone"></i></span><input type="text" name="phones[]" class="form-control" maxlength="30" required placeholder="Phone number"><span class="input-group-btn"><button type="button" class="btn btn-danger remove-phone" title="Remove number"><i class="fa fa-minus"></i></button></span></div>');
    });
    $(document).on('click', '.remove-phone', function () {
        $(this).closest('.phone-row').remove();
    });
});
</script>
@endsection
