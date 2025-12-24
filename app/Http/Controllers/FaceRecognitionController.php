<?php

namespace App\Http\Controllers;

use App\Models\RecognitionLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;

class FaceRecognitionController extends Controller
{
    /**
     * API สำหรับการจดจำใบหน้า (Probe)
     */
    public function recognize(Request $request)
    {
        $request->validate([
            'image'  => 'required|image|max:10240',
            'folder' => 'nullable|string|max:50', // ใช้เฉพาะตอนเทส
        ]);

        $imageFile = $request->file('image');
        $disk = config('filesystems.default');

        $probePath = null;
        $tempLocalPath = null;

        try {
            /* ------------------------------------------------------------
             | 1) Determine folder (TEST MODE ONLY)
             |------------------------------------------------------------ */
            $folder = 'unknown';

            if (app()->isLocal() && $request->filled('folder')) {
                $folder = preg_replace('/[^a-zA-Z0-9_-]/', '', $request->input('folder'));
            }

            $uploadPath = "faces/probe/{$folder}";

            /* ------------------------------------------------------------
             | 2) Upload image (S3 / local ตาม config)
             |------------------------------------------------------------ */
            $probePath = Storage::disk($disk)->putFile(
                $uploadPath,
                $imageFile,
                [
                    'visibility' => $disk === 's3' ? 'private' : 'public',
                ]
            );

            // URL (ไว้ debug / frontend)
            $probeUrl = null;
            if ($disk === 's3') {
                $probeUrl = Storage::disk('s3')->url($probePath);
            } elseif ($disk === 'public') {
                $probeUrl = asset('storage/' . $probePath);
            }

            /* ------------------------------------------------------------
             | 3) Prepare local temp file for AI
             |------------------------------------------------------------ */
            $tempLocalPath = $imageFile->store('faces/tmp', 'local');
            $fullTempPath = storage_path('app/' . $tempLocalPath);

            /* ------------------------------------------------------------
             | 4) Call AI API (Flask)
             |------------------------------------------------------------ */
            $aiResponse = Http::attach(
                'image',
                file_get_contents($fullTempPath),
                $imageFile->getClientOriginalName()
            )->timeout(10)->post('http://127.0.0.1:5001/recognize');

            if (!$aiResponse->ok()) {
                throw new \Exception('AI service error: ' . $aiResponse->body());
            }

            $aiResult = $aiResponse->json();

            if (!isset($aiResult['score'])) {
                throw new \Exception('Invalid AI response');
            }

            /* ------------------------------------------------------------
             | 5) Decision (Backend เป็นคนตัดสิน)
             |------------------------------------------------------------ */
            $score = $aiResult['score'];
            $decision = $score >= 0.85 ? 'allow' : 'review';

            $top1User = User::where('status', 'active')
                ->inRandomOrder()
                ->first();

            /* ------------------------------------------------------------
             | 6) Save recognition log
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
                'score'        => $score,
                'top1_user_id' => $top1User->id ?? null,
                'model_name'   => 'OpenCV',
                'decision'     => $decision,
            ]);

            /* ------------------------------------------------------------
             | 7) Cleanup temp file
             |------------------------------------------------------------ */
            if ($tempLocalPath && Storage::disk('local')->exists($tempLocalPath)) {
                Storage::disk('local')->delete($tempLocalPath);
            }

            /* ------------------------------------------------------------
             | 8) Response
             |------------------------------------------------------------ */
            return response()->json([
                'message'   => $decision === 'allow'
                    ? 'User recognized successfully.'
                    : 'Recognition requires review.',
                'user_id'   => $top1User->id ?? null,
                'user_name' => $top1User->name ?? null,
                'score'     => $score,
                'decision'  => $decision,
                'probe'     => [
                    'disk'   => $disk,
                    'folder' => $folder,
                    'path'   => $probePath,
                    'url'    => $probeUrl,
                ],
                'ai_raw' => $aiResult, // เอาออกได้ตอน prod
            ], 200);

        } catch (\Throwable $e) {
            // rollback
            if ($probePath && Storage::disk($disk)->exists($probePath)) {
                Storage::disk($disk)->delete($probePath);
            }

            if ($tempLocalPath && Storage::disk('local')->exists($tempLocalPath)) {
                Storage::disk('local')->delete($tempLocalPath);
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
