<?php

use Illuminate\Support\Facades\Route;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

Route::get('/', function () {
    return view('welcome');
});

// ROUTE SAKTI: Hapus ini setelah berhasil login!
Route::get('/fix-user', function () {
    try {
        // Cek koneksi database dulu
        \DB::connection()->getPdo();
        
        $user = User::updateOrCreate(
            ['email' => 'ridho@gmail.com'],
            [
                'name' => 'Ridho Alhasan',
                'password' => Hash::make('12345678')
            ]
        );
        return "✅ Akun " . $user->name . " berhasil diperbarui di Supabase! Sekarang coba login di HP.";
    } catch (\Exception $e) {
        return response()->json([
            'status' => '❌ Error Database Gan!',
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => substr($e->getTraceAsString(), 0, 500) . '...'
        ], 500);
    }
});
