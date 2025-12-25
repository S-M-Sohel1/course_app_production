<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\MemberController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\LessonController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\SslCommerzController;
use App\Http\Controllers\Api\HLSKeyController;
use App\Http\Controllers\Api\HLSProxyController;
use App\Http\Controllers\Api\HLSStreamController;
use Illuminate\Support\Facades\Broadcast;
// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

 Route::post('/login',[MemberController::class,'login']);

// HLS streaming proxy with proper CORS headers
Route::options('/hls-stream/{path}', [HLSStreamController::class, 'options'])->where('path', '.*');
Route::get('/hls-stream/{path}', [HLSStreamController::class, 'stream'])->where('path', '.*');

// HLS proxy to add CORS headers (serves playlist and segments through Laravel)
Route::get('/hls-proxy/{path}', [HLSProxyController::class, 'proxy'])->where('path', '.*');

// HLS decryption key endpoint (must be outside auth middleware for Video.js to access)
Route::get('/hls/keys/{keyId}', [HLSKeyController::class, 'getKey']);
Route::options('/hls/keys/{keyId}', function() {
    return response('', 200, [
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Methods' => 'GET, OPTIONS',
        'Access-Control-Allow-Headers' => '*',
        'Access-Control-Max-Age' => '86400',
    ]);
});

Route::group(['middleware' => 'auth:sanctum'], function () {
    Route::any('/courseList', [CourseController::class, 'courseList']);
    Route::any('/courseNewestList', [CourseController::class, 'courseNewestList']);
    Route::any('/coursePopularList', [CourseController::class, 'coursePopularList']);
    Route::any('/courseDetail', [CourseController::class, 'courseDetail']);
    Route::any('/coursesBought', [CourseController::class, 'coursesBought']);
    Route::any('/coursePurchaseStatus', [CourseController::class, 'coursePurchaseStatus']);
    Route::any('/lessonList', [LessonController::class, 'lessonList']);
    Route::any('/lessonDetail', [LessonController::class, 'lessonDetail']);
    Route::any('/checkout', [PaymentController::class, 'checkout']);
    Route::any('/coursesSearchDefault', [CourseController::class, 'coursesSearchDefault']);
    Route::any('/coursesSearch', [CourseController::class, 'coursesSearch']);
    Route::any('/authorCourseList', [CourseController::class, 'authorCourseList']);
    Route::any('/courseAuthor', [CourseController::class, 'courseAuthor']);
    Route::any('/update_photo',[MemberController::class,'update_photo']);
    Route::any('/memberPay',[MemberController::class,'memberPayment']);
    Route::any('/changeName',[MemberController::class,'changeName']);
    Route::any('/changeDescription',[MemberController::class,'changeDescription']);
          Route::post('/users',[MessageController::class,'users']);
        Route::post('/sendMessage',[MessageController::class,'sendMessage']);
        Route::post('/getMessage/{id}',[MessageController::class,'getMessage']);
        Route::post('/getUserId',[MemberController::class,'getUserId']);
});

Route::any('/webGoHooks', [PaymentController::class, 'webGoHooks']);
//Route::post('/pay-via-ajax', [SslCommerzPaymentController::class, 'payViaAjax']);
Route::post('/sslcommerz/create', [SslCommerzController::class, 'createPayment']);
Route::match(['get', 'post'], '/sslcommerz/success', [SslCommerzController::class, 'success']);
Route::match(['get', 'post'], '/sslcommerz/fail', [SslCommerzController::class, 'fail']);
Route::match(['get', 'post'], '/sslcommerz/cancel', [SslCommerzController::class, 'cancel']);
Route::post('/sslcommerz/ipn', [SslCommerzController::class, 'ipn']);
Route::post('/sslcommerz/validate', [SslCommerzController::class, 'validatePayment']);

Route::get('/uploads/{filename}', function ($filename) {
    $path = public_path('uploads/' . $filename);

    if (!file_exists($path)) {
        abort(404);
    }

Broadcast::routes(['middleware' => ['auth:sanctum']]);
    Route::any('/webGoHooks',[PaymentController::class,'webGoHooks']);
    return response()->file($path, [
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
        'Access-Control-Allow-Headers' => '*',
    ]);
});

// Serve storage files with CORS headers - same pattern as uploads
Route::get('/storage/{path}', function ($path) {
    $fullPath = storage_path('app/public/' . $path);

    if (!file_exists($fullPath)) {
        abort(404);
    }

    return response()->file($fullPath, [
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
        'Access-Control-Allow-Headers' => '*',
    ]);
})->where('path', '.*');