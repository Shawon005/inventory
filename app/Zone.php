<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Zone extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function businessLocation()
    {
        return $this->belongsTo(BusinessLocation::class, 'location_id');
    }

    public function contacts()
    {
        return $this->hasMany(Contact::class);
    }

    public static function forDropdown(int $businessId, ?int $includeZoneId = null)
    {
        $query = self::query()
            ->join('business_locations as bl', 'bl.id', '=', 'zones.location_id')
            ->where('zones.business_id', $businessId)
            ->where(function ($query) use ($includeZoneId) {
                $query->where('zones.is_active', true);

                if ($includeZoneId) {
                    $query->orWhere('zones.id', $includeZoneId);
                }
            });

        $permittedLocations = auth()->user()->permitted_locations();
        if ($permittedLocations !== 'all') {
            $query->whereIn('zones.location_id', $permittedLocations);
        }

        return $query
            ->orderBy('bl.name')
            ->orderBy('zones.name')
            ->get(['zones.id', 'zones.name', 'bl.name as location_name'])
            ->mapWithKeys(function ($zone) {
                return [$zone->id => $zone->location_name.' - '.$zone->name];
            });
    }
}
