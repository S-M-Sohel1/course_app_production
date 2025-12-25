<?php

namespace App\Filament\Resources\Lessons\Pages;

use App\Filament\Resources\Lessons\LessonResource;
use Filament\Resources\Pages\CreateRecord;
use App\Jobs\ConvertVideoToHLS;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CreateLesson extends CreateRecord
{
    protected static string $resource = LessonResource::class;
    
    protected function afterCreate(): void
    {
        $this->record->refresh();
        
        Log::info('AfterCreate triggered', [
            'lesson_id' => $this->record->id,
            'video_count' => is_array($this->record->video) ? count($this->record->video) : 0,
        ]);
        
        // Process videos from LOCAL storage (not S3!)
        if (!empty($this->record->video) && is_array($this->record->video)) {
            foreach ($this->record->video as $index => $video) {
                if (isset($video['url']) && !empty($video['url'])) {
                    // The video['url'] is now a local disk path like "lesson-videos-temp/filename.mp4"
                    $localPath = Storage::disk('local')->path($video['url']);
                    
                    if (!file_exists($localPath)) {
                        Log::error("Local video file not found", [
                            'lesson_id' => $this->record->id,
                            'video_index' => $index,
                            'expected_path' => $localPath,
                            'url_field' => $video['url']
                        ]);
                        continue;
                    }
                    
                    Log::info('Dispatching HLS job from LOCAL file', [
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
                }
            }
            
            // Mark lesson as having videos in processing
            $this->record->update(['hls_processing' => true]);
        } else {
            Log::warning('Video data not in expected format', [
                'lesson_id' => $this->record->id,
                'video_data' => $this->record->video,
            ]);
        }
    }
}
