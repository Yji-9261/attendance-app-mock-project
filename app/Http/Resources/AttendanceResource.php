<?php

namespace App\Http\Resources;

use App\Http\Resources\ApplicationResource;
use App\Http\Resources\AttendanceBreakResource;
use App\Http\Resources\UserResource;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            "id" => $this->id,
            "user_id" => $this->getUserId($request),
            "user_name" => $this->getUserName($request),
            "user" => $this->getUser($request),
            "date" => $this->date->format('Y-m-d'),
            "clock_in" => $this->clock_in->format('H:i:s'),
            "clock_out" => $this->clock_out?->format('H:i:s'),
            "total_time" => $this->getTotalTime($request),
            "total_break_time" => $this->getTotalBreakTime($request),
            "comment" => $this->comment,
            "breaks" => $this->getBreaks($request),
            "applications" => $this->getApplications($request),
        ];
    }

    private function getTotalTime(Request $request)
    {
        return $this->when(
            !$request->routeIs('*.show'),
            $this->total_time
        );
    }

    private function getTotalBreakTime(Request $request)
    {
        return $this->when(
            !$request->routeIs('*.show'),
            $this->total_break_time
        );
    }

    private function getUserId(Request $request)
    {
        return $this->when(
            !$request->routeIs('*.show'),
            $this->user_id
        );
    }

    private function getUserName(Request $request)
    {
        return $this->when(
            !$request->routeIs('*.show'),
            $this->user->name
        );
    }

    private function getUser(Request $request)
    {
        return $this->when(
            $request->routeIs('*.show'),
            new UserResource($this->whenLoaded('user'))
        );
    }

    private function getBreaks(Request $request)
    {
        return $this->when(
            $request->routeIs('*.show'),
            AttendanceBreakResource::collection($this->whenLoaded('breaktimes'))
        );
    }

    public function getApplications(Request $request)
    {
        return $this->when(
            $request->routeIs('*.show'),
            ApplicationResource::collection($this->whenLoaded('applications')),
        );
    }
}
