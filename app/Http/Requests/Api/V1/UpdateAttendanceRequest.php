<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Attendance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

use Illuminate\Support\Facades\Log;

class UpdateAttendanceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $attendance = $this->route('attendanceRecord');
        //$attendance = Attendance::findOrFail($attendanceId);

        //Log::info($attendance->user_id);
        //Log::info($this->user()->id);

        return [
            'date' => [
                'required',
                'date_format:Y-m-d',
                Rule::unique('attendances')
                    ->ignore($attendance->id)
                    ->where('user_id', $this->user()->id),
            ],
            'clock_in' => [
                'required',
                'date_format:H:i:s'
            ],
            'clock_out' => [
                'date_format:H:i:s'
            ],
        ];
    }

    public function messages()
    {
        return [
            'date.required' => '勤怠日は必須です。',
            'date.date_format' => '勤怠日は YYYY-MM-DD 形式で指定してください。',
            'date.unique' => 'この日付の勤怠は既に登録されています。',

            'clock_in.required' => '出勤時刻は必須です。',
            'clock_in.date_format' => '出勤時刻は HH:MM:SS 形式で指定してください。',

            'clock_out.date_format' => '退勤時刻は HH:MM:SS 形式で指定してください。',
            'clock_out.after' => '退勤時刻は出勤時刻より後の時刻を指定してください。',

            'comment.max' => '備考は 255 文字以内で入力してください。',
        ];
    }
}
