<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\Admin\CreateAdminRequest;
use App\Http\Requests\Admin\UpdateAdminRequest;
use App\Http\Resources\AdminResource;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    public function __construct(
        private AuditLogger $auditLogger,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min((int) $request->integer('per_page', 25), 100);
        return AdminResource::collection(User::orderBy('id')->paginate($perPage));
    }

    public function store(CreateAdminRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = User::create([
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'password' => $data['password'],
            'role' => $data['role'],
        ]);

        // Explicit business-level action on top of the trait-generated user.created row.
        $this->auditLogger->log(
            action: 'admin.created',
            target: $user,
            properties: ['role' => $user->role->value],
        );

        return (new AdminResource($user))->response()->setStatusCode(201);
    }

    public function show(User $admin): AdminResource
    {
        return new AdminResource($admin);
    }

    public function update(UpdateAdminRequest $request, User $admin): AdminResource
    {
        $data = $request->validated();

        // Self-lock: cannot change own role
        if ($admin->id === $request->user()->id && isset($data['role']) && $data['role'] !== $admin->role->value) {
            throw ValidationException::withMessages(['role' => 'You cannot change your own role.']);
        }

        // Last-super lock on demotion
        if (isset($data['role']) && $admin->isSuperAdmin() && $data['role'] !== UserRole::SuperAdmin->value) {
            $this->assertNotLastSuperAdmin($admin);
        }

        $previousRole = $admin->role;
        if (isset($data['email'])) {
            $data['email'] = strtolower($data['email']);
        }
        // Password: only touch if non-empty (accept missing OR empty string as "no change")
        if (array_key_exists('password', $data) && ($data['password'] ?? '') === '') {
            unset($data['password']);
        }

        $admin->update($data);

        if (isset($data['role']) && $previousRole !== $admin->role) {
            $this->auditLogger->log(
                action: 'admin.role_changed',
                target: $admin,
                properties: ['before' => ['role' => $previousRole->value], 'after' => ['role' => $admin->role->value]],
            );
        } else {
            $this->auditLogger->log(action: 'admin.updated', target: $admin);
        }

        return new AdminResource($admin);
    }

    public function destroy(Request $request, User $admin): JsonResponse
    {
        if ($admin->id === $request->user()->id) {
            throw ValidationException::withMessages(['admin' => 'You cannot delete your own account.']);
        }
        if ($admin->isSuperAdmin()) {
            $this->assertNotLastSuperAdmin($admin);
        }

        $deletedId = $admin->id;
        $deletedEmail = $admin->email;
        $admin->delete();

        $this->auditLogger->log(
            action: 'admin.deleted',
            properties: ['deleted_admin_id' => $deletedId, 'email' => $deletedEmail],
        );

        return response()->json(null, 204);
    }

    private function assertNotLastSuperAdmin(User $admin): void
    {
        $superCount = User::where('role', UserRole::SuperAdmin->value)->count();
        if ($superCount <= 1) {
            throw ValidationException::withMessages([
                'role' => 'Cannot remove the last super admin. Promote another admin first.',
            ]);
        }
    }
}
