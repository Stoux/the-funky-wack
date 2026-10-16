<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Link a tracklist line to existing track(s), and/or a new one. Admin routes are already behind the admin middleware.
 */
class LinkTrackAliasRequest extends FormRequest
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
            'track_ids' => ['present', 'array', 'max:5'],
            'track_ids.*' => ['integer', 'distinct', 'exists:tracks,id'],
            'new_track' => ['nullable', 'array'],
            'new_track.artist' => ['required_with:new_track', 'string', 'max:255'],
            'new_track.title' => ['required_with:new_track', 'string', 'max:255'],
            'new_track.version' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'track_ids.max' => 'A line can link to at most 5 tracks.',
            'new_track.artist.required_with' => 'The new track needs an artist.',
            'new_track.title.required_with' => 'The new track needs a title.',
        ];
    }
}
