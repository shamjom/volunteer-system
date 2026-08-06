<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [

            'event_id' => 'required|exists:events,id',

            'title' => 'required|string|max:255',

            'description' => 'nullable|string',

            'status' => 'required|in:pending,in_progress,completed',

        ];
    }
}