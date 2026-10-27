<?php

namespace Modules\Manufacturing\Services;

use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PostingService
{
    public function run(User $user, string $operation, string $key, array $payload, callable $callback): array
    {
        if (! preg_match('/^[A-Za-z0-9_.:-]{8,80}$/D', $key)) {
            throw ValidationException::withMessages(['idempotency_key' => 'Provide a unique operation key of 8–80 letters, digits, dots, colons, underscores or hyphens.']);
        }
        $hash = hash('sha256', json_encode($this->canonical($payload), JSON_THROW_ON_ERROR));
        return DB::transaction(function () use ($user, $operation, $key, $payload, $callback, $hash) {
            // Tenant mutex protects version publication, document keys and concurrent order commands.
            abort_unless(DB::table('business')->where('id', $user->business_id)->lockForUpdate()->first(), 403);
            $query = DB::table('inventory_postings')->where('business_id', $user->business_id)->where('operation', $operation)->where('idempotency_key', $key);
            if ($existing = $query->first()) {
                if (! hash_equals($existing->payload_hash, $hash)) {
                    throw ValidationException::withMessages(['idempotency_key' => 'This operation key was already used with different content.']);
                }
                return json_decode($existing->result, true, 512, JSON_THROW_ON_ERROR);
            }
            $id = DB::table('inventory_postings')->insertGetId([
                'business_id' => $user->business_id, 'operation' => $operation, 'idempotency_key' => $key,
                'payload_hash' => $hash, 'request_payload' => json_encode($payload, JSON_THROW_ON_ERROR), 'created_by' => $user->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $result = $callback($id);
            $query->update(['result' => json_encode($result, JSON_THROW_ON_ERROR), 'updated_at' => now()]);
            return $result;
        }, 3);
    }

    private function canonical(array $data): array
    {
        if ($data && array_keys($data) !== range(0, count($data) - 1)) { ksort($data); }
        foreach ($data as &$value) {
            if (is_array($value)) { $value = $this->canonical($value); }
        }
        return $data;
    }
}
