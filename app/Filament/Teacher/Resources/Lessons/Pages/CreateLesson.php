<?php

namespace App\Filament\Teacher\Resources\Lessons\Pages;

use App\Filament\Teacher\Resources\Lessons\LessonResource;
use Filament\Resources\Pages\CreateRecord;
use App\Jobs\ConvertVideoToHLS;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class CreateLesson extends CreateRecord
{
    protected static string $resource = LessonResource::class;
    
    protected function afterCreate(): void
    {
        // Refresh the record to ensure casts are applied
        $this->record->refresh();
        
        Log::info('AfterCreate triggered (Teacher)', [
            'lesson_id' => $this->record->id,
            'video_count' => is_array($this->record->video) ? count($this->record->video) : 0,
        ]);
        
        // Trigger HLS conversion for ALL videos in the lesson
        if (!empty($this->record->video) && is_array($this->record->video)) {
            foreach ($this->record->video as $index => $video) {
                if (isset($video['url'])) {
                    Log::info('Dispatching HLS job', [
                        'lesson_id' => $this->record->id,
                        'video_index' => $index,
                        'video_url' => $video['url']
                    ]);
                    
                    // Dispatch HLS conversion job for each video
                    ConvertVideoToHLS::dispatch($this->record->id, $video['url'], $index);
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
