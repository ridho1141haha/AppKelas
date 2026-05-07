<?php

use Illuminate\Support\Facades\Route;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

Route::get('/', function () {
    return view('welcome');
});

// ROUTE SAKTI: Hapus ini setelah berhasil login!
Route::get('/fix-user', function () {
    $user = User::updateOrCreate(
        ['email' => 'ridho@gmail.com'],
        [
            'name' => 'Ridho Alhasan',
            'password' => Hash::make('12345678')
        ]
    );
    return "Akun " . $user->name . " berhasil diperbarui di Supabase! Sekarang coba login di HP.";
});
