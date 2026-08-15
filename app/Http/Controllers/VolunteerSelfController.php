<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class VolunteerSelfController extends Controller
{
    public function profile(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'volunteer') {
            return response()->json([
                'message' => 'Only volunteers can access this endpoint.'
            ], 403);
        }

        return response()->json([
            'user' => $user->load('volunteerProfile')
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'volunteer') {
            return response()->json([
                'message' => 'Only volunteers can access this endpoint.'
            ], 403);
        }

        $data = $request->validate([
          'name' => 'sometimes|required|string|max:255',
          'email' => 'sometimes|required|email|unique:users,email,' . $user->id,
          'phone' => 'sometimes|nullable|string|max:50',
          'address' => 'sometimes|nullable|string|max:255',
          'interests' => 'sometimes|array',
          'preferred_hours_per_week' =>'sometimes|nullable|integer|min:0|max:168',
          'notifications_enabled' =>'sometimes|boolean',
          'language' =>'sometimes|in:ar,en',
          'avatar' =>'sometimes|nullable|string|max:500',
         ]);

        $user->update([
            'name' => $data['name'] ?? $user->name,
            'email' => $data['email'] ?? $user->email,
        ]);

        $user->volunteerProfile()->update([
            'phone' => $data['phone'] ?? $user->volunteerProfile?->phone,
            'address' => $data['address'] ?? $user->volunteerProfile?->address,
            'interests' =>$data['interests'] ?? $user->volunteerProfile?->interests,
            'preferred_hours_per_week' =>$data['preferred_hours_per_week']?? $user->volunteerProfile?->preferred_hours_per_week,
            'notifications_enabled' =>$data['notifications_enabled']?? $user->volunteerProfile?->notifications_enabled,
            'language' =>$data['language']?? $user->volunteerProfile?->language,
            'avatar' =>$data['avatar']?? $user->volunteerProfile?->avatar,
        ]);

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user' => $user->fresh()->load('volunteerProfile'),
        ]);
    }
}