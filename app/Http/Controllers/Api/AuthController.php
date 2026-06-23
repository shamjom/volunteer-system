<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\StoreUserRequest;
use App\Models\User;
use App\Models\VolunteerProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{ 
    //اذا المستخدم عمل  تسجيل دخول من التطبيق رح يستنى الموافقة او الرفض من الادمن 
    public function login(LoginRequest $request)
    {
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['بيانات الدخول غير صحيحة.'],
            ]);
        }

        if ($user->status !== 'active') {
            return response()->json([
                'message' => 'Your account is not active yet.',
            ], 403);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'user' => $user->load('volunteerProfile'),
            'token' => $token,
        ]);
    }

    public function me(Request $request)
    {
        return response()->json(
            $request->user()->load('volunteerProfile')
        );
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }

    public function storeUser(StoreUserRequest $request)
    {
        $data = $request->validated();

        $status = $data['role'] === 'volunteer' ? 'pending' : 'active';

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'status' => $status,
        ]);

        if ($data['role'] === 'volunteer') {
            VolunteerProfile::create([
                'user_id' => $user->id,
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
                'total_hours' => 0,
                'status' => 'pending',
            ]);
        }

        return response()->json([
            'message' => 'User created successfully',
            'user' => $user->load('volunteerProfile'),
        ], 201);
    }

    public function approve(User $user)
    {
        if ($user->role !== 'volunteer') {
            return response()->json([
                'message' => 'Only volunteers can be approved.',
            ], 422);
        }

        $user->update(['status' => 'active']);

        $user->volunteerProfile()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'status' => 'active',
                'total_hours' => $user->volunteerProfile?->total_hours ?? 0,
                'phone' => $user->volunteerProfile?->phone,
                'address' => $user->volunteerProfile?->address,
            ]
        );

        return response()->json([
            'message' => 'Volunteer approved successfully',
            'user' => $user->load('volunteerProfile'),
        ]);
    }

    public function reject(User $user)
    {
        if ($user->role !== 'volunteer') {
            return response()->json([
                'message' => 'Only volunteers can be rejected.',
            ], 422);
        }

        $user->update(['status' => 'rejected']);

        $user->volunteerProfile()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'status' => 'rejected',
                'total_hours' => $user->volunteerProfile?->total_hours ?? 0,
                'phone' => $user->volunteerProfile?->phone,
                'address' => $user->volunteerProfile?->address,
            ]
        );

        return response()->json([
            'message' => 'Volunteer rejected successfully',
            'user' => $user->load('volunteerProfile'),
        ]);
    }
}