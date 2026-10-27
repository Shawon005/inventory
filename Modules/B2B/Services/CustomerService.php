<?php

namespace Modules\B2B\Services;

use App\Support\Decimal as D;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Modules\Manufacturing\Services\PostingService;

class CustomerService
{
    public function __construct(private Access $access, private PostingService $postings) {}

    public function save(User $user, array $data, string $key): array
    {
        $this->access->authorize($user, 'customers.manage');
        Validator::make($data, ['contact_id' => 'nullable|integer|min:1', 'name' => 'required_without:contact_id|string|max:191', 'mobile' => 'required_without:contact_id|string|max:191', 'email' => 'nullable|email|max:191', 'address' => 'nullable|string|max:2000', 'credit_limit' => 'required', 'payment_term_days' => 'required|integer|min:0|max:3650', 'is_active' => 'required|boolean'])->validate();
        $limit = D::positive(D::value($data['credit_limit']), 'credit_limit', true);
        return $this->postings->run($user, 'b2b.customer', $key, $data, function () use ($user, $data, $limit) {
            if (! empty($data['contact_id'])) {
                $id = (int) $data['contact_id'];
                abort_unless(DB::table('contacts')->where('id', $id)->where('business_id', $user->business_id)->whereIn('type', ['customer', 'both'])->whereNull('deleted_at')->exists(), 404);
            } else {
                $id = DB::table('contacts')->insertGetId(['business_id' => $user->business_id, 'type' => 'customer', 'name' => $data['name'], 'supplier_business_name' => $data['name'], 'mobile' => $data['mobile'], 'email' => $data['email'] ?? null, 'address_line_1' => $data['address'] ?? null, 'contact_status' => 'active', 'created_by' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
            }
            DB::table('b2b_customer_profiles')->updateOrInsert(['business_id' => $user->business_id, 'contact_id' => $id], ['credit_limit' => $limit, 'payment_term_days' => $data['payment_term_days'], 'is_active' => $data['is_active'], 'created_at' => now(), 'updated_at' => now()]);
            return ['contact_id' => $id];
        });
    }
}
