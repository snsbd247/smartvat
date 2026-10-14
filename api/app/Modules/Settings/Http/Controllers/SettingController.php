<?php

namespace App\Modules\Settings\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Settings\Models\Setting;
use App\Shared\Tenancy\CurrentCompany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Generic per-company settings store. Each "group" (general, numbering, email,
 * notifications, audit-policy, master.uom, master.currencies, …) holds one JSON
 * payload. Reading is open to any authenticated user of the company; writing
 * requires the company-configuration gate.
 */
class SettingController extends Controller
{
    private const GROUPS = [
        'general', 'numbering', 'notifications', 'email', 'audit-policy',
        'workflows', 'vat-versions',
        'master.uom', 'master.currencies', 'master.machines',
    ];

    public function show(string $group, CurrentCompany $current): JsonResponse
    {
        abort_unless(in_array($group, self::GROUPS, true), 404);

        $setting = Setting::where('group', $group)->first();

        return response()->json(['group' => $group, 'payload' => $setting?->payload ?? (object) []]);
    }

    public function update(string $group, Request $request, CurrentCompany $current): JsonResponse
    {
        abort_unless(in_array($group, self::GROUPS, true), 404);
        $this->authorize('configure-company');

        $payload = $request->validate(['payload' => ['required', 'array']])['payload'];

        $setting = Setting::updateOrCreate(
            ['company_id' => $current->idOrFail(), 'group' => $group],
            ['payload' => $payload],
        );

        return response()->json(['group' => $group, 'payload' => $setting->payload]);
    }
}
