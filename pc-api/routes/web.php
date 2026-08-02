<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json(['name' => '安防运维OA系统 API', 'version' => '1.0.0', 'docs' => '/api']);
});
