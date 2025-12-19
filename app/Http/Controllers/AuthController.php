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
     * POST /api/auth/register: สร้างบัญชีผู้ใช้ใหม่พร้อมกฎความปลอดภัยรหัสผ่าน
     */
    public function register(Request $request)
    {
        // 1. Validation: กำหนดกฎสำหรับรหัสผ่าน (ตัวเล็ก, ตัวใหญ่, ขั้นต่ำ 6 ตัว)
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => [
                'required',
                'string',
                'min:6',
                'confirmed',
                'regex:/[a-z]/',      // ต้องมีตัวพิมพ์เล็กอย่างน้อย 1 ตัว
                'regex:/[A-Z]/',      // ต้องมีตัวพิมพ์ใหญ่อย่างน้อย 1 ตัว
            ],
        ], [
            // กำหนดข้อความแจ้งเตือนภาษาอังกฤษ (หรือเปลี่ยนเป็นภาษาไทยได้ตามต้องการ)
            'password.regex' => 'Password must contain at least one uppercase and one lowercase letter.',
        ]);

        // 2. สร้าง User โดยใช้ UUID และ Hash รหัสผ่าน
        $user = User::create([
            'id' => (string) Str::uuid(),
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'status' => 'active',
        ]);

        // 3. สร้าง Access Token สำหรับใช้งาน API
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'User registered successfully.',
            'access_token' => $token,
            'user_id' => $user->id,
            'token_type' => 'Bearer',
        ], 201);
    }

    /**
     * POST /api/auth/login: ล็อกอินได้ทั้ง Email หรือ Username
     */
    public function login(Request $request)
    {
        // รับค่า login (ซึ่งอาจเป็น email หรือ name) และ password
        $request->validate([
            'login' => 'required|string',
            'password' => 'required|string',
        ]);

        $login = $request->login;

        // ค้นหาผู้ใช้จาก email หรือ name
        $user = User::where('email', $login)
            ->orWhere('name', $login)
            ->first();

        // ตรวจสอบตัวตนและรหัสผ่าน
        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'login' => ['Invalid credentials.'],
            ]);
        }

        // ลบ Token เก่าทิ้งเพื่อความปลอดภัยและออก Token ใหม่
        $user->tokens()->delete();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'user_id' => $user->id,
            'token_type' => 'Bearer',
        ]);
    }

    /**
     * POST /api/auth/logout: ออกจากระบบและยกเลิก Token ปัจจุบัน
     */
    public function logout(Request $request)
    {
        // ลบ Token ที่ใช้ในการเข้าถึงปัจจุบัน
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully.'
        ], 200);
    }
}