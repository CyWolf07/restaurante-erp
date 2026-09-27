<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AuditService
{
    public function record(string $entity, string $id, string $action, array $data, ?string $userId = null): void
    {
        DB::table('audit_events')->insert([
            'user_id' => $userId ?? Auth::id(), 'entity_type' => $entity, 'entity_id' => $id,
            'action' => $action, 'data' => json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
        ]);
    }
}
