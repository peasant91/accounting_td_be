<?php

namespace App\Services\Audit;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;

class AuditLogger
{
    public function log(
        string $action,
        ?Model $target = null,
        array $properties = [],
        ?int $userId = null,
    ): ActivityLog {
        $request = request();
        $userId ??= auth()->id();

        return ActivityLog::create([
            'action' => $action,
            'loggable_type' => $target ? $target::class : null,
            'loggable_id' => $target?->getKey(),
            'user_id' => $userId,
            'properties' => $properties,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent() ? substr($request->userAgent(), 0, 500) : null,
        ]);
    }
}
