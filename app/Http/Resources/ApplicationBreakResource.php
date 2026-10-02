<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApplicationBreakResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     * 
     * @param Request $request リクエスト
     * @return array{"application_id": int, "break_in": string, "break_out": string, id: int}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'application_id' => $this->application_id,
            'break_in' => $this->break_in->format('H:i:s'),
            'break_out' => $this->break_out->format('H:i:s'),
        ];
    }
}
