<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApplicationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'attendance_id' => $this->attendance_id,
            'application_date' => $this->application_date->format('Y-m-d'),
            'new_clock_in' => $this->new_clock_in->format('H:i:s'),
            'new_clock_out' => $this->new_clock_out->format('H:i:s'),
            'comment' => $this->comment,
            'break_applications' => ApplicationBreakResource::collection($this->whenLoaded('breakapplications')),
        ];
    }
}
