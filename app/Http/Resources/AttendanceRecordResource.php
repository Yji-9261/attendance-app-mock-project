<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MissingValue;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AttendanceRecordResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     * 
     * @param Request $request リクエスト
     * @return array{applications: MissingValue|mixed, breaks: MissingValue|mixed, "clock_in": mixed, "clock_out": mixed, comment: mixed, date: mixed, id: mixed, "total_break_time": MissingValue|mixed, "total_time": MissingValue|mixed, user: MissingValue|mixed, "user_id": MissingValue|mixed, "user_name": MissingValue|mixed}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->getUserId($request),
            'user_name' => $this->getUserName($request),
            'user' => $this->getUser($request),
            'date' => $this->date->format('Y-m-d'),
            'clock_in' => $this->clock_in->format('H:i:s'),
            'clock_out' => $this->clock_out?->format('H:i:s'),
            'total_time' => $this->getTotalTime($request),
            'total_break_time' => $this->getTotalBreakTime($request),
            'comment' => $this->comment,
            'breaks' => $this->getBreaks($request),
            'applications' => $this->getApplications($request),
        ];
    }

    /**
     * 詳細勤怠以外は実勤務時間を返す
     * 
     * @param Request $request リクエスト
     * @return string|MissingValue
     */
    private function getTotalTime(Request $request)
    {
        return $this->when(
            !$request->routeIs('*.show'),
            $this->total_time
        );
    }

    /**
     * 詳細勤怠以外は総休憩時間を返す
     * 
     * @param Request $request リクエスト
     * @return string|MissingValue
     */
    private function getTotalBreakTime(Request $request)
    {
        return $this->when(
            !$request->routeIs('*.show'),
            $this->total_break_time
        );
    }

    /**
     * 詳細勤怠以外はuser_idを返す
     * 
     * @param Request $request リクエスト
     * @return int|MissingValue
     */
    private function getUserId(Request $request)
    {
        return $this->when(
            !$request->routeIs('*.show'),
            $this->user_id
        );
    }

    /**
     * 詳細勤怠以外はuser_nameを返す
     * 
     * @param Request $request リクエスト
     * @return string|MissingValue
     */
    private function getUserName(Request $request)
    {
        return $this->when(
            !$request->routeIs('*.show'),
            $this->user->name
        );
    }

    /**
     * 詳細勤怠はUser詳細を返す
     * 
     * @param Request $request リクエスト
     * @return UserResource|MissingValue
     */
    private function getUser(Request $request)
    {
        return $this->when(
            $request->routeIs('*.show'),
            new UserResource($this->whenLoaded('user'))
        );
    }

    /**
     * 詳細勤怠は休憩時間詳細を返す
     * 
     * @param Request $request リクエスト
     * @return AnonymousResourceCollection|MissingValue
     */
    private function getBreaks(Request $request)
    {
        return $this->when(
            $request->routeIs('*.show'),
            AttendanceBreakResource::collection($this->whenLoaded('breaktimes'))
        );
    }

    /**
     * 詳細勤怠は勤怠修正申請詳細を返す
     * 
     * @param Request $request リクエスト
     * @return AnonymousResourceCollection|MissingValue
     */
    private function getApplications(Request $request)
    {
        return $this->when(
            $request->routeIs('*.show'),
            ApplicationResource::collection($this->whenLoaded('applications')),
        );
    }
}
