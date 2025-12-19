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

        // ✅ ใช้ Local ก่อน (เก็บไฟล์ไว้ใน storage/app/...)
        // ถ้าอยากให้เปิดดูผ่าน URL ให้เปลี่ยนเป็น 'public' และรัน php artisan storage:link
        $disk = 'local';

        // ------------------------------------------------------------
        // 📝 (คอมเมนต์ไว้) ถ้าจะใช้ตามค่า .env ให้ใช้บรรทัดนี้แทน
        // $disk = config('filesystems.default'); // local / public / s3
        // ------------------------------------------------------------

        try {
            // 1) จัดเก็บภาพ Probe ไปยัง Local storage (คืนค่า path แน่นอน)
            // เก็บลง: storage/app/faces/probe/xxxxx.jpg
            $probePath = Storage::disk($disk)->putFile('faces/probe', $imageFile);

            // ------------------------------------------------------------
            // 📝 (คอมเมนต์ไว้) แบบเดิม/แบบ S3 (อย่าลบ เผื่อกลับไปใช้)
            // ❌ put() แบบเดิมบางเคสคืนค่า true/false ทำให้ probePath เพี้ยน
            // $probePath = Storage::disk(config('filesystems.default'))->put('faces/probe', $imageFile, 'public');
            //
            // ✅ ถ้าจะใช้ S3 แนะนำให้ใช้ putFile() + visibility option:
            // $disk = 's3';
            // $probePath = Storage::disk($disk)->putFile('faces/probe', $imageFile, ['visibility' => 'private']);
            // ------------------------------------------------------------

            // 2) จำลองการเรียก AI Service (OpenCV)
            $recognitionScore = rand(80, 99) / 100; // Score จำลอง 0.80 - 0.99

            // 3) ค้นหาผู้ใช้งานที่ตรงกันที่สุดใน FACE_MODELS
            // (ตอนนี้จำลอง: สุ่ม user active)
            $top1User = User::where('status', 'active')->inRandomOrder()->first();

            // กำหนดการตัดสินใจ (Threshold > 0.90)
            $decision = ($recognitionScore > 0.80) ? 'allow' : 'review';

            // 4) บันทึกผลการจดจำลงในตาราง RECOGNITION_LOGS
            RecognitionLog::create([
                'probe_s3_files' => [
                    // ✅ เก็บ path + disk ชัดเจน (แม้จะเป็น local)
                    'path' => $probePath,
                    'disk' => $disk,

                    // ------------------------------------------------------------
                    // 📝 (คอมเมนต์ไว้) ถ้าใช้ S3 จริง ค่อยเก็บ bucket/key เพิ่ม
                    // 'bucket' => config('filesystems.disks.s3.bucket'),
                    // ------------------------------------------------------------

                    // ค่าเดิมของคุณ (เก็บไว้ ไม่ลบ)
                    // 'raw' => $probePath,
                    // 'bucket' => config('filesystems.disks.s3.bucket') ?? 'local-mock',
                ],
                'score' => $recognitionScore,
                'top1_user_id' => $top1User->id ?? null,
                'model_name' => 'OpenCV',
                'decision' => $decision,
            ]);

            // 5) ตอบกลับผลการตัดสินใจ
            if ($decision === 'allow' && $top1User) {
                return response()->json([
                    'message' => 'User recognized successfully.',
                    'user_id' => $top1User->id,
                    'user_name' => $top1User->name,
                    'score' => $recognitionScore,
                    'decision' => $decision,
                    'probe' => [
                        'disk' => $disk,
                        'path' => $probePath,
                    ],
                ], 200);
            }

            return response()->json([
                'message' => 'Recognition failed or requires review.',
                'score' => $recognitionScore,
                'decision' => $decision,
                'probe' => [
                    'disk' => $disk,
                    'path' => $probePath,
                ],
            ], 403);

        } catch (\Exception $e) {
            if ($probePath) {
                Storage::disk($disk)->delete($probePath);
            }

            return response()->json([
                'message' => 'Server error during recognition.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
