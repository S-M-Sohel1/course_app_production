@php
    $hlsPlaylist = $getState();
    
    if (!$hlsPlaylist) {
        echo '<div class="text-gray-500">⏳ Video processing or not available</div>';
        return;
    }
    
    // Use Laravel streaming proxy instead of direct S3 URL
    $hlsUrl = url("/api/hls-stream/{$hlsPlaylist}");
    $playerId = 'vid' . uniqid();
@endphp

@once
    <script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>
@endonce

<div style="margin: 10px 0;">
    <video 
        id="{{ $playerId }}"
        controls 
        style="width: 640px; max-width: 100%; height: auto;"
    >
        Your browser does not support HLS playback.
    </video>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log('🔵 DOM ready, initializing player {{ $playerId }}');
        
        setTimeout(function() {
            const video = document.getElementById('{{ $playerId }}');
            const videoSrc = '{{ $hlsUrl }}';
            
            console.log('🟢 initPlayer called for {{ $playerId }}');
            console.log('📹 Video element:', video);
            console.log('🔗 HLS URL:', videoSrc);
            console.log('Hls available:', typeof Hls !== 'undefined');
            
            if (!video) {
                console.error('❌ Video element not found!');
                return;
            }
            
            if (typeof Hls !== 'undefined' && Hls.isSupported()) {
                console.log('✅ HLS.js is supported, initializing...');
                
                const hls = new Hls({
                    debug: true,
                    enableWorker: false, // Disable worker to avoid WebCrypto issues over HTTP
                    xhrSetup: function(xhr, url) {
                        console.log('📡 Loading:', url);
                    }
                });
                
                hls.loadSource(videoSrc);
                hls.attachMedia(video);
                
                hls.on(Hls.Events.MANIFEST_PARSED, function() {
                    console.log('✅ HLS manifest loaded successfully');
                });
                
                hls.on(Hls.Events.ERROR, function(event, data) {
                    console.error('❌ HLS error:', data);
                    if (data.fatal) {
                        switch(data.type) {
                            case Hls.ErrorTypes.NETWORK_ERROR:
                                console.error('Fatal network error encountered, trying to recover');
                                hls.startLoad();
                                break;
                            case Hls.ErrorTypes.MEDIA_ERROR:
                                console.error('Fatal media error encountered, trying to recover');
                                hls.recoverMediaError();
                                break;
                            default:
                                console.error('Fatal error, cannot recover');
                                hls.destroy();
                                break;
                        }
                    }
                });
            } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
                console.log('🍎 Native HLS support detected');
                video.src = videoSrc;
            } else {
                console.error('❌ No HLS support detected!');
            }
        }, 500); // Wait for HLS.js to load
    });
</script>
