<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Customer\CheckoutController;

Route::post('/mobile/login', [AuthController::class, 'loginMobile']);
Route::post('/mobile/register', [AuthController::class, 'registerMobile']);

// routes/api.php
Route::get('/mobile/products', [App\Http\Controllers\Customer\ProductController::class, 'getMobileProducts']);
// Endpoint untuk Checkout dari Mobile
Route::post('/mobile/checkout', [CheckoutController::class, 'storeMobile']);

// Rute untuk nampilin gambar (Bypass 403 Windows)
Route::get('/mobile/image/{path}', function ($path) {
    // Cari gambar di folder asli
    $fullPath = storage_path('app/public/' . $path);
    
    // Kalau gambarnya ada, tampilkan. Kalau nggak ada, kasih error 404
    if (!file_exists($fullPath)) {
        return response()->json(['error' => 'Gambar tidak ditemukan'], 404);
    }
    
    return response()->file($fullPath);
})->where('path', '.*'); // <-- Biar bisa baca kalau ada sub-folder kayak 'products/baju.jpg'