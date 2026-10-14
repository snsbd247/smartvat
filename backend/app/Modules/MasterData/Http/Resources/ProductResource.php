<?php

namespace App\Modules\MasterData\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'code' => $this->code,
            'name' => $this->name,
            'type' => $this->type,
            'category_id' => $this->category_id,
            'base_uom' => $this->base_uom,
            'costing_method' => $this->costing_method,
            'track_batches' => $this->track_batches,
            'reorder_level' => $this->reorder_level,
            'status' => $this->status,
        ];
    }
}
