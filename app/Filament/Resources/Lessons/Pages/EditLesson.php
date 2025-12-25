<?php

namespace App\Filament\Resources\Lessons\Pages;

use App\Filament\Resources\Lessons\LessonResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use App\Jobs\ConvertVideoToHLS;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class EditLesson extends EditRecord
{
    protected static string $resource = LessonResource::class;
    
    protected $originalVideos = [];

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
    
    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Store original video data before editing
        $this->originalVideos = $data['video'] ?? [];
        return $data;
    }
    
    protected function afterSave(): void
    {
        $this->record->refresh();
        
        Log::info('EditLesson afterSave triggered', [
            'lesson_id' => $this->record->id,
            'video_count' => is_array($this->record->video) ? count($this->record->video) : 0,
            'original_videos' => $this->originalVideos
        ]);
        
        // Process videos from LOCAL storage
        if (!empty($this->record->video) && is_array($this->record->video)) {
            foreach ($this->record->video as $index => $video) {
                if (!isset($video['url']) || empty($video['url'])) {
                    continue;
                }
                
                // Check if this is a LOCAL path (new upload) - means user uploaded a new video
                $isLocalPath = !str_starts_with($video['url'], 'hls/');
                
                if ($isLocalPath) {
                    // ALWAYS DELETE OLD HLS FILES for this index when there's a new upload
                    // This handles both: replacement and reordering/swapping
                    $oldHlsDir = "hls/{$this->record->id}/{$index}/";
                    
                    // Check if HLS directory exists and delete it
                    $hlsFiles = Storage::disk('s3')->allFiles($oldHlsDir);
                    if (!empty($hlsFiles)) {
                        Log::info('Deleting OLD HLS directory (new upload detected at this index)', [
                            'lesson_id' => $this->record->id,
                            'video_index' => $index,
                            'hls_dir' => $oldHlsDir,
                            'files_count' => count($hlsFiles)
                        ]);
                        
                        Storage::disk('s3')->deleteDirectory($oldHlsDir);
                    }
                    
                    // Delete old video thumbnail if exists at this index
                    if (isset($this->originalVideos[$index]['thumbnail'])) {
                        Storage::disk('s3')->delete($this->originalVideos[$index]['thumbnail']);
                        Log::info('Deleted old thumbnail', ['path' => $this->originalVideos[$index]['thumbnail']]);
                    }
                    
                    // Get local file path
                    $localPath = Storage::disk('local')->path($video['url']);
                    
                    if (!file_exists($localPath)) {
                        Log::error("Local video file not found", [
                            'lesson_id' => $this->record->id,
                            'video_index' => $index,
                            'expected_path' => $localPath
                        ]);
                        continue;
                    }
                    
                    Log::info('Dispatching HLS job for edited lesson from LOCAL file', [
                        'lesson_id' => $this->record->id,
                        'video_index' => $index,
                        'local_path' => $localPath,
                        'size_mb' => round(filesize($localPath) / 1024 / 1024, 2)
                    ]);
                    
                    // Dispatch HLS conversion job with LOCAL file path
                    ConvertVideoToHLS::dispatch(
                        $this->record->id, 
                        $localPath,
                        $index,
                        basename($localPath)
                    );
                    
                    // Mark as processing
                    $this->record->update(['hls_processing' => true]);
                }
            }
        }
        
        // Check for deleted videos (entire video entry removed from repeater)
        if (!empty($this->originalVideos)) {
            foreach ($this->originalVideos as $index => $oldVideo) {
                $stillExists = isset($this->record->video[$index]);
                
                if (!$stillExists) {
                    Log::info('Video was REMOVED from repeater - deleting all files', [
                        'lesson_id' => $this->record->id,
                        'video_index' => $index
                    ]);
                    
                    // Delete removed video thumbnail
                    if (isset($oldVideo['thumbnail'])) {
                        Storage::disk('s3')->delete($oldVideo['thumbnail']);
                        Log::info('Deleted removed thumbnail', ['path' => $oldVideo['thumbnail']]);
                    }
                    
                    // Delete removed video's HLS directory
                    $hlsDir = "hls/{$this->record->id}/{$index}/";
                    Storage::disk('s3')->deleteDirectory($hlsDir);
                    Log::info('Deleted removed video HLS directory', ['path' => $hlsDir]);
                    
                    // Delete local temp file if exists
                    if (isset($oldVideo['url']) && !str_starts_with($oldVideo['url'], 'hls/')) {
                        $localPath = Storage::disk('local')->path($oldVideo['url']);
                        if (file_exists($localPath)) {
                            unlink($localPath);
                            Log::info('Deleted local temp file for removed video', ['path' => $localPath]);
                        }
                    }
                }
            }
        }
    }
}
