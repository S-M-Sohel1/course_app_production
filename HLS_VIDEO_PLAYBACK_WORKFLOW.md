# HLS Video Playback Workflow

## Overview
This document describes the complete workflow for displaying encrypted HLS videos in the Filament admin panel lesson view.

## Architecture Components

### 1. Storage
- **AWS S3 Bucket**: `my-course-app-videos` (region: `eu-north-1`)
- **Video Format**: HLS (HTTP Live Streaming) with AES-128 encryption
- **File Structure**:
  ```
  hls/{lesson_id}/{video_index}/
  ├── playlist.m3u8          # HLS manifest file
  ├── segment_000.ts         # Video segments (encrypted)
  ├── segment_001.ts
  └── ...
  ```

### 2. Database
- **Table**: `lessons`
- **Video Field**: JSON column containing:
  ```json
  [
    {
      "name": "Video Title",
      "thumbnail": "path/to/thumbnail.jpg",
      "hls_playlist": "hls/14/0/playlist.m3u8",
      "encryption_key": "encrypted_key_data",
      "encryption_key_id": "01K9JFMBAXPK1AQ24QGPG177J2",
      "original_filename": "video.mp4"
    }
  ]
  ```

### 3. Backend Controllers

#### HLSStreamController (`app/Http/Controllers/Api/HLSStreamController.php`)
**Purpose**: Serves HLS files with URL rewriting and CORS headers

**Routes**:
- `GET /api/hls-stream/{path}` - Serves playlists and redirects to S3 for segments
- `OPTIONS /api/hls-stream/{path}` - Handles CORS preflight requests

**Workflow**:
1. **For `.m3u8` playlist files**:
   - Fetches the file from S3
   - Rewrites segment URLs to point to `/api/hls-stream/...`
   - Rewrites encryption key URIs to point to `/api/hls/keys/{keyId}`
   - Preserves IV (Initialization Vector) values
   - Returns with CORS headers

2. **For `.ts` video segment files**:
   - Generates a temporary signed S3 URL (valid for 5 minutes)
   - Returns 302 redirect to the signed URL
   - Allows S3 to serve the file directly (fast delivery)

3. **For `.key` files**:
   - Returns 404 (keys should only be accessed via HLSKeyController)

**Example URL Rewriting**:
```
Original playlist.m3u8:
#EXT-X-KEY:METHOD=AES-128,URI="https://ngrok-domain/api/hls/keys/01K9JFMBAXPK1AQ24QGPG177J2",IV=0x6ac25cbf...
segment_000.ts

Rewritten playlist.m3u8:
#EXT-X-KEY:METHOD=AES-128,URI="http://localhost:8000/api/hls/keys/01K9JFMBAXPK1AQ24QGPG177J2",IV=0x6ac25cbf...
http://localhost:8000/api/hls-stream/hls/14/0/segment_000.ts
```

#### HLSKeyController (`app/Http/Controllers/Api/HLSKeyController.php`)
**Purpose**: Serves decryption keys for encrypted video segments

**Route**: `GET /api/hls/keys/{keyId}`

**Workflow**:
1. Receives key ID from URL parameter
2. Queries database for lesson containing this encryption key ID
   ```php
   Lesson::whereRaw("JSON_SEARCH(video, 'one', ?) IS NOT NULL", [$keyId])
   ```
3. Finds the specific video entry with matching `encryption_key_id`
4. Decrypts the stored encryption key using Laravel's `Crypt::decrypt()`
5. Validates key size (must be exactly 16 bytes for AES-128)
6. Returns raw binary key with CORS headers

**Response Headers**:
- `Content-Type: application/octet-stream`
- `Content-Length: 16`
- `Access-Control-Allow-Origin: *`
- `Access-Control-Allow-Methods: GET, OPTIONS`
- `Cross-Origin-Resource-Policy: cross-origin`

### 4. Frontend Components

#### LessonInfolist.php (`app/Filament/Resources/Lessons/Schemas/LessonInfolist.php`)
**Purpose**: Defines the Filament infolist schema for lesson view

**Video Player Integration**:
```php
ViewEntry::make('hls_playlist')
    ->label('Video Player')
    ->view('filament.components.hls-player')
    ->columnSpanFull()
```

#### HLS Player Blade Component (`resources/views/filament/components/hls-player.blade.php`)
**Purpose**: Renders the video player with HLS.js library

**Workflow**:
1. Receives HLS playlist path from Filament state
2. Constructs Laravel streaming URL: `/api/hls-stream/{playlist_path}`
3. Loads HLS.js library from CDN (loaded once with `@once` directive)
4. Initializes video player with configuration:
   ```javascript
   new Hls({
       debug: true,
       enableWorker: false  // Disable worker for HTTP compatibility
   })
   ```
5. Loads the HLS source and attaches to video element
6. HLS.js handles:
   - Fetching the playlist
   - Downloading video segments
   - Requesting decryption keys
   - Decrypting segments using WebCrypto API or software fallback
   - Playing the video

## Complete Request Flow

### Step-by-Step Video Playback Process

```
1. User navigates to lesson view
   → Browser: GET /admin/lessons/14

2. Filament renders ViewEntry component
   → Blade: filament/components/hls-player.blade.php
   → Video player initialized with URL: /api/hls-stream/hls/14/0/playlist.m3u8

3. HLS.js requests playlist
   → Browser: GET /api/hls-stream/hls/14/0/playlist.m3u8
   → Laravel: HLSStreamController@stream
   → S3: Fetch original playlist
   → Laravel: Rewrite URLs in playlist
   → Browser: Receives rewritten playlist

4. HLS.js parses playlist and requests decryption key
   → Browser: GET /api/hls/keys/01K9JFMBAXPK1AQ24QGPG177J2
   → Laravel: HLSKeyController@getKey
   → Database: Query lesson by encryption_key_id
   → Laravel: Decrypt key using Crypt::decrypt()
   → Browser: Receives 16-byte binary key

5. HLS.js requests first video segment
   → Browser: GET /api/hls-stream/hls/14/0/segment_000.ts
   → Laravel: HLSStreamController@stream (302 redirect)
   → Browser: Follow redirect to signed S3 URL
   → S3: Serves encrypted segment directly
   → Browser: Downloads encrypted segment

6. HLS.js decrypts and plays segment
   → WebCrypto API: AES-128 decryption using key and IV
   → Video element: Appends decrypted segment to buffer
   → User: Sees video playing

7. Repeat steps 5-6 for remaining segments
   → Progressive playback continues
```

## Security Considerations

### Encryption
- **Algorithm**: AES-128-CBC
- **Key Generation**: `random_bytes(16)` during video upload
- **Key Storage**: Encrypted in database using Laravel's `Crypt` facade
- **IV (Initialization Vector)**: Generated during upload, stored in playlist

### Access Control
- Authentication checks are currently **disabled** for testing
- In production, should verify:
  - User is authenticated (`Auth::guard('sanctum')->check()`)
  - User has purchased the course
  - User has access to the specific lesson

### CORS Configuration
- All API endpoints return CORS headers:
  - `Access-Control-Allow-Origin: *`
  - `Access-Control-Allow-Methods: GET, HEAD, OPTIONS`
  - `Access-Control-Allow-Headers: Content-Type, Range`

### S3 Security
- Bucket is **publicly readable** (required for redirected segment access)
- Temporary signed URLs expire after 5 minutes
- Original encryption keys are **never** stored on S3

## Browser Compatibility

### Secure Context Requirements
**WebCrypto API** (used for decryption) requires a secure context:

✅ **Works**:
- `https://` (any domain)
- `http://localhost` (special exemption)
- `http://localhost:8000`

❌ **Doesn't work**:
- `http://127.0.0.1` (not considered secure by Chrome/Edge)
- `http://192.168.x.x` (local IP addresses)

### Workarounds
1. **Development**: Use `http://localhost:8000` instead of `http://127.0.0.1:8000`
2. **Production**: Always use HTTPS (ngrok, SSL certificate, etc.)
3. **Configuration**: `enableWorker: false` forces software decryption fallback

## Configuration Files

### Routes (`routes/api.php`)
```php
// HLS streaming with CORS
Route::options('/hls-stream/{path}', [HLSStreamController::class, 'options'])->where('path', '.*');
Route::get('/hls-stream/{path}', [HLSStreamController::class, 'stream'])->where('path', '.*');

// Decryption key endpoint
Route::get('/hls/keys/{keyId}', [HLSKeyController::class, 'getKey']);
Route::options('/hls/keys/{keyId}', function() {
    return response('', 200, [
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Methods' => 'GET, OPTIONS',
        'Access-Control-Allow-Headers' => '*',
        'Access-Control-Max-Age' => '86400',
    ]);
});
```

### Filesystems (`config/filesystems.php`)
```php
's3' => [
    'driver' => 's3',
    'key' => env('AWS_ACCESS_KEY_ID'),
    'secret' => env('AWS_SECRET_ACCESS_KEY'),
    'region' => env('AWS_DEFAULT_REGION'),
    'bucket' => env('AWS_BUCKET'),
    'url' => env('AWS_URL'),
    'endpoint' => env('AWS_ENDPOINT'),
    'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
    'throw' => false,
]
```

### Environment Variables (`.env`)
```env
AWS_ACCESS_KEY_ID=your_access_key
AWS_SECRET_ACCESS_KEY=your_secret_key
AWS_DEFAULT_REGION=eu-north-1
AWS_BUCKET=my-course-app-videos
APP_URL=http://localhost:8000  # or https://your-domain.ngrok.io
```

## Troubleshooting

### Common Issues

1. **CORS Errors**
   - **Symptom**: "No 'Access-Control-Allow-Origin' header"
   - **Solution**: Verify CORS headers in HLSStreamController and HLSKeyController

2. **Decryption Errors**
   - **Symptom**: "WebCrypto and softwareDecrypt: failed to decrypt data"
   - **Causes**:
     - Not using `localhost` or HTTPS
     - Key size is not exactly 16 bytes
     - Wrong key being returned
   - **Solution**: Use `localhost` for development, check logs for key size

3. **Timeout Errors**
   - **Symptom**: "Timeout after 10000ms" on segment loading
   - **Cause**: Video segments too large for proxying
   - **Solution**: Use redirect to S3 (already implemented)

4. **Video Not Loading**
   - **Symptom**: No network requests to playlist
   - **Causes**:
     - HLS.js not loaded
     - JavaScript errors preventing initialization
   - **Solution**: Check browser console for errors, verify HLS.js CDN is accessible

## Performance Optimizations

### Current Implementation
1. **Playlist**: Served directly by Laravel (small file, needs URL rewriting)
2. **Video Segments**: 302 redirect to S3 (S3 serves directly = fast)
3. **Decryption Keys**: Served by Laravel (small file, requires database query)

### Why This Approach
- **Avoid proxying large files**: Prevents PHP timeout and memory issues
- **S3 serves segments directly**: Utilizes S3's CDN capabilities
- **Laravel controls access**: Can add authentication/authorization logic
- **URL rewriting**: Ensures all requests go through our domain (CORS-friendly)

## Future Enhancements

### Recommended Improvements
1. **Enable authentication**: Uncomment auth checks in HLSKeyController
2. **Add purchase verification**: Verify user has purchased course before serving key
3. **Implement caching**: Cache decrypted keys (with proper TTL)
4. **Use CloudFront**: Add AWS CloudFront CDN for better performance
5. **Add analytics**: Track video playback events
6. **Implement quality selection**: Multiple quality levels in HLS manifest
7. **Add subtitles support**: WebVTT subtitle tracks
8. **Offline download**: Progressive download for purchased content

## Technical Notes

### Why Not Direct S3 URLs?
- **CORS limitations**: S3 CORS configuration doesn't work well with browser security policies
- **Signed URLs**: Don't support CORS properly (signature validated before CORS headers)
- **Access control**: Can't verify user permissions on S3 directly

### Why HLS.js Over Video.js?
- **Better CORS handling**: More flexible with cross-origin requests
- **Lighter weight**: Smaller bundle size
- **Active development**: Better maintained and updated

### Why Disable Worker?
- **WebCrypto limitation**: Workers have stricter secure context requirements
- **HTTP compatibility**: Allows software decryption fallback over HTTP
- **Trade-off**: Slightly slower performance but better compatibility

## Summary

The video playback system uses a hybrid approach:
1. **Laravel** handles playlist serving with URL rewriting and key delivery
2. **S3** serves the actual video segments (redirected from Laravel)
3. **HLS.js** manages playback, decryption, and adaptive streaming
4. **WebCrypto/Software Decryption** decrypts segments in the browser

This architecture provides:
- ✅ Security through encryption
- ✅ Access control through Laravel API
- ✅ Performance through S3 CDN
- ✅ Compatibility with modern browsers
- ✅ Scalability for multiple concurrent users
