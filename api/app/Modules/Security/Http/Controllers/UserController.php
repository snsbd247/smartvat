<?php

namespace App\Modules\Security\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Shared\Tenancy\CurrentCompany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Spatie\Permission\PermissionRegistrar;

class UserController extends Controller
{
    public function __construct(private CurrentCompany $current) {}

    /** Users who have access to the active company. */
    public function index(): JsonResponse
    {
        $this->authorize('user-view');

        return response()->json(
            $this->current->model()->users()->get(['users.id', 'name', 'email', 'username', 'status'])
        );
    }

    /** Create a user, bind them to the active company, and assign a role in it. */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('user-manage');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'username' => ['nullable', 'string', 'max:64', Rule::unique('users', 'username')],
            'password' => ['required', 'string', 'min:6'],
            'mobile' => ['nullable', 'string', 'max:40'],
            'role' => ['sometimes', Rule::in(['admin', 'viewer'])],
            'nid' => ['nullable', 'string', 'max:40'],
        ]);

        $company = $this->current->model();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'username' => $data['username'] ?? null,
            'mobile' => $data['mobile'] ?? null,
            'password' => Hash::make($data['password']),
            'status' => 'active',
            'created_by' => auth()->id(),
        ]);

        if (! empty($data['nid'])) {
            $user->nid = $data['nid']; // encrypted at rest + blind index
            $user->save();
        }

        $company->users()->syncWithoutDetaching([$user->id => ['is_default' => false]]);

        if (! empty($data['role'])) {
            app(PermissionRegistrar::class)->setPermissionsTeamId($company->id);
            $user->assignRole($data['role']);
        }

        return response()->json([
            'id' => $user->id, 'name' => $user->name, 'email' => $user->email,
            'username' => $user->username, 'status' => $user->status,
        ], 201);
    }
}
