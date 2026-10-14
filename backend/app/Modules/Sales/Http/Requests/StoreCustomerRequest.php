<?php

namespace App\Modules\Sales\Http\Requests;

use App\Shared\Tenancy\CurrentCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = app(CurrentCompany::class)->id();
        $customerId = $this->route('customer')?->id;

        return [
            'code' => [
                $this->isMethod('post') ? 'required' : 'sometimes', 'string', 'max:32',
                Rule::unique('customers', 'code')->where('company_id', $companyId)->ignore($customerId),
            ],
            'name' => [$this->isMethod('post') ? 'required' : 'sometimes', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'country' => ['sometimes', 'string', 'size:2'],
            'bin' => ['nullable', 'string', 'max:32'],
            'vat_reg_no' => ['nullable', 'string', 'max:64'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ];
    }
}
