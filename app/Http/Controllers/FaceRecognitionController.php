<?php

namespace App\Http\Controllers;

use App\Models\FaceModel;
use App\Models\RecognitionLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FaceRecognitionController extends Controller
{
    /**
     * API สำหรับการจดจำใบหน้า (รับภาพ Probe)
     */
    public function recognize(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:10240', // ภาพที่ใช้ตรวจสอบ
        ]);

        $imageFile = $request->file('image');
        $probePath = null;
        
        try {
            // 1. จัดเก็บภาพ Probe ไปยัง S3 (หรือ Local storage)
            $probePath = Storage::disk(config('filesystems.default'))->put('faces/probe', $imageFile, 'public'); 

            // 2. จำลองการเรียก AI Service (OpenCV)
            $recognitionScore = rand(80, 99) / 100; // Score จำลอง 0.80 - 0.99
            
            // 3. ค้นหาผู้ใช้งานที่ตรงกันที่สุดใน FACE_MODELS
            $top1User = User::where('status', 'active')->inRandomOrder()->first();
            
            // กำหนดการตัดสินใจ (Threshold > 0.90)
            $decision = ($recognitionScore > 0.90) ? 'allow' : 'review'; 
            
            // 4. บันทึกผลการจดจำลงในตาราง RECOGNITION_LOGS
            RecognitionLog::create([
                'probe_s3_files' => [
                    'raw' => $probePath,
                    'bucket' => config('filesystems.disks.s3.bucket') ?? 'local-mock',
                ],
                'score' => $recognitionScore,
                'top1_user_id' => $top1User->id ?? null,
                'model_name' => 'OpenCV',
                'decision' => $decision,
            ]);

            // 5. ตอบกลับผลการตัดสินใจ
            if ($decision === 'allow' && $top1User) {
                return response()->json([
                    'message' => 'User recognized successfully.',
                    'user_id' => $top1User->id,
                    'user_name' => $top1User->name,
                    'score' => $recognitionScore,
                    'decision' => $decision,
                ], 200);
            }
            
            return response()->json([
                'message' => 'Recognition failed or requires review.',
                'score' => $recognitionScore,
                'decision' => $decision,
            ], 403); 

        } catch (\Exception $e) {
            if ($probePath) {
                 Storage::disk(config('filesystems.default'))->delete($probePath);
            }
            return response()->json(['message' => 'Server error during recognition.', 'error' => $e->getMessage()], 500);
        }
    }
}