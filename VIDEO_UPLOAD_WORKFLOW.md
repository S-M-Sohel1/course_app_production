# Video Upload & HLS Conversion Workflow Documentation

## Table of Contents
- [Overview](#overview)
- [Architecture](#architecture)
- [Workflow: Creating a Lesson with Videos](#workflow-creating-a-lesson-with-videos)
- [Workflow: Editing a Lesson (Replace/Swap Videos)](#workflow-editing-a-lesson-replaceswap-videos)
- [Workflow: Deleting a Lesson](#workflow-deleting-a-lesson)
- [HLS Conversion Job Details](#hls-conversion-job-details)
- [File Storage Locations](#file-storage-locations)
- [Security Features](#security-features)
- [Error Handling](#error-handling)
- [Configuration](#configuration)

---

## Overview

The system implements a secure video delivery system using **HLS (HTTP Live Streaming)** with **AES-128 encryption**. Videos uploaded by admins are automatically converted to encrypted HLS format and stored in AWS S3. Original MP4 files are **never stored in the cloud**, ensuring better security and reduced storage costs.

### Key Features
- ✅ **No S3 Round-Trip**: Videos processed locally before cloud upload
- ✅ **HLS Encryption**: AES-128 encryption for video segments
- ✅ **Automatic Cleanup**: Old HLS files deleted when replaced
- ✅ **Multiple Videos**: Support for multiple videos per lesson
- ✅ **Background Processing**: Queue jobs for video conversion
- ✅ **Bandwidth Optimization**: Only encrypted segments uploaded to S3

---

## Architecture

```
┌─────────────────┐
│  Admin Panel    │
│  (Filament)     │
└────────┬────────┘
         │ Upload MP4
         ▼
┌─────────────────────────────────────────┐
│  Livewire Temporary Storage             │
│  storage/app/private/livewire-tmp/      │
└────────┬────────────────────────────────┘
         │ Filament saves
         ▼
┌─────────────────────────────────────────┐
│  Local Disk Storage                     │
│  storage/app/lesson-videos-temp/        │
└────────┬────────────────────────────────┘
         │ afterCreate/afterSave
         ▼
┌─────────────────────────────────────────┐
│  Permanent Temp for Job Processing      │
│  storage/app/video-processing/          │
│  lesson_13_video_0_abc123_file.mp4      │
└────────┬────────────────────────────────┘
         │ Queue Job Dispatched
         ▼
┌─────────────────────────────────────────┐
│  ConvertVideoToHLS Job                  │
│  1. Generate encryption key             │
│  2. FFmpeg: MP4 → HLS segments          │
│  3. Upload to S3: hls/{lessonId}/{idx}/ │
│  4. Delete local temp file              │
└────────┬────────────────────────────────┘
         │ Upload encrypted segments
         ▼
┌─────────────────────────────────────────┐
│  AWS S3 Bucket                          │
│  my-course-app-videos                   │
│  ├── hls/                               │
│  │   └── 13/                            │
│  │       ├── 0/                         │
│  │       │   ├── playlist.m3u8          │
│  │       │   ├── segment_000.ts         │
│  │       │   ├── segment_001.ts         │
│  │       │   └── ...                    │
│  │       └── 1/                         │
│  └── video-thumbnails/                  │
└─────────────────────────────────────────┘
```

---

## Workflow: Creating a Lesson with Videos

### Step 1: Admin Uploads Video
**Location**: Filament Admin Panel → Lessons → Create

```
User selects video file(s) → FileUpload component
```

**Configuration** (`app/Filament/Resources/Lessons/Schemas/LessonForm.php`):
```php
FileUpload::make('url')
    ->label('video')
    ->maxSize(512000)  // 500MB max
    ->disk('local')    // Store locally, NOT S3
    ->directory('lesson-videos-temp')
    ->acceptedFileTypes(['video/mp4', 'video/mov', 'video/avi', ...])
```

**Result**: File saved to `storage/app/lesson-videos-temp/xyz.mp4`

---

### Step 2: Form Submission
**Trigger**: Admin clicks "Create" button

**Process** (`app/Filament/Resources/Lessons/Pages/CreateLesson.php`):

```php
protected function afterCreate(): void
{
    foreach ($this->record->video as $index => $video) {
        // 1. Get Livewire temp file path
        $localPath = Storage::disk('local')->path($video['url']);
        
        // 2. Copy to PERMANENT temp location
        $permanentTempPath = storage_path(
            "app/video-processing/lesson_{$lessonId}_video_{$index}_" 
            . uniqid() . "_" . basename($localPath)
        );
        copy($localPath, $permanentTempPath);
        
        // 3. Dispatch background job
        ConvertVideoToHLS::dispatch(
            $lessonId, 
            $permanentTempPath,
            $index,
            basename($localPath)
        );
    }
    
    // 4. Mark as processing
    $lesson->update(['hls_processing' => true]);
}
```

**Why copy to permanent temp?**
- Livewire may auto-clean temp files before job runs
- Prevents race conditions if admin edits again quickly
- Unique filename prevents conflicts

---

### Step 3: Background Job Processing
**Job**: `app/Jobs/ConvertVideoToHLS.php`

**Queue Configuration**:
- **Driver**: Database (`config/queue.php`)
- **Timeout**: 3600 seconds (1 hour)
- **Worker**: `php artisan queue:work`

**Job Flow**:

```php
public function handle(): void
{
    // 1. Verify local file exists
    if (!file_exists($this->localVideoPath)) {
        throw new Exception("File not found");
    }
    
    // 2. Generate encryption components
    $encryptionKey = random_bytes(16);        // AES-128 key
    $keyId = Str::ulid()->toString();         // Unique key ID
    $iv = bin2hex(random_bytes(16));          // Initialization vector
    
    // 3. Create temp directories
    $tempDir = storage_path("app/temp/{$lessonId}_{$videoIndex}/");
    $hlsOutputDir = storage_path("app/hls/{$lessonId}_{$videoIndex}/");
    
    // 4. Create keyinfo file for FFmpeg
    file_put_contents($keyInfoPath, implode("\n", [
        url("/api/hls/keys/{$keyId}"),  // Key URL
        $keyPath,                        // Local key file
        $iv                              // IV
    ]));
    
    // 5. FFmpeg conversion
    $this->convertToHLS($localVideoPath, $hlsOutputDir, $keyInfoPath);
    
    // 6. Upload HLS files to S3
    $playlistPath = $this->uploadHLSToS3($hlsOutputDir, $lessonId, $videoIndex);
    
    // 7. Update database
    $videoData[$videoIndex]['hls_playlist'] = $playlistPath;
    $videoData[$videoIndex]['encryption_key_id'] = $keyId;
    $videoData[$videoIndex]['encryption_key'] = Crypt::encrypt($encryptionKey);
    $videoData[$videoIndex]['original_filename'] = $originalFilename;
    unset($videoData[$videoIndex]['url']); // Remove local path
    
    // 8. Cleanup local files
    unlink($this->localVideoPath);  // Delete permanent temp file
    $this->cleanup($tempDir, $hlsOutputDir);
    
    // 9. Mark as complete
    if ($allProcessed) {
        $lesson->update(['hls_processing' => false]);
    }
}
```

---

### Step 4: FFmpeg Conversion

**Command**:
```bash
ffmpeg -i input.mp4 \
  -c:v libx264 \
  -c:a aac \
  -hls_time 10 \
  -hls_key_info_file keyinfo.txt \
  -hls_playlist_type vod \
  -hls_segment_filename segment_%03d.ts \
  playlist.m3u8
```

**Parameters**:
- `-c:v libx264`: H.264 video codec
- `-c:a aac`: AAC audio codec
- `-hls_time 10`: 10-second segments
- `-hls_key_info_file`: Encryption configuration
- `-hls_playlist_type vod`: Video on demand (not live stream)

**Output**:
```
storage/app/hls/13_0/
├── playlist.m3u8
├── segment_000.ts
├── segment_001.ts
├── segment_002.ts
└── ...
```

---

### Step 5: S3 Upload

**Code**:
```php
private function uploadHLSToS3($localDir, $lessonId, $videoIndex): string
{
    $files = glob($localDir . '*');
    
    foreach ($files as $file) {
        $filename = basename($file);
        $s3Path = "hls/{$lessonId}/{$videoIndex}/{$filename}";
        
        Storage::disk('s3')->put(
            $s3Path,
            file_get_contents($file),
            'public'
        );
    }
    
    return "hls/{$lessonId}/{$videoIndex}/playlist.m3u8";
}
```

**S3 Structure**:
```
my-course-app-videos/
└── hls/
    └── 13/                    # Lesson ID
        ├── 0/                 # Video index 0
        │   ├── playlist.m3u8
        │   ├── segment_000.ts
        │   └── ...
        └── 1/                 # Video index 1
            ├── playlist.m3u8
            └── ...
```

**Important**: Original MP4 file is **NEVER** uploaded to S3!

---

## Workflow: Editing a Lesson (Replace/Swap Videos)

### Scenario 1: Replace Video #1 with New Upload

**Process** (`app/Filament/Resources/Lessons/Pages/EditLesson.php`):

```php
protected function mutateFormDataBeforeFill(array $data): array
{
    // Store original videos BEFORE editing
    $this->originalVideos = $data['video'] ?? [];
    return $data;
}

protected function afterSave(): void
{
    foreach ($this->record->video as $index => $video) {
        // Check if this is a NEW upload (local path)
        $isLocalPath = !str_starts_with($video['url'], 'hls/');
        
        if ($isLocalPath) {
            // 1. DELETE OLD HLS FILES at this index
            $oldHlsDir = "hls/{$lessonId}/{$index}/";
            $hlsFiles = Storage::disk('s3')->allFiles($oldHlsDir);
            
            if (!empty($hlsFiles)) {
                Storage::disk('s3')->deleteDirectory($oldHlsDir);
                Log::info("Deleted old HLS: {$oldHlsDir}");
            }
            
            // 2. Delete old thumbnail
            if (isset($this->originalVideos[$index]['thumbnail'])) {
                Storage::disk('s3')->delete($this->originalVideos[$index]['thumbnail']);
            }
            
            // 3. Copy to permanent temp
            $permanentTempPath = storage_path("app/video-processing/...");
            copy($localPath, $permanentTempPath);
            
            // 4. Dispatch new conversion job
            ConvertVideoToHLS::dispatch(...);
        }
    }
}
```

**What gets deleted?**
- ✅ Old HLS directory: `hls/13/0/` (playlist + all segments)
- ✅ Old thumbnail (if changed)
- ❌ No original MP4 to delete (never uploaded!)

---

### Scenario 2: Swap Videos (Reorder)

**Example**:
- **Before**: Video 0 = big.mp4 (43 segments), Video 1 = small.mp4 (1 segment)
- **After**: Video 0 = small.mp4, Video 1 = big.mp4

**What happens**:
1. Admin uploads new files at both positions
2. `afterSave()` detects local paths at index 0 and 1
3. **Deletes** `hls/13/0/` (old 43 segments)
4. **Deletes** `hls/13/1/` (old 1 segment)
5. Converts new files and uploads to same paths
6. No orphaned files! ✅

---

### Scenario 3: Remove Video from Repeater

```php
// Check for deleted videos
foreach ($this->originalVideos as $index => $oldVideo) {
    $stillExists = isset($this->record->video[$index]);
    
    if (!$stillExists) {
        // Video was removed
        Storage::disk('s3')->deleteDirectory("hls/{$lessonId}/{$index}/");
        if (isset($oldVideo['thumbnail'])) {
            Storage::disk('s3')->delete($oldVideo['thumbnail']);
        }
    }
}
```

---

## Workflow: Deleting a Lesson

**Trigger**: Admin clicks "Delete" on a lesson

**Process** (`app/Models/Lesson.php`):

```php
protected static function booted(): void
{
    static::deleting(function ($lesson) {
        // Delete ALL HLS directories for this lesson
        if (is_array($lesson->video)) {
            foreach ($lesson->video as $index => $video) {
                $hlsDir = "hls/{$lesson->id}/{$index}/";
                Storage::disk('s3')->deleteDirectory($hlsDir);
            }
        }
        
        // Delete thumbnails
        if ($lesson->thumbnail) {
            Storage::disk('s3')->delete($lesson->thumbnail);
        }
        
        // Delete video thumbnails
        foreach ($lesson->video as $video) {
            if (isset($video['thumbnail'])) {
                Storage::disk('s3')->delete($video['thumbnail']);
            }
        }
    });
}
```

**Result**: All S3 files for the lesson are deleted automatically.

---

## HLS Conversion Job Details

### Job Properties

```php
class ConvertVideoToHLS implements ShouldQueue
{
    public $timeout = 3600;  // 1 hour max
    
    protected int $lessonId;
    protected string $localVideoPath;  // Permanent temp path
    protected int $videoIndex;
    protected ?string $originalFilename;
}
```

### Processing Steps

1. **Validate Input**
   - Check if local file exists
   - Verify lesson exists in database

2. **Generate Encryption**
   - 16-byte AES-128 key
   - Unique key ID (ULID)
   - Random initialization vector

3. **FFmpeg Conversion**
   - Input: Local MP4 file
   - Output: HLS segments (10 seconds each)
   - Encryption: AES-128 per segment

4. **S3 Upload**
   - Upload all `.ts` segments
   - Upload `playlist.m3u8`
   - Set visibility: `public`

5. **Database Update**
   - Store HLS playlist path
   - Store encrypted encryption key
   - Store key ID
   - Remove `url` field (local path)

6. **Cleanup**
   - Delete permanent temp file
   - Delete processing directories
   - Remove temp encryption files

### Error Handling

```php
catch (\Exception $e) {
    Log::error("HLS conversion failed", [
        'lesson_id' => $lessonId,
        'error' => $e->getMessage()
    ]);
    
    $lesson->update(['hls_processing' => false]);
    throw $e;  // Retry via queue
}
```

**Note**: If job fails, temp file remains in `video-processing/` (needs manual cleanup or scheduled task).

---

## File Storage Locations

### Local Storage (Temporary)

| Location | Purpose | Cleaned By |
|----------|---------|------------|
| `storage/app/private/livewire-tmp/` | Livewire uploads | Livewire auto-cleanup |
| `storage/app/lesson-videos-temp/` | Filament temporary | Manual/Livewire |
| `storage/app/video-processing/` | Job processing | Job after success |
| `storage/app/temp/{lessonId}_{index}/` | Encryption keys | Job after upload |
| `storage/app/hls/{lessonId}_{index}/` | HLS output before S3 | Job after upload |

### AWS S3 (Permanent)

| Location | Content | Visibility |
|----------|---------|------------|
| `hls/{lessonId}/{videoIndex}/` | HLS segments + playlist | Public |
| `video-thumbnails/` | Lesson thumbnails | Public |
| `lesson-thumbnails/` | Video thumbnails | Public |

**NOT in S3**:
- ❌ Original MP4 files
- ❌ Encryption keys (served via API)

---

## Security Features

### 1. HLS Encryption (AES-128)

**Why?**
- Prevents video download/piracy
- Forces playback through app
- Each segment individually encrypted

**How it works?**
```
Client requests segment → Checks playlist.m3u8
→ Finds encryption key URL: /api/hls/keys/{keyId}
→ Requests key from backend
→ Backend verifies auth + returns decryption key
→ Client decrypts segment in memory
→ Plays video
```

### 2. Original MP4 Never Stored

- ✅ Only encrypted segments in S3
- ✅ Reduces storage costs
- ✅ Better security (no raw video download)

### 3. Encryption Key Storage

```php
// Stored encrypted in database
$videoData[$index]['encryption_key'] = Crypt::encrypt($encryptionKey);

// Decrypted only when served via API
Route::get('/api/hls/keys/{keyId}', function ($keyId) {
    // Authenticate user
    // Find video with this keyId
    // Decrypt and return key
});
```

### 4. Access Control

- HLS playlists are public (required for video players)
- But encryption prevents unauthorized viewing
- Key API endpoint requires authentication

---

## Error Handling

### Common Issues

| Issue | Cause | Solution |
|-------|-------|----------|
| "Local video file not found" | Livewire cleaned temp file | Files now copied to permanent temp |
| FFmpeg timeout | Video too large | Increase `timeout` in job |
| S3 upload fails | Network/credentials | Job will retry automatically |
| Job fails repeatedly | Invalid video format | Check FFmpeg error logs |

### Monitoring

**Check job status**:
```bash
php artisan queue:failed
```

**Retry failed jobs**:
```bash
php artisan queue:retry {id}
```

**Clear failed jobs**:
```bash
php artisan queue:flush
```

---

## Configuration

### PHP Settings (`C:\xampp\php\php.ini`)

```ini
upload_max_filesize = 1024M
post_max_size = 1000M
max_execution_time = 600
max_input_time = 600
memory_limit = 512M
```

### Livewire Config (`config/livewire.php`)

```php
'temporary_file_upload' => [
    'disk' => 'local',  // NOT s3!
    'rules' => ['file', 'max:512000'],  // 500MB
    'max_upload_time' => 30,  // 30 minutes
],
```

### Queue Config (`config/queue.php`)

```php
'default' => 'database',

'connections' => [
    'database' => [
        'driver' => 'database',
        'table' => 'jobs',
        'queue' => 'default',
        'retry_after' => 3600,  // 1 hour
    ],
],
```

### S3 Config (`config/filesystems.php`)

```php
's3' => [
    'driver' => 's3',
    'key' => env('AWS_ACCESS_KEY_ID'),
    'secret' => env('AWS_SECRET_ACCESS_KEY'),
    'region' => env('AWS_DEFAULT_REGION', 'eu-north-1'),
    'bucket' => env('AWS_BUCKET', 'my-course-app-videos'),
    'url' => env('AWS_URL'),
    'visibility' => 'public',
],
```

### Environment Variables (`.env`)

```env
QUEUE_CONNECTION=database

AWS_ACCESS_KEY_ID=your-key
AWS_SECRET_ACCESS_KEY=your-secret
AWS_DEFAULT_REGION=eu-north-1
AWS_BUCKET=my-course-app-videos
```

---

## Performance Metrics

### Upload & Processing Times (Estimate)

| File Size | Upload | FFmpeg Conversion | S3 Upload | Total |
|-----------|--------|-------------------|-----------|-------|
| 10 MB | ~5s | ~15s | ~5s | ~25s |
| 50 MB | ~15s | ~45s | ~20s | ~80s |
| 100 MB | ~30s | ~90s | ~40s | ~160s |
| 500 MB | ~120s | ~450s | ~180s | ~750s |

**Note**: Times vary based on:
- Network speed
- Server CPU
- Video codec/resolution
- FFmpeg settings

### Bandwidth Savings (vs Old Workflow)

**Old Workflow**:
```
Upload 100MB to S3 → Download 100MB from S3 → Convert → Upload 100MB HLS
Total: 300MB network I/O
```

**New Workflow**:
```
Upload locally → Convert locally → Upload 100MB HLS only
Total: 100MB network I/O
```

**Savings**: ~66% reduction in network I/O! 🎉

---

## Troubleshooting

### Videos stuck in "processing" state

**Check**:
1. Queue worker running? → `php artisan queue:work`
2. Check failed jobs → `php artisan queue:failed`
3. Check logs → `storage/logs/laravel.log`

### FFmpeg not working

**Check**:
1. FFmpeg installed? → `ffmpeg -version`
2. Path correct? → Windows: `where ffmpeg`
3. Executable permissions?

### S3 upload fails

**Check**:
1. AWS credentials correct?
2. Bucket exists?
3. Bucket region matches config?
4. IAM permissions allow `s3:PutObject`?

### Large files fail

**Check**:
1. PHP settings (`upload_max_filesize`, `post_max_size`)
2. Livewire config (`max:512000`)
3. Job timeout (`public $timeout = 3600`)
4. Nginx/Apache timeout settings

---

## Maintenance Tasks

### Recommended Scheduled Tasks

**1. Clean old temp files** (daily):
```php
// app/Console/Kernel.php
$schedule->call(function () {
    $files = Storage::disk('local')->files('lesson-videos-temp');
    foreach ($files as $file) {
        if (Storage::disk('local')->lastModified($file) < now()->subDay()->timestamp) {
            Storage::disk('local')->delete($file);
        }
    }
})->daily();
```

**2. Clean failed job temp files** (weekly):
```php
$schedule->call(function () {
    $files = glob(storage_path('app/video-processing/*'));
    foreach ($files as $file) {
        if (filemtime($file) < strtotime('-1 week')) {
            unlink($file);
        }
    }
})->weekly();
```

**3. Monitor S3 storage** (monthly):
```bash
aws s3 ls s3://my-course-app-videos/hls/ --recursive --summarize
```

---

## API Endpoints

### HLS Key Delivery

**Endpoint**: `GET /api/hls/keys/{keyId}`

**Purpose**: Serve decryption keys for HLS playback

**Implementation** (needs to be created):
```php
Route::middleware('auth:sanctum')->get('/api/hls/keys/{keyId}', function ($keyId) {
    // Find video with this key ID
    $lesson = Lesson::whereRaw("JSON_CONTAINS(video, JSON_OBJECT('encryption_key_id', ?))", [$keyId])
        ->first();
    
    if (!$lesson) {
        abort(404);
    }
    
    // Find the specific video
    foreach ($lesson->video as $video) {
        if ($video['encryption_key_id'] === $keyId) {
            $key = Crypt::decrypt($video['encryption_key']);
            return response($key, 200, [
                'Content-Type' => 'application/octet-stream'
            ]);
        }
    }
    
    abort(404);
});
```

---

## Future Improvements

### Potential Enhancements

1. **Adaptive Bitrate Streaming**
   - Generate multiple quality levels (360p, 720p, 1080p)
   - Client auto-switches based on bandwidth

2. **Progress Tracking**
   - Show FFmpeg conversion progress in admin panel
   - Use WebSockets/Pusher for real-time updates

3. **Video Previews**
   - Generate thumbnail from video
   - Extract first frame as preview image

4. **CDN Integration**
   - Use CloudFront in front of S3
   - Faster global delivery
   - Reduced S3 costs

5. **Automatic Cleanup**
   - Scheduled job to clean temp files
   - Monitor and alert on disk usage

6. **Compression**
   - Optimize video encoding settings
   - Reduce file sizes without quality loss

---

## Summary

This implementation provides a **secure, efficient, and cost-effective** video delivery system:

✅ **No wasted bandwidth** - Videos processed locally  
✅ **Encrypted HLS** - Prevents piracy  
✅ **Automatic cleanup** - No orphaned files  
✅ **Scalable** - Queue-based background processing  
✅ **Multiple videos** - Support for lessons with multiple videos  
✅ **Smart editing** - Handles replacement and reordering  

The workflow ensures that original high-quality MP4 files never touch the cloud, only encrypted HLS segments are stored in S3, providing both security and cost savings.
