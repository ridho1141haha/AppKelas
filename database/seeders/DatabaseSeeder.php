<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            ScheduleSeeder::class,
            TaskSeeder::class,
        ]);

        // Pastikan user Ridho selalu ada
        User::updateOrCreate(
            ['email' => 'ridho@gmail.com'],
            [
                'name' => 'Ridho Alhasan',
                'password' => \Hash::make('12345678')
            ]
        );
    }
}
