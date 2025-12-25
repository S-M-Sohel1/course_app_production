<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

// Test 1: Simulate API request
$request = Illuminate\Http\Request::create('/api/lessonDetail', 'GET');
app()->instance('request', $request);

$lesson = App\Models\Lesson::find(6);

echo "=== Simulated API Request (should return HLS) ===\n";
echo "Request path: " . request()->path() . "\n";
echo "Is API route: " . (request()->is('api/*') ? 'YES' : 'NO') . "\n\n";
echo json_encode($lesson->toArray(), JSON_PRETTY_PRINT);
echo "\n\n";

// Test 2: Simulate admin panel request
$request = Illuminate\Http\Request::create('/admin/lessons', 'GET');
app()->instance('request', $request);

$lesson = App\Models\Lesson::find(6);

echo "=== Simulated Admin Request (should return MP4) ===\n";
echo "Request path: " . request()->path() . "\n";
echo "Is API route: " . (request()->is('api/*') ? 'YES' : 'NO') . "\n\n";
echo json_encode($lesson->toArray(), JSON_PRETTY_PRINT);
echo "\n";
