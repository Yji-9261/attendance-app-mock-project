<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'new_clock_in' => [
                'required',
                'date_format:H:i',
            ],

            'new_clock_out' => [
                'required',
                'after:new_clock_in',
                'date_format:H:i',
            ],

            'comment' => [
                'required',
                'string',
                'max:255',
            ],

            'new_break_in.*' => [
                'nullable',
                'required_with:new_break_out.*',
                'after:new_clock_in',
                'before:new_clock_out',
                'date_format:H:i',
            ],

            'new_break_out.*' => [
                'nullable',
                'required_with:new_break_in.*',
                'before:new_clock_out',
                'after:new_break_in.*',
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
            'new_clock_out.after' => '出勤時間もしくは退勤時間が不適切な値です',
            'new_clock_out.date_format' => '退勤時間が不適切な値です',

            'comment.required' => '備考を記入してください',
            'comment.max' => '最大255文字までです',

            'new_break_in.*.after' => '休憩時間が不適切な値です',
            'new_break_in.*.before' => '休憩時間が不適切な値です',
            'new_break_in.*.required_with' => '休憩開始時間が未入力です',
            'new_break_in.date_format' => '休憩開始時間が不適切な値です',

            'new_break_out.*.before' => '休憩時間もしくは退勤時間が不適切な値です',
            'new_break_out.*.after' => '休憩時間が不適切な値です',
            'new_break_out.*.required_with' => '休憩終了時間が未入力です',
            'new_break_out.date_format' => '休憩終了時間が不適切な値です',

        ];
    }
}
