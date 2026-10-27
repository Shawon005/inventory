<?php

namespace Modules\Manufacturing\Services;

use App\User;
use Illuminate\Support\Facades\DB;

class ManufacturingAccess
{
    public function business(User $user): int
    {
        abort_unless($user->business_id, 403);
        return (int) $user->business_id;
    }

    public function authorize(User $user, string $ability): int
    {
        abort_unless($user->can('manufacturing.'.$ability), 403);
        return $this->business($user);
    }

    public function location(User $user, int $id, bool $requireActive = true): void
    {
        \App\Support\InventoryChannel::assertLocation($id);
        $location = DB::table('business_locations')->where('id', $id)->where('business_id', $this->business($user));
        if ($requireActive) { $location->where('is_active', 1); }
        abort_unless($location->exists(), 404);
        $permitted = $user->permitted_locations($this->business($user));
        abort_unless($permitted === 'all' || in_array($id, $permitted), 403);
    }
}
