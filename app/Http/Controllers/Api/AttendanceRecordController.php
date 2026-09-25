<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Http\Requests\Api\V1\CreateNewAttendanceRequest;
use App\Http\Requests\Api\V1\IndexAttendanceRecordRequest;
use App\Http\Requests\Api\V1\UpdateAttendanceRequest;
use App\Http\Resources\AttendanceResource;

use Illuminate\Http\Request;

class AttendanceRecordController extends Controller
{
    /**
     * デフォルトページネーション
     * @var int
     */
    private const DEFAULT_PER_PAGE = 20;

    /**
     * 勤怠データ一覧表示
     * GET(/api/v1/attendance-records)
     * @param IndexAttendanceRecordRequest $request
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(IndexAttendanceRecordRequest $request)
    {
        $validated = $request->validated();

        // 使用するリレーションデータをeager loading
        $query = Attendance::with([
            'user',
            'breaktimes',
            'applications'
        ]);

        // ユーザーID指定があるならクエリ実行
        $query->when(
            $validated['user_id'] ?? null,
            function ($query, $user_id) {
                return $query->where('user_id', $user_id);
            }
        );

        // 年月指定があるならクエリ実行
        $query->when(
            $validated['month'] ?? null,
            function ($query, $month) {
                $yearMonth = explode('-', $month);
                $query->whereYear('date', $yearMonth[0]);
                $query->whereMonth('date', $yearMonth[1]);
            },
        );

        // 日付指定があるならクエリ実行
        $query->when(
            $validated['date'] ?? null,
            function ($query, $date) {
                return $query->whereDate('date', $date);
            }
        );

        // 日付降順
        // １ページあたりの件数指定
        // レスポンス
        $attendances = $query
            ->latest('date')
            ->paginate($validated['per_page'] ?? self::DEFAULT_PER_PAGE);
        return AttendanceResource::collection($attendances);
    }

    /**
     * 勤怠データ新規作成
     * POST(/api/v1/attendance-records/{attendanceRecord})
     * @param CreateNewAttendanceRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(CreateNewAttendanceRequest $request)
    {
        // 作成
        $validated = $request->validated();
        $attendance = $request->user()
            ->attendances()
            ->create($validated);

        // レスポンス
        $attendance->load(['user', 'breaktimes']);
        return (new AttendanceResource($attendance))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * 勤怠詳細表示
     * GET(/api/v1/attendance-records/{attendanceRecord})
     * @param Attendance $attendanceRecord
     * @return AttendanceResource
     */
    public function show(Attendance $attendanceRecord)
    {
        // レスポンス
        $attendanceRecord->load([
            'user',
            'breaktimes',
            'applications.breakapplications'
        ]);
        return new AttendanceResource($attendanceRecord);
    }

    /**
     * 勤怠更新
     * PUT(/api/v1/attendance-records/{attendanceRecord})
     * @param UpdateAttendanceRequest $request
     * @param Attendance $attendanceRecord
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdateAttendanceRequest $request, Attendance $attendanceRecord)
    {
        // 認可
        $this->authorize('update', $attendanceRecord);

        // 更新
        $validated = $request->validated();
        $attendanceRecord->update($validated);

        // レスポンス
        $attendanceRecord->load(['user', 'breaktimes']);
        return (new AttendanceResource($attendanceRecord))
            ->response()
            ->setStatusCode(200);
    }

    /**
     * 勤怠削除
     * DELETE(/api/v1/attendance-records/{attendanceRecord})
     * @param Attendance $attendanceRecord
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Attendance $attendanceRecord)
    {
        // 認可
        $this->authorize('delete', $attendanceRecord);

        // 削除
        $attendanceRecord->delete();

        // レスポンス
        return response()->json(null, 204);
    }
}
