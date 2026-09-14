<?php

namespace App\Http\Requests;

use App\Models\Attendance;
use Carbon\Carbon;

use Illuminate\Foundation\Http\FormRequest;

class ApplicationRequest extends FormRequest
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
        return [
            'new_clock_in' => ['required', 'date_format:H:i',],

            'new_clock_out' => ['required', 'after_or_equal:new_clock_in', 'date_format:H:i',],

            'comment' => ['required'],

            'new_break_in.*' => [
                'nullable',
                'required_with:new_break_out.*',
                'after_or_equal:new_clock_in',
                'before_or_equal:new_clock_out',
                'date_format:H:i',
            ],

            'new_break_out.*' => [
                'nullable',
                'required_with:new_break_in.*',
                'before_or_equal:new_clock_out',
                'after_or_equal:new_break_in.*',
                'date_format:H:i',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'new_clock_in.required' => '出勤時間が未入力です',
            'new_clock_in.date_format' => '出勤時間が不適切な値です',

            'new_clock_out.required' => '退勤時間が未入力です',
            'new_clock_out.after_or_equal' => '出勤時間もしくは退勤時間が不適切な値です',
            'new_clock_out.date_format' => '退勤時間が不適切な値です',

            'comment.required' => '備考を記入してください',

            'new_break_in.*.after_or_equal' => '休憩時間が不適切な値です',
            'new_break_in.*.before_or_equal' => '休憩時間が不適切な値です',
            'new_break_in.*.required_with' => '休憩開始時間が未入力です',
            'new_break_in.date_format' => '休憩開始時間が不適切な値です',

            'new_break_out.*.before_or_equal' => '休憩時間もしくは退勤時間が不適切な時間です',
            'new_break_out.*.after_or_equal' => '休憩時間が不適切な値です',
            'new_break_out.*.required_with' => '休憩終了時間が未入力です',
            'new_break_out.date_format' => '休憩終了時間が不適切な値です',

        ];
    }


    // いったん放置、応用問題が終わって時間があったら対応する
    // public function withValidator($validator)
    // {
    //     // 休憩時間が
    //     // 12:00~13:00 12:30~13:30
    //     // の記載がされていたときに重複として弾く処理
    //     $validator->after(function ($validator) {
    //         $newBreakIns = $this->input('new_break_in', []);
    //         $newBreakOuts = $this->input('new_break_out', []);

    //         // 休憩時間が一個以下なら特殊な処理はない
    //         if ($newBreakIns->count() <= 1) {
    //             return;
    //         }

    //         foreach ($newBreakIns as $index => $item) {
    //             $item->dateTimeBetween();
    //         }

    //         // 
    //         if ($this->input('field_a') === 1 && empty($this->input('field_b'))) {
    //             $validator->errors()->add('field_b', 'field_aが1の場合、field_bは必須です。');
    //         }
    //     });
    // }
}
