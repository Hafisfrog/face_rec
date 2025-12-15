<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;


class AuthController extends Controller
{
    /**
     * POST /api/auth/register: สร้างบัญชีผู้ใช้ใหม่
     */
   public function register(Request $request)
{
    $request->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|email|unique:users,email',
        'password' => 'required|string|min:6|confirmed',
    ]);

    $user = User::create([
        'id' => (string) \Str::uuid(),
        'name' => $request->name,
        'email' => $request->email,
        'password' => Hash::make($request->password),
        'status' => 'active',
    ]);

    $token = $user->createToken('auth_token')->plainTextToken;

    return response()->json([
        'message' => 'User registered successfully.',
        'access_token' => $token,
        'user_id' => $user->id,
        'token_type' => 'Bearer',
    ], 201);
}

    /**
     * POST /api/auth/login: ล็อกอินด้วย Username/ID
     */
    public function login(Request $request)
{
    $request->validate([
        'login' => 'required|string', // อาจเป็น name หรือ email ก็ได้
        'password' => 'required|string',
    ]);

    $loginField = filter_var($request->login, FILTER_VALIDATE_EMAIL) ? 'email' : 'name';

    $user = \App\Models\User::where($loginField, $request->login)->first();

    if (!$user || !Hash::check($request->password, $user->password)) {
        throw ValidationException::withMessages([
            'login' => ['Invalid credentials.'],
        ]);
    }

    $user->tokens()->delete();
    $token = $user->createToken('auth_token')->plainTextToken;

    return response()->json([
        'access_token' => $token,
        'user_id' => $user->id,
        'token_type' => 'Bearer',
    ]);
}

    /**
     * POST /api/auth/logout: ออกจากระบบ
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully.'], 200);
    }
}