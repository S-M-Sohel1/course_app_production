<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HLSStreamController extends Controller
{
    public function stream(Request $request, $path)
    {
        // Validate that the path starts with 'hls/' for security
        if (!str_starts_with($path, 'hls/')) {
            abort(403, 'Access denied');
        }

        $s3 = Storage::disk('s3');
        
        // Check if file exists
        if (!$s3->exists($path)) {
            abort(404, 'File not found');
        }

        // For .m3u8 playlist files, we need to rewrite the URLs to point to our proxy
        if (str_ends_with($path, '.m3u8')) {
            $content = $s3->get($path);
            
            // Replace relative paths with our proxy URLs
            $baseUrl = url('/api/hls-stream/');
            $pathDir = dirname($path);
            
            // Replace segment references
            $content = preg_replace_callback(
                '/^(?!#)(.+\.ts)$/m',
                function($matches) use ($pathDir, $baseUrl) {
                    $segmentPath = $pathDir . '/' . $matches[1];
                    // Ensure proper slash between baseUrl and path
                    return rtrim($baseUrl, '/') . '/' . ltrim($segmentPath, '/');
                },
                $content
            );
            
            // Replace key URI references - point to our Laravel API endpoint instead of S3
            $content = preg_replace_callback(
                '/#EXT-X-KEY:METHOD=AES-128,URI="([^"]+)"(,IV=0x[0-9a-f]+)?/i',
                function($matches) {
                    // Extract the key URI
                    $keyUri = $matches[1];
                    
                    // Extract key ID from the URI (whether it's a full URL or relative path)
                    if (preg_match('/\/api\/hls\/keys\/([^\/\?]+)/', $keyUri, $keyMatches)) {
                        // Already has /api/hls/keys/ format, extract the ID
                        $keyId = $keyMatches[1];
                    } else {
                        // It's a relative path like "encryption.key"
                        $keyId = basename($keyUri, '.key');
                    }
                    
                    // Build the full tag with our current API endpoint
                    $newUri = url("/api/hls/keys/{$keyId}");
                    $result = '#EXT-X-KEY:METHOD=AES-128,URI="' . $newUri . '"';
                    
                    // Preserve IV if present
                    if (isset($matches[2]) && $matches[2]) {
                        $result .= $matches[2];
                    }
                    
                    return $result;
                },
                $content
            );
            
            return response($content, 200, [
                'Content-Type' => 'application/vnd.apple.mpegurl',
                'Content-Length' => strlen($content),
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, HEAD, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, Range',
                'Access-Control-Expose-Headers' => 'Content-Length, Content-Range',
                'Cache-Control' => 'no-cache',
            ]);
        }

        // For .key files, DON'T redirect - let them go through the HLSKeyController
        if (str_ends_with($path, '.key')) {
            abort(404, 'Key files should be accessed via /api/hls/keys/{keyId}');
        }

        // For .ts segments, generate a temporary signed URL and redirect
        // This allows S3 to serve the file directly (much faster than proxying)
        $temporaryUrl = $s3->temporaryUrl($path, now()->addMinutes(5));
        
        return redirect($temporaryUrl, 302, [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, HEAD, OPTIONS',
        ]);
    }

    public function options(Request $request)
    {
        return response('', 200, [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, HEAD, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Range',
            'Access-Control-Max-Age' => '3600',
        ]);
    }
}
