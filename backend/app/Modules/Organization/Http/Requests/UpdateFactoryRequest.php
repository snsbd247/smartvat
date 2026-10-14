<?php

namespace App\Modules\Organization\Http\Requests;

use App\Shared\Tenancy\CurrentCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFactoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = app(CurrentCompany::class)->id();
        $factoryId = $this->route('factory')?->id;

        return [
            'code' => [
                'sometimes', 'required', 'string', 'max:32',
                Rule::unique('factories', 'code')
                    ->where('company_id', $companyId)
                    ->ignore($factoryId),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ];
    }
}
