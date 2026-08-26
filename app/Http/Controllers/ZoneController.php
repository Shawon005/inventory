<?php

namespace App\Http\Controllers;

use App\BusinessLocation;
use App\Zone;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ZoneController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeAccess();

        $businessId = $request->session()->get('user.business_id');
        $zones = Zone::with('businessLocation')
            ->withCount('contacts')
            ->where('business_id', $businessId)
            ->when($request->filled('location_id'), function ($query) use ($request) {
                $query->where('location_id', $request->input('location_id'));
            });

        $permittedLocations = auth()->user()->permitted_locations();
        if ($permittedLocations !== 'all') {
            $zones->whereIn('location_id', $permittedLocations);
        }

        $zones = $zones->orderBy('name')->get();
        $locations = BusinessLocation::forDropdown($businessId, false);

        return view('zone.index', compact('zones', 'locations'));
    }

    public function create(Request $request)
    {
        $this->authorizeAccess();

        $businessId = $request->session()->get('user.business_id');
        $locations = BusinessLocation::forDropdown($businessId, false);
        $selectedLocation = $request->input('location_id');

        return view('zone.create', compact('locations', 'selectedLocation'));
    }

    public function store(Request $request)
    {
        $this->authorizeAccess();

        $businessId = $request->session()->get('user.business_id');
        $validated = $this->validateZone($request, $businessId);
        $validated['business_id'] = $businessId;
        $validated['is_active'] = $request->boolean('is_active');

        Zone::create($validated);

        return redirect()->route('zones.index', ['location_id' => $validated['location_id']])
            ->with('status', ['success' => true, 'msg' => 'Zone added successfully.']);
    }

    public function edit(Request $request, $id)
    {
        $this->authorizeAccess();

        $businessId = $request->session()->get('user.business_id');
        $zone = Zone::where('business_id', $businessId)->findOrFail($id);
        $this->ensureLocationIsPermitted($zone->location_id);
        $locations = BusinessLocation::forDropdown($businessId, false);

        return view('zone.edit', compact('zone', 'locations'));
    }

    public function update(Request $request, $id)
    {
        $this->authorizeAccess();

        $businessId = $request->session()->get('user.business_id');
        $zone = Zone::where('business_id', $businessId)->findOrFail($id);
        $this->ensureLocationIsPermitted($zone->location_id);

        $validated = $this->validateZone($request, $businessId, $zone->id);
        $validated['is_active'] = $request->boolean('is_active');
        $zone->update($validated);

        return redirect()->route('zones.index', ['location_id' => $validated['location_id']])
            ->with('status', ['success' => true, 'msg' => 'Zone updated successfully.']);
    }

    public function destroy(Request $request, $id)
    {
        $this->authorizeAccess();

        $businessId = $request->session()->get('user.business_id');
        $zone = Zone::where('business_id', $businessId)->findOrFail($id);
        $this->ensureLocationIsPermitted($zone->location_id);

        if ($zone->contacts()->exists()) {
            return redirect()->back()->with('status', [
                'success' => false,
                'msg' => 'This zone is assigned to contact customers. Reassign them before deleting it.',
            ]);
        }

        $zone->delete();

        return redirect()->back()->with('status', ['success' => true, 'msg' => 'Zone deleted successfully.']);
    }

    protected function validateZone(Request $request, int $businessId, ?int $ignoreId = null): array
    {
        $permittedLocations = auth()->user()->permitted_locations();
        $locationRule = Rule::exists('business_locations', 'id')->where(function ($query) use ($businessId, $permittedLocations) {
            $query->where('business_id', $businessId);
            if ($permittedLocations !== 'all') {
                $query->whereIn('id', $permittedLocations);
            }
        });

        $nameRule = Rule::unique('zones', 'name')->where(function ($query) use ($businessId, $request) {
            return $query->where('business_id', $businessId)
                ->where('location_id', $request->input('location_id'));
        });

        if ($ignoreId) {
            $nameRule->ignore($ignoreId);
        }

        return $request->validate([
            'location_id' => ['required', $locationRule],
            'name' => ['required', 'string', 'max:255', $nameRule],
            'description' => 'nullable|string|max:1000',
            'is_active' => 'nullable|boolean',
        ]);
    }

    protected function authorizeAccess(): void
    {
        if (! auth()->user()->can('business_settings.access')) {
            abort(403, 'Unauthorized action.');
        }
    }

    protected function ensureLocationIsPermitted(int $locationId): void
    {
        $permittedLocations = auth()->user()->permitted_locations();
        if ($permittedLocations !== 'all' && ! in_array($locationId, (array) $permittedLocations, false)) {
            abort(403, 'Unauthorized action.');
        }
    }
}
