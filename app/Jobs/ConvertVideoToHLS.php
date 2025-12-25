<?php

namespace App\Jobs;

use App\Models\Lesson;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class ConvertVideoToHLS implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600; // 1 hour timeout for large videos

    /**
     * Create a new job instance.
     */
    protected int $lessonId;
    protected string $localVideoPath; // Changed from S3 path to local path
    protected int $videoIndex;
    protected ?string $originalFilename;

    public function __construct(int $lessonId, string $localVideoPath, int $videoIndex, ?string $originalFilename = null)
    {
        $this->lessonId = $lessonId;
        $this->localVideoPath = $localVideoPath;
        $this->videoIndex = $videoIndex;
        $this->originalFilename = $originalFilename;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $lesson = Lesson::findOrFail($this->lessonId);
            
            Log::info("Starting HLS conversion", [
                'lesson_id' => $lesson->id,
                'video_index' => $this->videoIndex,
                'local_video_path' => $this->localVideoPath,
                'original_filename' => $this->originalFilename
            ]);

            // Verify local file exists
            if (!file_exists($this->localVideoPath)) {
                throw new \Exception("Local video file not found: {$this->localVideoPath}");
            }

            // Mark as processing
            $lesson->update(['hls_processing' => true]);

            // Generate encryption key
            $encryptionKey = random_bytes(16); // 128-bit key for AES-128
            $keyId = Str::ulid()->toString();
            $iv = bin2hex(random_bytes(16)); // Initialization vector

            // Create temp directories (use unique folder per video)
            $tempDir = storage_path("app/temp/{$lesson->id}_{$this->videoIndex}/");
            $hlsOutputDir = storage_path("app/hls/{$lesson->id}_{$this->videoIndex}/");
            
            if (!file_exists($tempDir)) {
                mkdir($tempDir, 0755, true);
            }
            if (!file_exists($hlsOutputDir)) {
                mkdir($hlsOutputDir, 0755, true);
            }

            // Use the local file directly (no S3 download needed!)
            Log::info("Using local video file: {$this->localVideoPath}");

            // Save encryption key to temp file
            $keyPath = $tempDir . 'encryption.key';
            file_put_contents($keyPath, $encryptionKey);

            // Create keyinfo file for FFmpeg
            $keyInfoPath = $tempDir . 'keyinfo.txt';
            $keyUrl = url("/api/hls/keys/{$keyId}");
            
            file_put_contents($keyInfoPath, implode("\n", [
                $keyUrl,
                $keyPath,
                $iv
            ]));

            // Convert to HLS with encryption (using local file)
            Log::info("Starting FFmpeg conversion");
            $this->convertToHLS($this->localVideoPath, $hlsOutputDir, $keyInfoPath);

            // Upload HLS files to S3
            Log::info("Uploading HLS files to S3");
            $playlistPath = $this->uploadHLSToS3($hlsOutputDir, $lesson->id, $this->videoIndex);

            // Update the specific video in the JSON array
            $videoData = $lesson->video;
            if (isset($videoData[$this->videoIndex])) {
                $videoData[$this->videoIndex]['hls_playlist'] = $playlistPath;
                $videoData[$this->videoIndex]['encryption_key_id'] = $keyId;
                $videoData[$this->videoIndex]['encryption_key'] = Crypt::encrypt($encryptionKey);
                // Store original filename for reference
                $videoData[$this->videoIndex]['original_filename'] = $this->originalFilename ?? 'video.mp4';
                // Remove the url field since we never uploaded to S3
                unset($videoData[$this->videoIndex]['url']);
                
                $lesson->update(['video' => $videoData]);
            }

            // Check if all videos are processed, then mark as complete
            $allProcessed = true;
            foreach ($videoData as $video) {
                if (!isset($video['hls_playlist'])) {
                    $allProcessed = false;
                    break;
                }
            }
            
            if ($allProcessed) {
                $lesson->update(['hls_processing' => false]);
            }

            // Cleanup: Delete the local livewire temp file and processing directories
            if (file_exists($this->localVideoPath)) {
                unlink($this->localVideoPath);
                Log::info("Deleted local temp video: {$this->localVideoPath}");
            }
            $this->cleanup($tempDir, $hlsOutputDir);

            Log::info("HLS conversion completed", [
                'lesson_id' => $lesson->id,
                'video_index' => $this->videoIndex
            ]);

        } catch (\Exception $e) {
            Log::error("HLS conversion failed", [
                'lesson_id' => $this->lessonId,
                'video_index' => $this->videoIndex,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $lesson = Lesson::find($this->lessonId);
            if ($lesson) {
                $lesson->update(['hls_processing' => false]);
            }
            throw $e;
        }
    }

    private function convertToHLS($inputPath, $outputDir, $keyInfoPath): void
    {
        $command = [
            'ffmpeg',
            '-i', $inputPath,
            '-c:v', 'libx264',
            '-c:a', 'aac',
            '-hls_time', '10',
            '-hls_key_info_file', $keyInfoPath,
            '-hls_playlist_type', 'vod',
            '-hls_segment_filename', $outputDir . 'segment_%03d.ts',
            $outputDir . 'playlist.m3u8'
        ];

        $process = new Process($command);
        $process->setTimeout(3600);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \Exception("FFmpeg failed: " . $process->getErrorOutput());
        }
    }

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

            Log::info("Uploaded: {$s3Path}");
        }

        // Return the S3 path to the playlist
        return "hls/{$lessonId}/{$videoIndex}/playlist.m3u8";
    }

    private function cleanup($tempDir, $hlsDir): void
    {
        // Delete temp files
        array_map('unlink', glob($tempDir . '*'));
        @rmdir($tempDir);

        // Delete local HLS files
        array_map('unlink', glob($hlsDir . '*'));
        @rmdir($hlsDir);

        Log::info("Cleanup completed");
    }
}
