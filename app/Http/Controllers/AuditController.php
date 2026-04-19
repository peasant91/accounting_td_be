<?php

namespace App\Http\Controllers;

use App\Http\Resources\ActivityLogResource;
use App\Http\Resources\LoginAttemptResource;
use App\Models\ActivityLog;
use App\Models\LoginAttempt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AuditController extends Controller
{
    public function activity(Request $request): AnonymousResourceCollection
    {
        $perPage = min((int) $request->integer('per_page', 25), 100);

        $q = ActivityLog::query()->with('user')->orderByDesc('id');

        if ($userId = $request->integer('user_id')) {
            $q->where('user_id', $userId);
        }
        if ($action = (string) $request->string('action')) {
            $q->where('action', $action);
        }
        if ($type = (string) $request->string('loggable_type')) {
            $q->where('loggable_type', $type);
        }
        if ($from = $request->date('date_from')) {
            $q->where('created_at', '>=', $from);
        }
        if ($to = $request->date('date_to')) {
            $q->where('created_at', '<=', $to);
        }
        if ($search = (string) $request->string('search')) {
            $like = '%' . $search . '%';
            $q->where(fn ($sub) => $sub
                ->where('action', 'like', $like)
                ->orWhere('properties', 'like', $like)
                ->orWhere('ip_address', 'like', $like));
        }

        return ActivityLogResource::collection($q->paginate($perPage));
    }

    public function loginAttempts(Request $request): AnonymousResourceCollection
    {
        $perPage = min((int) $request->integer('per_page', 25), 100);

        $q = LoginAttempt::query()->orderByDesc('id');

        if ($email = (string) $request->string('email')) {
            $q->where('email', $email);
        }
        if ($ip = (string) $request->string('ip')) {
            $q->where('ip_address', $ip);
        }
        if ($request->has('successful')) {
            $q->where('successful', $request->boolean('successful'));
        }
        if ($from = $request->date('date_from')) {
            $q->where('attempted_at', '>=', $from);
        }
        if ($to = $request->date('date_to')) {
            $q->where('attempted_at', '<=', $to);
        }

        return LoginAttemptResource::collection($q->paginate($perPage));
    }
}
