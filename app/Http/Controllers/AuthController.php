<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash; 
use Illuminate\Support\Facades\Auth; 

class AuthController extends Controller
{
    /**
     * POST /api/auth/register: สร้างบัญชีผู้ใช้ใหม่
     */
    public function register(Request $request)
    {
        // 1. Validation: ต้องมี name, email (unique), password (confirmed)
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email', // ตรวจสอบ email ซ้ำ
            'password' => [
                'required',
                'string',
                'min:6',
                'confirmed',
                'regex:/[a-z]/',      // ต้องมีตัวพิมพ์เล็กอย่างน้อย 1 ตัว
                'regex:/[A-Z]/',      // ต้องมีตัวพิมพ์ใหญ่อย่างน้อย 1 ตัว
            ],
        ], [
            'password.regex' => 'Password must contain at least one uppercase and one lowercase letter.',
        ]);

        // 2. สร้าง User โดย Hash password และใช้ UUID
        $user = User::create([
            'id' => (string) Str::uuid(), 
            'name' => $request->name,
            'email' => $request->email, 
            'password' => Hash::make($request->password), // Hash password
            'status' => 'active',
        ]);

        // 3. สร้าง Access Token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'User registered successfully.',
            'access_token' => $token,
            'user_id' => $user->id,
            'token_type' => 'Bearer',
        ], 201); // 201 Created
    }

    /**
     * POST /api/auth/login: ล็อกอินด้วย Email/Password
     */
    public function login(Request $request)
    {
        $request->validate([
            'login' => 'required|string',
            'password' => 'required|string',
        ]);

        $login = $request->login;

        $user = User::where('email', $login)
            ->orWhere('name', $login)
            ->first();

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
        // ลบ Token ปัจจุบันที่กำลังใช้งานอยู่
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully.'], 200);
    }
}