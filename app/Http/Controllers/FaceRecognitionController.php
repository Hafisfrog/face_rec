<?php

namespace App\Http\Controllers;

use App\Models\RecognitionLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FaceRecognitionController extends Controller
{
    /**
     * API สำหรับการจดจำใบหน้า (รับภาพ Probe)
     */
    public function recognize(Request $request)
    {
        $request->validate([
            'image'  => 'required|image|max:10240',
            'folder' => 'nullable|string|max:50', // ใช้เฉพาะตอนเทส
        ]);

        $imageFile = $request->file('image');

        // ใช้ disk ตาม .env
        $disk = config('filesystems.default');
        $probePath = null;

        try {
            /* ------------------------------------------------------------
             | 1) Determine Folder (TEST MODE)
             |------------------------------------------------------------ */
            // ค่า default (flow จริง)
            $folder = 'unknown';

            // 🔥 ถ้าเป็น local / testing → อนุญาตส่ง folder มาเทส
            // if (app()->isLocal() && $request->filled('folder')) {
            //     // sanitize กัน path แปลก ๆ
            //     $folder = preg_replace('/[^a-zA-Z0-9_-]/', '', $request->input('folder'));
            // }
            if ($request->filled('folder')) {
            $folder = preg_replace('/[^a-zA-Z0-9_-]/', '', $request->input('folder'));
            }


            $uploadPath = "faces/probe/{$folder}";

            /* ------------------------------------------------------------
             | 2) Upload Probe Image
             |------------------------------------------------------------ */
            $probePath = Storage::disk($disk)->putFile(
                $uploadPath,
                $imageFile,
                [
                    'visibility' => $disk === 's3' ? 'private' : 'public',
                ]
            );

            // URL (debug / frontend)
            $probeUrl = null;
            if ($disk === 's3') {
                $probeUrl = Storage::disk('s3')->url($probePath);
            } elseif ($disk === 'public') {
                $probeUrl = asset('storage/' . $probePath);
            }

            /* ------------------------------------------------------------
             | 3) Mock Face Recognition (ยังไม่ใช้ AI จริง)
             |------------------------------------------------------------ */
            $recognitionScore = rand(80, 99) / 100;

            $top1User = User::where('status', 'active')
                ->inRandomOrder()
                ->first();

            $decision = ($recognitionScore > 0.80) ? 'allow' : 'review';

            /* ------------------------------------------------------------
             | 4) Save Recognition Log
             |------------------------------------------------------------ */
            RecognitionLog::create([
                'probe_s3_files' => [
                    'disk'   => $disk,
                    'path'   => $probePath,
                    'url'    => $probeUrl,
                    'folder' => $folder,
                    'bucket' => $disk === 's3'
                        ? config('filesystems.disks.s3.bucket')
                        : null,
                ],
                'score'        => $recognitionScore,
                'top1_user_id' => $top1User->id ?? null,
                'model_name'   => 'OpenCV',
                'decision'     => $decision,
            ]);

            /* ------------------------------------------------------------
             | 5) Response
             |------------------------------------------------------------ */
            return response()->json([
                'message'  => $decision === 'allow'
                    ? 'User recognized successfully.'
                    : 'Recognition requires review.',
                'user_id'   => $top1User->id ?? null,
                'user_name' => $top1User->name ?? null,
                'score'     => $recognitionScore,
                'decision'  => $decision,
                'probe'     => [
                    'disk'   => $disk,
                    'folder' => $folder,
                    'path'   => $probePath,
                    'url'    => $probeUrl,
                ],
            ], 200);

        } catch (\Throwable $e) {
            // rollback file ถ้ามี error
            if ($probePath && Storage::disk($disk)->exists($probePath)) {
                Storage::disk($disk)->delete($probePath);
            }

            return response()->json([
                'message' => 'Server error during recognition.',
                'error'   => app()->isLocal()
                    ? $e->getMessage()
                    : 'Internal Server Error',
            ], 500);
        }
    }
}
