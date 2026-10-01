<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexAttendanceRecordRequest;
use App\Http\Requests\Api\V1\StoreAttendanceRequest;
use App\Http\Requests\Api\V1\UpdateAttendanceRequest;
use App\Http\Resources\AttendanceResource;
use App\Models\Attendance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AttendanceRecordController extends Controller
{
    /**
     * ページネーションデフォルト値
     *
     * @var int
     */
    private const DEFAULT_PER_PAGE = 20;

    /**
     * 勤怠データ一覧表示
     * GET(/api/v1/attendance-records)

     *
     * @param  IndexAttendanceRecordRequest  $request  勤怠一覧表示時のフォームリクエスト
     */
    public function index(IndexAttendanceRecordRequest $request): AnonymousResourceCollection
    {
        $validated = $request->validated();

        $query = Attendance::with([
            'user',
            'breaktimes',
        ]);

        $query->when(
            $validated['user_id'] ?? null,
            function ($query, $userId) {
                return $query->where('user_id', $userId);
            }
        );

        $query->when(
            $validated['month'] ?? null,
            function ($query, $month) {
                $yearMonth = explode('-', $month);
                $query->whereYear('date', $yearMonth[0]);
                $query->whereMonth('date', $yearMonth[1]);
            },
        );

        $query->when(
            $validated['date'] ?? null,
            function ($query, $date) {
                return $query->whereDate('date', $date);
            }
        );

        $attendances = $query
            ->latest('date')
            ->paginate($validated['per_page'] ?? self::DEFAULT_PER_PAGE);

        return AttendanceResource::collection($attendances);
    }

    /**
     * 勤怠データ新規作成
     * POST(/api/v1/attendance-records/{attendanceRecord})
     *
     * @param  StoreAttendanceRequest  $request  勤怠登録時のフォームリクエスト
     */
    public function store(StoreAttendanceRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $attendance = $request->user()
            ->attendances()
            ->create($validated);

        $attendance->load(['user', 'breaktimes']);

        return (new AttendanceResource($attendance))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * 勤怠詳細表示
     * GET(/api/v1/attendance-records/{attendanceRecord})
     */
    public function show(Attendance $attendanceRecord): AttendanceResource
    {
        $attendanceRecord->load([
            'user',
            'breaktimes',
            'applications.breakapplications',
        ]);

        return new AttendanceResource($attendanceRecord);
    }

    /**
     * 勤怠更新
     * PUT(/api/v1/attendance-records/{attendanceRecord})
     * 
     * @param UpdateAttendanceRequest $request
     * @param Attendance $attendanceRecord
     * @return JsonResponse
     */
    public function update(
        UpdateAttendanceRequest $request,
        Attendance $attendanceRecord
    ): JsonResponse {
        $this->authorize('update', $attendanceRecord);

        $validated = $request->validated();
        $attendanceRecord->update($validated);

        $attendanceRecord->load(['user', 'breaktimes']);

        return (new AttendanceResource($attendanceRecord))
            ->response()
            ->setStatusCode(200);
    }

    /**
     * 勤怠削除
     * DELETE(/api/v1/attendance-records/{attendanceRecord})
     * 
     * @param Attendance $attendanceRecord
     * @return JsonResponse
     */
    public function destroy(Attendance $attendanceRecord): JsonResponse
    {
        $this->authorize('delete', $attendanceRecord);

        $attendanceRecord->delete();

        return response()->json(null, 204);
    }
}
