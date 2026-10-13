<?php

namespace App\Modules\Security\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Shared\Audit\AuditLog;
use App\Shared\Tenancy\CurrentCompany;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

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
