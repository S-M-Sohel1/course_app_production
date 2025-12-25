# Video Upload & HLS Conversion Workflow - OPTIMIZED

## Previous Workflow (Inefficient) ❌
1. User uploads video → **Livewire temp (local)**
2. Filament saves → **Upload to S3** (lesson-videos/)
3. Queue job starts → **Download from S3** ⚠️ (wasted bandwidth!)
4. Convert to HLS locally
5. Upload HLS files to S3
6. Delete original MP4 from S3

**Problem**: Step 2-3 wastes bandwidth by uploading to S3, then immediately downloading the same file!

---

## New Workflow (Optimized) ✅
1. User uploads video → **Livewire temp (local)**
2. `mutateFormDataBeforeSave()` → **Copy to processing temp directory**
3. Prevent Filament from uploading to S3 (set to placeholder)
4. Queue job starts → **Use local file directly** 🚀
5. Convert to HLS from local file
6. Upload **only HLS files** to S3
7. **Never upload original MP4** to S3
8. Clean up local temp files

**Benefits**:
- ✅ No wasted S3 uploads/downloads
- ✅ Faster processing (no network I/O for large files)
- ✅ Reduced AWS S3 costs (fewer PUT/GET requests)
- ✅ Better security (original MP4 never stored in S3)

---

## Code Changes

### 1. ConvertVideoToHLS Job
**Before**: Downloaded from S3
```php
file_put_contents($localVideoPath, Storage::disk('s3')->get($this->videoPath));
```

**After**: Uses local file directly
```php
if (!file_exists($this->localVideoPath)) {
    throw new \Exception("Local video file not found");
}
$this->convertToHLS($this->localVideoPath, ...);
```

### 2. CreateLesson Page
**Before**: Passed S3 URL to job
```php
ConvertVideoToHLS::dispatch($lessonId, $video['url'], $index);
```

**After**: Intercepts Livewire temp file, passes local path
```php
protected function mutateFormDataBeforeSave(array $data): array
{
    foreach ($data['video'] as $index => $video) {
        if ($video instanceof TemporaryUploadedFile) {
            $jobTempPath = storage_path("app/video-processing/{$tempPath}");
            copy($video->getRealPath(), $jobTempPath);
            $this->videoFilePaths[$index] = ['path' => $jobTempPath, ...];
        }
    }
    return $data; // Video field set to placeholder, not uploaded to S3
}

protected function afterCreate(): void
{
    ConvertVideoToHLS::dispatch($lessonId, $fileData['path'], $index, $filename);
}
```

### 3. EditLesson Page
Same optimization as CreateLesson - intercepts new/changed videos before S3 upload.

---

## File Storage Locations

### Temporary Storage (Processing)
- `storage/app/private/livewire-tmp/` - Livewire uploads
- `storage/app/video-processing/` - Copied for job processing

### Final Storage (S3)
- `hls/{lessonId}/{videoIndex}/playlist.m3u8` - HLS playlist
- `hls/{lessonId}/{videoIndex}/segment_*.ts` - Video segments
- `hls/{lessonId}/{videoIndex}/encryption.key` - ❌ Not stored (served via API)

### What's NOT in S3
- ✅ Original MP4 files (never uploaded, deleted after HLS conversion)

---

## Testing Checklist

- [ ] Create new lesson with single video
- [ ] Create new lesson with multiple videos  
- [ ] Edit lesson - add new video
- [ ] Edit lesson - replace existing video
- [ ] Edit lesson - remove video
- [ ] Verify large files (65MB+) process successfully
- [ ] Check S3 bucket - confirm no original MP4s
- [ ] Check local storage cleaned up after job
- [ ] Verify HLS playback works
- [ ] Check logs for "Using local video file" (not "Downloading from S3")

---

## Performance Improvement Estimate

For a **65MB video**:
- **Old workflow**: Upload 65MB to S3 → Download 65MB from S3 = **130MB network I/O**
- **New workflow**: Process locally = **0MB network I/O** for original video

**Result**: ~50% reduction in processing time + reduced AWS costs! 🎉
