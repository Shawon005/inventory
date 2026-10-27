<?php

namespace Modules\B2B\Services;

use App\Support\InventoryChannel;
use App\User;
use Illuminate\Support\Facades\DB;

class Access
{
    public function authorize(User $user, string $ability): int
    {
        abort_unless($user->business_id && $user->can('b2b.'.$ability), 403);
        return (int) $user->business_id;
    }

    public function read(User $user): int
    {
        abort_unless($user->business_id, 403);
        abort_unless(collect(['dashboard.view', 'products.manage', 'customers.manage', 'stock.transfer', 'pos.access', 'sales.create', 'credit.manage', 'payments.record', 'returns.process', 'reports.view'])->contains(fn ($a) => $user->can('b2b.'.$a)), 403);
        return (int) $user->business_id;
    }

    public function location(User $user, int $id, bool $active = true): object
    {
        $q = DB::table('business_locations')->where('id', $id)->where('business_id', $user->business_id);
        InventoryChannel::mainLocations($q, 'id');
        if ($active) { $q->where('is_active', 1); }
        $row = $q->first(); abort_unless($row, 404);
        $ids = $user->permitted_locations($user->business_id);
        abort_unless($ids === 'all' || in_array($id, $ids), 403);
        return $row;
    }

    public function scope($query, User $user, $column): void
    {
        $ids = $user->permitted_locations($user->business_id);
        if ($ids !== 'all') { $query->whereIn($column, $ids); }
        InventoryChannel::mainLocations($query, $column);
    }

    public function contactScope($query, User $user, string $alias = 'c'): void
    {
        // B2B POS access is an explicit business-wide sales permission. Do not
        // let the generic retail "own customers" filter hide enabled B2B buyers.
        if (! $user->can('b2b.pos.access') && $user->can('customer.view_own') && ! $user->can('customer.view') && ! $user->can('b2b.customers.manage')) {
            $query->where(function ($q) use ($user, $alias) {
                $q->where($alias.'.created_by', $user->id)->orWhereExists(fn ($a) => $a->selectRaw('1')->from('user_contact_access')->whereColumn('contact_id', $alias.'.id')->where('user_id', $user->id));
            });
        }
    }
}
