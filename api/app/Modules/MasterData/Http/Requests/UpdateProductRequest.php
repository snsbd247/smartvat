<?php

namespace App\Modules\MasterData\Http\Requests;

use App\Shared\Tenancy\CurrentCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = app(CurrentCompany::class)->id();
        $productId = $this->route('product')?->id;

        return [
            'code' => [
                'sometimes', 'required', 'string', 'max:64',
                Rule::unique('products', 'code')->where('company_id', $companyId)->ignore($productId),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['sometimes', Rule::in(['raw', 'finished', 'packaging', 'consumable', 'spare', 'service'])],
            'category_id' => ['nullable', Rule::exists('product_categories', 'id')->where('company_id', $companyId)],
            'base_uom' => ['sometimes', 'string', 'max:16'],
            'costing_method' => ['sometimes', Rule::in(['weighted_average', 'fifo'])],
            'track_batches' => ['sometimes', 'boolean'],
            'reorder_level' => ['sometimes', 'numeric', 'min:0'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ];
    }
}
