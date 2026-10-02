<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ApplicationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * 
     * @return bool 常にtrue
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * バリデーション前の処理
     * 
     * @return void
     */
    protected function prepareForValidation(): void
    {
        // 時間記入欄は全角半角許容
        $this->merge([
            'new_clock_in' => mb_convert_kana($this->new_clock_in, 'a'),
            'new_clock_out' => mb_convert_kana($this->new_clock_out, 'a'),
            'new_break_in' => collect($this->new_break_in)
                ->map(fn($break_in) => $break_in !== null
                    ? mb_convert_kana($break_in, 'a')
                    : null)
                ->toArray(),

            'new_break_out' => collect($this->new_break_out)
                ->map(fn($break_out) => $break_out !== null
                    ? mb_convert_kana($break_out, 'a')
                    : null)
                ->toArray(),
        ]);
    }

    /**
     * バリデーションルール
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
                'after_or_equal:new_clock_in',
                'date_format:H:i',
            ],

            'comment' => [
                'required',
                'string',
                'max:255',
            ],

            'new_break_in' => ['array'],
            'new_break_in.*' => [
                'nullable',
                'required_with:new_break_out.*',
                'after_or_equal:new_clock_in',
                'before_or_equal:new_clock_out',
                'before_or_equal:new_break_out.*',
                'date_format:H:i',
            ],

            'new_break_out' => ['array'],
            'new_break_out.*' => [
                'nullable',
                'required_with:new_break_in.*',
                'before_or_equal:new_clock_out',
                'date_format:H:i',
            ],
        ];
    }

    /**
     * バリデーションメッセージ
     * 
     * @return array{"comment.max": string, "comment.required": string, "new_break_in.*.after_or_equal": string, "new_break_in.*.before_or_equal": string, "new_break_in.*.date_format": string, "new_break_in.*.required_with": string, "new_break_out.*.before_or_equal": string, "new_break_out.*.date_format": string, "new_break_out.*.required_with": string, "new_clock_in.date_format": string, "new_clock_in.required": string, "new_clock_out.after_or_equal": string, "new_clock_out.date_format": string, "new_clock_out.required": string}
     */
    public function messages(): array
    {
        return [
            'new_clock_in.required' => '出勤時間が未入力です',
            'new_clock_in.date_format' => '出勤時間が不適切な値です',

            'new_clock_out.required' => '退勤時間が未入力です',
            'new_clock_out.after_or_equal' => '出勤時間もしくは退勤時間が不適切な値です',
            'new_clock_out.date_format' => '退勤時間が不適切な値です',

            'comment.required' => '備考を記入してください',
            'comment.max' => '最大255文字までです',

            'new_break_in.*.after_or_equal' => '休憩時間が不適切な値です',
            'new_break_in.*.before_or_equal' => '休憩時間が不適切な値です',
            'new_break_in.*.required_with' => '休憩開始時間が未入力です',
            'new_break_in.*.date_format' => '休憩開始時間が不適切な値です',

            'new_break_out.*.before_or_equal' => '休憩時間もしくは退勤時間が不適切な値です',
            'new_break_out.*.required_with' => '休憩終了時間が未入力です',
            'new_break_out.*.date_format' => '休憩終了時間が不適切な値です',

        ];
    }
}
