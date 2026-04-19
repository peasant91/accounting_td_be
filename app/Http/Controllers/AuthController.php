<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Models\LoginAttempt;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    public function __construct(
        private AuditLogger $auditLogger,
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $email = strtolower((string) $request->string('email'));
        $password = (string) $request->string('password');
        $key = 'login:' . sha1($request->ip() . '|' . $email);

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json([
                'message' => 'Too many login attempts. Try again in a few minutes.',
            ], 429)->header('Retry-After', RateLimiter::availableIn($key));
        }

        $user = User::where('email', $email)->first();
        $successful = $user && Hash::check($password, $user->password);

        LoginAttempt::create([
            'email' => $email,
            'user_id' => $user?->id,
            'ip_address' => $request->ip(),
            'user_agent' => substr($request->userAgent() ?? '', 0, 500) ?: null,
            'successful' => $successful,
            'attempted_at' => now(),
        ]);

        if (!$successful) {
            RateLimiter::hit($key, 60 * 15);
            $this->auditLogger->log(
                action: 'auth.login_failed',
                properties: ['email' => $email],
                userId: $user?->id,
            );
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        RateLimiter::clear($key);

        Auth::login($user);
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        $this->auditLogger->log(action: 'auth.login', userId: $user->id);

        return response()->json(null, 204);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        $all = (bool) $request->boolean('all');

        if ($all) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }

        Auth::guard('web')->logout();
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        $this->auditLogger->log(action: 'auth.logout', userId: $user->id);

        return response()->json(null, 204);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
            ],
        ]);
    }
}
