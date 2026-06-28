<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Customer\CheckoutController;

// Endpoint untuk Register
Route::post('/register', [AuthController::class, 'register']);

// Endpoint untuk Checkout
// Gunakan middleware auth:sanctum jika user harus login untuk checkout
Route::post('/checkout', [CheckoutController::class, 'store']);