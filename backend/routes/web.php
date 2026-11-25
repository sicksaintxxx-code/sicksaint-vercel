<?php

use Illuminate\Support\Facades\Route;

Route::get('/reset-password/{token}', function (string $token, \Illuminate\Http\Request $request) {
    $frontend = rtrim(env('FRONTEND_URL', 'http://localhost:3000'), '/');
    return redirect($frontend.'/reset-password?'.http_build_query(['token' => $token, 'email' => $request->query('email')]));
})->name('password.reset');

Route::get('/', function () {
    return view('welcome');
});
