<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/firebase/push-test', function () {
    abort_unless((bool) config('mobile_push.test_endpoint_enabled'), 404);

    return view('firebase-push-test');
})->name('firebase.push-test');
