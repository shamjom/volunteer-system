<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [

            'event_id' => 'sometimes|exists:events,id',

            'title' => 'sometimes|string|max:255',

            'description' => 'nullable|string',

            'status' => 'sometimes|in:pending,in_progress,completed',

        ];
    }
}