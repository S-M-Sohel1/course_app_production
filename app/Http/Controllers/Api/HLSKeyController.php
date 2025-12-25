<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\Course;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class HLSKeyController extends Controller
{
    public function getKey($keyId)
    {
        try {
            Log::info('HLS Key Request', ['keyId' => $keyId]);
            
            // Find lesson that has a video with this encryption key ID
            $lesson = Lesson::whereRaw("JSON_SEARCH(video, 'one', ?) IS NOT NULL", [$keyId])->first();

            if (!$lesson) {
                Log::warning('Lesson not found for key', ['keyId' => $keyId]);
                abort(404, 'Key not found');
            }

            Log::info('Lesson found', ['lesson_id' => $lesson->id, 'video_count' => count($lesson->video ?? [])]);

            // Find the specific video with this key ID
            $encryptionKey = null;
            foreach ($lesson->video as $video) {
                if (isset($video['encryption_key_id']) && $video['encryption_key_id'] === $keyId) {
                    $encryptionKey = $video['encryption_key'];
                    Log::info('Encryption key found', ['keyId' => $keyId]);
                    break;
                }
            }

            if (!$encryptionKey) {
                Log::error('Encryption key not found in video array', ['keyId' => $keyId, 'videos' => $lesson->video]);
                abort(404, 'Encryption key not found');
            }

            // Verify user is authenticated
            // if (!Auth::guard('sanctum')->check()) {
            //     abort(401, 'Unauthorized');
            // }

            // $user = Auth::guard('sanctum')->user();

            // Verify user has purchased the course (using optimized JOIN approach)
            // $hasPurchased = Course::join('orders', 'courses.id', '=', 'orders.course_id')
            //     ->where('orders.user_token', '=', $user->token)
            //     ->where('orders.status', '=', 1)
            //     ->where('courses.id', '=', $lesson->course_id)
            //     ->exists();

            // if (!$hasPurchased) {
            //     abort(403, 'Access denied - Course not purchased');
            // }

            // Decrypt and return the encryption key
            Log::info('Attempting to decrypt key', ['encrypted_length' => strlen($encryptionKey)]);
            $decryptedKey = Crypt::decrypt($encryptionKey);
            Log::info('Key decrypted successfully', [
                'decrypted_length' => strlen($decryptedKey),
                'decrypted_hex' => bin2hex($decryptedKey),
                'is_binary' => ctype_print($decryptedKey) ? 'no' : 'yes'
            ]);

            // AES-128 requires exactly 16 bytes
            if (strlen($decryptedKey) !== 16) {
                Log::error('Invalid key size', [
                    'expected' => 16,
                    'actual' => strlen($decryptedKey),
                    'hex' => bin2hex($decryptedKey)
                ]);
                abort(500, 'Invalid encryption key size');
            }

            // Return raw binary key with CORS headers
            // Chrome ORB requires proper CORS for cross-origin binary responses
            return response($decryptedKey, 200)
                ->header('Content-Type', 'application/octet-stream')
                ->header('Content-Length', strlen($decryptedKey))
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
                ->header('Access-Control-Allow-Headers', '*')
                ->header('Access-Control-Expose-Headers', 'Content-Length')
                ->header('Cross-Origin-Resource-Policy', 'cross-origin')
                ->header('X-Content-Type-Options', 'nosniff');
                
        } catch (\Exception $e) {
            Log::error('HLS Key Error', [
                'keyId' => $keyId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Internal server error',
                'message' => $e->getMessage(),
                'keyId' => $keyId
            ], 500);
        }
    }
}
