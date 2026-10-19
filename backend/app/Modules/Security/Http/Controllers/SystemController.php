<?php

namespace App\Modules\Security\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Shared\Audit\AuditLogger;
use App\Shared\Audit\AuditLog;
use App\Shared\Tenancy\CurrentCompany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SystemController extends Controller
{
    public function __construct(private CurrentCompany $current) {}

    public function activityLogs(): JsonResponse
    {
        $this->authorize('user-view');

        return response()->json($this->logsQuery()->limit(100)->get()->map($this->mapLog()));
    }

    public function loginHistory(): JsonResponse
    {
        $this->authorize('user-view');

        return response()->json($this->logsQuery()->where('event', 'login')->limit(100)->get()->map($this->mapLog()));
    }

    public function roles(): JsonResponse
    {
        $this->authorize('user-view');

        return response()->json(Role::orderBy('name')->pluck('name'));
    }

    public function permissions(): JsonResponse
    {
        $this->authorize('user-view');

        return response()->json(Permission::orderBy('name')->pluck('name'));
    }

    /** Roles with their assigned permissions + the full permission catalogue. */
    public function rolesMatrix(): JsonResponse
    {
        $this->authorize('user-view');
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        $roles = Role::with('permissions:id,name')->orderBy('name')->get()
            ->map(fn (Role $r) => ['name' => $r->name, 'permissions' => $r->permissions->pluck('name')->values()]);

        return response()->json([
            'roles' => $roles,
            'permissions' => Permission::orderBy('name')->pluck('name'),
        ]);
    }

    /** Replace a role's permission set. Super-admin only; the super-admin role is immutable. */
    public function updateRolePermissions(Request $request, string $role): JsonResponse
    {
        abort_unless($request->user()->hasRole('super-admin'), 403, 'Only a super-admin can edit roles.');
        abort_if($role === 'super-admin', 422, 'The super-admin role cannot be edited.');

        $data = $request->validate([
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string'],
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        $roleModel = Role::findByName($role, 'web');
        $valid = Permission::whereIn('name', $data['permissions'])->pluck('name')->all();
        $roleModel->syncPermissions($valid);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        AuditLogger::log('role_permissions_updated', $roleModel);

        return response()->json(['message' => 'Role permissions updated.', 'count' => count($valid)]);
    }

    /** Create a new (global) role. Super-admin only. */
    public function createRole(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasRole('super-admin'), 403, 'Only a super-admin can create roles.');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:50', 'regex:/^[a-z][a-z0-9-]*$/'],
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        Role::findOrCreate($data['name'], 'web');

        return response()->json(['message' => 'Role created.'], 201);
    }

    private function logsQuery()
    {
        return AuditLog::with('user:id,name')
            ->where('company_id', $this->current->id())
            ->orderByDesc('id');
    }

    private function mapLog(): callable
    {
        return fn (AuditLog $l) => [
            'id' => $l->id,
            'event' => $l->event,
            'user' => $l->user?->name,
            'auditable_type' => class_basename((string) $l->auditable_type),
            'auditable_id' => $l->auditable_id,
            'ip_address' => $l->ip_address,
            'created_at' => $l->created_at,
        ];
    }
}
