<?php

namespace App\Filament\Teacher\Resources\Lessons\Pages;

use App\Filament\Teacher\Resources\Lessons\LessonResource;
use Filament\Actions\DeleteAction;
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
        // Refresh to get the latest data
        $this->record->refresh();
        
        Log::info('EditLesson afterSave triggered (Teacher)', [
            'lesson_id' => $this->record->id,
            'video_count' => is_array($this->record->video) ? count($this->record->video) : 0,
        ]);
        
        // Process all videos and trigger HLS conversion for new/changed ones
        if (!empty($this->record->video) && is_array($this->record->video)) {
            foreach ($this->record->video as $index => $video) {
                if (!isset($video['url'])) {
                    continue;
                }
                
                // Check if this is a new video or URL changed
                $isNewOrChanged = !isset($this->originalVideos[$index]) || 
                                  !isset($this->originalVideos[$index]['url']) ||
                                  $this->originalVideos[$index]['url'] !== $video['url'];
                
                if ($isNewOrChanged) {
                    // Delete old video files from S3 if URL changed (replacement)
                    if (isset($this->originalVideos[$index]['url']) && 
                        $this->originalVideos[$index]['url'] !== $video['url']) {
                        
                        Log::info('Deleting replaced video files from S3 (Teacher)', [
                            'lesson_id' => $this->record->id,
                            'video_index' => $index
                        ]);
                        
                        // Delete old original video
                        Storage::disk('s3')->delete($this->originalVideos[$index]['url']);
                        
                        // Delete old video thumbnail
                        if (isset($this->originalVideos[$index]['thumbnail'])) {
                            Storage::disk('s3')->delete($this->originalVideos[$index]['thumbnail']);
                        }
                        
                        // Delete old HLS directory
                        if (isset($this->originalVideos[$index]['hls_playlist'])) {
                            $oldHlsDir = "hls/{$this->record->id}/{$index}/";
                            Storage::disk('s3')->deleteDirectory($oldHlsDir);
                            Log::info('Deleted old HLS directory', ['path' => $oldHlsDir]);
                        }
                    }
                    
                    Log::info('Dispatching HLS job for edited lesson (Teacher)', [
                        'lesson_id' => $this->record->id,
                        'video_index' => $index,
                        'video_url' => $video['url'],
                        'reason' => !isset($this->originalVideos[$index]) ? 'new_video' : 'url_changed'
                    ]);
                    
                    // Dispatch HLS conversion job
                    ConvertVideoToHLS::dispatch($this->record->id, $video['url'], $index);
                    
                    // Mark as processing
                    $this->record->update(['hls_processing' => true]);
                }
            }
        }
        
        // Check for deleted videos (videos that existed before but not anymore)
        if (!empty($this->originalVideos)) {
            foreach ($this->originalVideos as $index => $oldVideo) {
                $stillExists = isset($this->record->video[$index]) && 
                              isset($this->record->video[$index]['url']);
                
                if (!$stillExists) {
                    Log::info('Deleting removed video files from S3 (Teacher)', [
                        'lesson_id' => $this->record->id,
                        'video_index' => $index
                    ]);
                    
                    // Delete removed video
                    if (isset($oldVideo['url'])) {
                        Storage::disk('s3')->delete($oldVideo['url']);
                    }
                    
                    // Delete removed video thumbnail
                    if (isset($oldVideo['thumbnail'])) {
                        Storage::disk('s3')->delete($oldVideo['thumbnail']);
                    }
                    
                    // Delete removed video's HLS directory
                    if (isset($oldVideo['hls_playlist'])) {
                        $hlsDir = "hls/{$this->record->id}/{$index}/";
                        Storage::disk('s3')->deleteDirectory($hlsDir);
                        Log::info('Deleted removed video HLS directory', ['path' => $hlsDir]);
                    }
                }
            }
        }
    }
}
