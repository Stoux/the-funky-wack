<?php

namespace App\Http\Requests;

use App\Models\Edition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;

abstract class EditionRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(Request $request): array
    {
        $edition = $request->route()->parameter('edition');
        $editionRule = $edition instanceof Edition ? sprintf(',%d', $edition->id) : '';

        return [
            'number' => 'required|integer|unique:editions,number' . $editionRule,
            'tag_line' => 'required|string|max:255',
            'date' => 'required|date',
            'notes' => 'nullable|string',
            'empty_note' => 'nullable|string|max:255',
            'timetabler_mode' => 'boolean',
        ];
    }
}
