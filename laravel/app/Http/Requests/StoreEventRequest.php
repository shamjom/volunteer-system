<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            
            'title' => 'required|string|max:255',

            'description' => 'nullable|string',

            'location' => 'required|string|max:255',

            'start_date' => 'required|date',

            'end_date' => 'required|date|after:start_date',

            'max_volunteers' => 'required|integer|min:1',

            'status' => 'required|in:open,closed,completed',

        ];
    }
}