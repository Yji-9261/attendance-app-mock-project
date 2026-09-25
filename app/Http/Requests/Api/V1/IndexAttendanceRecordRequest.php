<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class IndexAttendanceRecordRequest extends FormRequest
{

    private const MAX_PER_PAGE = 100;
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
            'user_id' => ['nullable', 'integer', 'min:1'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'month' => ['nullable', 'date_format:Y-m'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:' . self::MAX_PER_PAGE,
            ],
        ];
    }

    public function messages()
    {
        return [
            'user_id.integer' => '数値を入力してください',
            'user_id.min' => '1以上の数値を入力してください',

            'date.date_format' => 'YYYY-MM-DD 形式で指定してください。',

            'month.date_format' => 'YYYY-MM 形式で指定してください。',

            'page.integer' => '数値を入力してください',
            'page.min' => '1以上の数値を入力してください',

            'per_page.integer' => '数値を入力してください',
            'per_page.max' => ':max以下の数値を入力してください',
            'per_page.min' => '1以上の数値を入力してください',
        ];
    }
}
