<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MergeTrackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'target_id' => ['required', 'integer', 'exists:tracks,id', 'not_in:'.$this->route('track')->id],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'target_id.not_in' => 'A track can not be merged into itself.',
        ];
    }
}
