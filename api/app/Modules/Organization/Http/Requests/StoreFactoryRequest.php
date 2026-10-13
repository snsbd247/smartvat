<?php

namespace App\Modules\Organization\Http\Requests;

use App\Shared\Tenancy\CurrentCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFactoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled by the controller policy.
    }

    public function rules(): array
    {
        $companyId = app(CurrentCompany::class)->id();

        return [
            'code' => [
                'required', 'string', 'max:32',
                // Code is unique per company (tenant), not globally.
                Rule::unique('factories', 'code')->where('company_id', $companyId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ];
    }
}
