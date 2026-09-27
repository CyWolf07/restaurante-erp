<?php

namespace App\Services;

use App\Models\InventoryLog;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class InventoryRequestService
{
    public function metadata(?string $key, string $userId, array $payload): array
    {
        Validator::make(['operation_key' => $key], ['operation_key' => 'nullable|uuid'])->validate();
        if ($key === null) {
            return [];
        }
        ksort($payload);

        return ['operation_key' => $key,
            'operation_signature' => hash('sha256', json_encode([$userId, $payload], JSON_THROW_ON_ERROR))];
    }

    /** Call inside the shared inventory transaction, before changing balances. */
    public function existing(array $metadata): ?InventoryLog
    {
        if (! isset($metadata['operation_key'])) {
            return null;
        }
        $log = InventoryLog::where('operation_key', $metadata['operation_key'])->first();
        if ($log && ! hash_equals((string) $log->operation_signature, $metadata['operation_signature'])) {
            throw ValidationException::withMessages(['operation_key' => 'Esta solicitud ya fue registrada con otros datos. Actualiza la pantalla.']);
        }

        return $log;
    }
}
