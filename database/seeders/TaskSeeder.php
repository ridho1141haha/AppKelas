<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Task;

class TaskSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Task::truncate();

        $tasks = [
            [
                'title' => 'Latihan Aljabar',
                'subject' => 'Matematika',
                'deadline' => now()->addDays(2)->format('Y-m-d'),
                'description' => 'Kerjakan halaman 45-50 di buku paket.',
                'status' => 'pending'
            ],
            [
                'title' => 'Tugas Descriptive Text',
                'subject' => 'Bahasa Inggris',
                'deadline' => now()->addDays(5)->format('Y-m-d'),
                'description' => 'Tulis teks deskriptif tentang tempat wisata favorit.',
                'status' => 'pending'
            ],
            [
                'title' => 'Praktikum Database',
                'subject' => 'Produktif RPL',
                'deadline' => now()->addDays(1)->format('Y-m-d'),
                'description' => 'Selesaikan migrasi database dan API untuk project kelas.',
                'status' => 'pending'
            ]
        ];

        foreach ($tasks as $task) {
            Task::create($task);
        }
    }
}
