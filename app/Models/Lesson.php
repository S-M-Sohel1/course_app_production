<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class Lesson extends Model
{
    protected $fillable = [
        'course_id',
        'user_token',
        'name',
        'thumbnail',
        'video',
        'description',
        'hls_processing'
    ];
    
    protected $casts = [
        'video' => 'json',
    ];
    
    protected static function booted()
    {
        // Delete all S3 files when lesson is deleted
        static::deleting(function ($lesson) {
            Log::info('Deleting lesson files from S3', ['lesson_id' => $lesson->id]);
            
            // Delete lesson thumbnail
            if ($lesson->thumbnail) {
                Storage::disk('s3')->delete($lesson->thumbnail);
                Log::info('Deleted thumbnail', ['path' => $lesson->thumbnail]);
            }
            
            // Delete all videos, thumbnails, and HLS segments
            if (is_array($lesson->video)) {
                foreach ($lesson->video as $index => $video) {
                    // Delete original video (if it still exists)
                    if (isset($video['url'])) {
                        Storage::disk('s3')->delete($video['url']);
                        Log::info('Deleted video', ['path' => $video['url']]);
                    }
                    
                    // Delete video thumbnail
                    if (isset($video['thumbnail'])) {
                        Storage::disk('s3')->delete($video['thumbnail']);
                        Log::info('Deleted video thumbnail', ['path' => $video['thumbnail']]);
                    }
                    
                    // Delete entire HLS directory for this video
                    if (isset($video['hls_playlist'])) {
                        $hlsDir = "hls/{$lesson->id}/{$index}/";
                        Storage::disk('s3')->deleteDirectory($hlsDir);
                        Log::info('Deleted HLS directory', ['path' => $hlsDir]);
                    }
                }
            }
        });
    }

    public function course()
    {
        return $this->belongsTo(\App\Models\Course::class);
    }

    // Override toArray to replace paths with full URLs
    public function toArray()
    {
        $array = parent::toArray();
        
        // Check if request is from API (return HLS) or admin panel (return MP4)
        $isApiRequest = request()->is('api/*');
        
        // Replace thumbnail path with full URL
        if (isset($array['thumbnail'])) {
            $array['thumbnail'] = Storage::disk('s3')->url($array['thumbnail']);
        }
        
        // Replace video paths with full URLs
        if (isset($array['video']) && is_array($array['video'])) {
            $array['video'] = array_map(function ($video) use ($isApiRequest) {
                // If HLS playlist exists, use it (for both API and admin)
                if (!empty($video['hls_playlist'])) {
                    return [
                        'name' => $video['name'] ?? '',
                        'thumbnail' => isset($video['thumbnail']) ? Storage::disk('s3')->url($video['thumbnail']) : null,
                        'url' => Storage::disk('s3')->url($video['hls_playlist']), // HLS playlist URL
                        'type' => 'hls', // Indicate this is HLS
                    ];
                }
                
                // Fallback to original MP4 if HLS not ready yet
                return [
                    'name' => $video['name'] ?? '',
                    'thumbnail' => isset($video['thumbnail']) ? Storage::disk('s3')->url($video['thumbnail']) : null,
                    'url' => isset($video['url']) ? Storage::disk('s3')->url($video['url']) : null,
                    'type' => 'processing', // HLS conversion in progress
                ];
            }, $array['video']);
        }
        
        // Remove sensitive encryption fields from API responses
        if ($isApiRequest) {
            unset($array['encryption_key']);
            unset($array['encryption_key_id']);
            unset($array['hls_playlist']);
            unset($array['hls_processing']);
        }
        
        return $array;
    }
}

/*
┌─────────────────────────────────────────────────────────────┐
│                    LARAVEL SERVER                           │
│                                                             │
│  ┌──────────────┐        ┌──────────────┐                 │
│  │   Database   │        │   S3 Bucket  │                 │
│  │              │        │              │                 │
│  │ encryption_  │        │ segment_000. │ ← Encrypted!    │
│  │ key: abc123  │        │ ts (encrypted)│                │
│  └──────────────┘        │ segment_001. │ ← Encrypted!    │
│         ↓                │ ts (encrypted)│                │
│  [Decrypts key]          │ playlist.m3u8│                 │
│         ↓                └──────────────┘                 │
│  Returns raw key                ↓                          │
│  (16 bytes)                     ↓                          │
└─────────────────────────────────────────────────────────────┘
                  ↓                         ↓
                  ↓                         ↓
         ┌────────────────────────────────────────┐
         │         FLUTTER APP (User Device)      │
         │                                        │
         │  1. Download playlist.m3u8             │
         │     → Reads: segments are AES-128      │
         │                                        │
         │  2. Request key from Laravel:          │
         │     GET /keys/abc123                   │
         │     ← Receives: [16 raw bytes]         │
         │                                        │
         │  3. Download segment_000.ts (encrypted)│
         │                                        │
         │  4. DECRYPT IN FLUTTER:                │
         │     ┌──────────────────────┐           │
         │     │ AES-128 Decryption   │           │
         │     │ Input: Encrypted seg │           │
         │     │ Key: 16 bytes        │           │
         │     │ Output: Raw video    │           │
         │     └──────────────────────┘           │
         │              ↓                         │
         │  5. Play decrypted video in player    │
         │                                        │
         └────────────────────────────────────────┘
-----------------------------------------------------------

┌─────────────────────────────────────────────────────────────┐
│                   FLUTTER APP (User)                        │
└─────────────────────────────────────────────────────────────┘
                          ↓
                  [Request video]
                          ↓
┌─────────────────────────────────────────────────────────────┐
│          CLOUDFRONT (CDN - Global Edge Locations)           │
│  • Caches encrypted HLS segments                           │
│  • Signed URLs/Cookies for authentication                  │
│  • Fast global delivery                                    │
└─────────────────────────────────────────────────────────────┘
                          ↓
                  [Cache miss → fetch from S3]
                          ↓
┌─────────────────────────────────────────────────────────────┐
│              S3 BUCKET (Origin Storage)                     │
│  • Stores encrypted HLS segments                           │
│  • segment_000.ts (AES-128 encrypted)                      │
│  • playlist.m3u8 (manifest)                                │
└─────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────┐
│          LARAVEL API (Key Server)                           │
│  • Validates user subscription                             │
│  • Provides decryption key                                 │
│  • Verifies purchase before key delivery                   │
└─────────────────────────────────────────────────────────────┘
*/