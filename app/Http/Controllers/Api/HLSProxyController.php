<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class HLSProxyController extends Controller
{
    public function proxy(Request $request, $path)
    {
        // Get the full S3 URL
        $bucket = config('filesystems.disks.s3.bucket');
        $region = config('filesystems.disks.s3.region');
        $s3Url = "https://{$bucket}.s3.{$region}.amazonaws.com/{$path}";

        // Fetch content from S3
        $response = Http::get($s3Url);

        if ($response->failed()) {
            abort(404, 'File not found');
        }

        // Determine content type
        $contentType = 'application/octet-stream';
        if (str_ends_with($path, '.m3u8')) {
            $contentType = 'application/vnd.apple.mpegurl';
        } elseif (str_ends_with($path, '.ts')) {
            $contentType = 'video/MP2T';
        }

        // Return with proper CORS headers
        return response($response->body())
            ->header('Content-Type', $contentType)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
            ->header('Access-Control-Allow-Headers', '*')
            ->header('Access-Control-Expose-Headers', 'Content-Length, Content-Type')
            ->header('Cache-Control', 'public, max-age=31536000');
    }
}
