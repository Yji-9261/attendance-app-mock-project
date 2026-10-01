<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ExportCsvRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->user()->admin_status;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer'],
            'year_month' => ['required', 'date_format:Y-m'],
        ];
    }

    public function messages()
    {
        return [
            'user_id.required' => 'ユーザーIDが未入力です',
            'user_id.integer' => 'ユーザーIDは数値を入力してください',

            'year_month.required' => '年月が未入力です',
            'year_month.date_format' => 'YYYY-MM 形式で入力してください',
        ];
    }
}
