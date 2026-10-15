<?php

namespace App\Http\Requests;

use App\Models\Comment;
use Illuminate\Foundation\Http\FormRequest;

class StoreCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:2000'],
            'timestamp_seconds' => ['required', 'integer', 'min:0'],
            'parent_id' => ['nullable', 'integer', 'exists:comments,id'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $liveset = $this->route('liveset');

            // Validate timestamp doesn't exceed liveset duration
            if ($liveset->duration_in_seconds && $this->timestamp_seconds > $liveset->duration_in_seconds) {
                $validator->errors()->add('timestamp_seconds', 'Timestamp exceeds liveset duration.');
            }

            // Validate parent_id belongs to same liveset and is top-level
            if ($this->parent_id) {
                $parent = Comment::find($this->parent_id);
                if ($parent) {
                    if ($parent->liveset_id !== $liveset->id) {
                        $validator->errors()->add('parent_id', 'Parent comment must belong to the same liveset.');
                    }
                    if ($parent->parent_id !== null) {
                        $validator->errors()->add('parent_id', 'Cannot reply to a reply.');
                    }
                }
            }
        });
    }
}
