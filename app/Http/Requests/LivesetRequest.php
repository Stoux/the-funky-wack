<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

abstract class LivesetRequest extends FormRequest
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
    public function rules(): array
    {
        return [
            'edition_id' => 'required|exists:editions,id',
            'title' => 'required|string|max:255',
            'artist_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'bpm' => 'nullable|string',
            'genre' => 'nullable|string|max:255',
            'duration_in_seconds' => 'nullable|integer',
            'started_at' => 'nullable|date',
            'lineup_order' => 'nullable|integer',
            'soundcloud_url' => 'nullable|string|max:255',
            'audio_waveform_path' => 'nullable|string|max:255',
            'tracks_text' => 'nullable|string',
        ];
    }

    /**
     * Validate and parse tracks from text format
     *
     * @param string|null $tracksText
     * @return array
     * @throws \Illuminate\Validation\ValidationException
     */
    public function validateAndParseTracks($tracksText)
    {
        if (empty($tracksText)) {
            return [];
        }

        $tracks = [];
        $order = 1;
        $lines = explode("\n", $tracksText);

        foreach ($lines as $lineNumber => $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            // Format without timestamps
            if (preg_match('/^--:--:--\s*\|\s*(?P<title>.+)$/', $line, $matches)) {
                $tracks[] = [
                    'timestamp' => null,
                    'title' => $matches['title'],
                    'order' => $order++,
                ];

                continue;
            }

            // Validate format: [hh]:[mm]:[ss] | {title/name}
            if (!preg_match('/^(\d{1,2}):(\d{2}):(\d{2})\s*\|\s*(.+)$/', $line, $matches)) {
                $lineNumber++; // Make it 1-based for user-friendly error message
                throw ValidationException::withMessages([
                    'tracks_text' => ["Line {$lineNumber}: Invalid track format. Expected format: [hh]:[mm]:[ss] | {title/name}"]
                ])->status(429);
            }

            $hours = (int)$matches[1];
            $minutes = (int)$matches[2];
            $seconds = (int)$matches[3];
            $title = trim($matches[4]);

            // Validate time components
            if ($minutes >= 60 || $seconds >= 60) {
                $lineNumber++; // Make it 1-based for user-friendly error message
                throw ValidationException::withMessages([
                    'tracks_text' => ["Line {$lineNumber}: Invalid time format. Minutes and seconds must be less than 60."]
                ])->status(429);
            }

            // Convert timestamp to seconds
            $timestampInSeconds = $hours * 3600 + $minutes * 60 + $seconds;

            $tracks[] = [
                'timestamp' => $timestampInSeconds,
                'title' => $title,
                'order' => $order++,
            ];
        }

        return $tracks;
    }
}
