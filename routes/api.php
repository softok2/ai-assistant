<?php

use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/roles', function (Request $request) {
    return response()->json([
        'roles' => Role::latest()->pluck('name', 'id')->all()
    ]);
});
