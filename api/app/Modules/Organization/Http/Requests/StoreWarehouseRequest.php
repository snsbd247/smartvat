<?php

namespace App\Modules\Organization\Http\Requests;

use App\Shared\Tenancy\CurrentCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = app(CurrentCompany::class)->id();

        return [
            'code' => [
                'required', 'string', 'max:32',
                Rule::unique('warehouses', 'code')->where('company_id', $companyId),
            ],
            'name' => ['required', 'string', 'max:255'],
            // factory_id must belong to the same company — enforced via tenant-scoped exists.
            'factory_id' => [
                'nullable',
                Rule::exists('factories', 'id')->where('company_id', $companyId),
            ],
            'type' => ['sometimes', 'string', 'max:32'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ];
    }
}
