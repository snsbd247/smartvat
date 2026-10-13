<?php

namespace App\Modules\Organization\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanyConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $types = config('erp.business_types');
        $templates = array_keys(config('erp.industry_templates'));

        return [
            'business_types' => ['required', 'array', 'min:1'],
            'business_types.*' => [Rule::in($types)],
            'primary_business_type' => ['nullable', Rule::in($types)],
            'industry_template' => ['nullable', Rule::in($templates)],
        ];
    }
}
