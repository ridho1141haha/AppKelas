<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Schedule;

class ScheduleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Kosongkan tabel dulu biar gak dobel
        Schedule::truncate();

        $data = [
            // Kategori: Normatif - Adaptif
            ['day' => 'Senin', 'type' => 'Normatif - Adaptif', 'subjects' => 'B.ING - B.JAWA - MAT - B.INDO - SEJ', 'dismissal_time' => '15.15 (Upacara 14.35)'],
            ['day' => 'Selasa', 'type' => 'Normatif - Adaptif', 'subjects' => 'PP - PABP - MAT - B.ING', 'dismissal_time' => '13.45'],
            ['day' => 'Rabu', 'type' => 'Normatif - Adaptif', 'subjects' => 'PJOK - MAT - B.INDO - PABP - B.JAWA', 'dismissal_time' => '15.15'],
            ['day' => 'Kamis', 'type' => 'Normatif - Adaptif', 'subjects' => 'B.ING - PABP - B.INDO - PP', 'dismissal_time' => '13.45'],
            ['day' => 'Jumat', 'type' => 'Normatif - Adaptif', 'subjects' => 'SEJ - PJOK - B.ING - BK', 'dismissal_time' => '13.45'],

            // Kategori: Produktif - Minggu 1
            ['day' => 'Senin', 'type' => 'Produktif - Minggu 1', 'subjects' => 'PTGM', 'dismissal_time' => '17.00 (Upacara 16.00)'],
            ['day' => 'Selasa', 'type' => 'Produktif - Minggu 1', 'subjects' => 'PTGM - PPB', 'dismissal_time' => '17.00'],
            ['day' => 'Rabu', 'type' => 'Produktif - Minggu 1', 'subjects' => 'PPB', 'dismissal_time' => '17.00'],
            ['day' => 'Kamis', 'type' => 'Produktif - Minggu 1', 'subjects' => 'MPP', 'dismissal_time' => '17.00'],
            ['day' => 'Jumat', 'type' => 'Produktif - Minggu 1', 'subjects' => 'MPP - PKK', 'dismissal_time' => '13.30'],

            // Kategori: Produktif - Minggu 2
            ['day' => 'Senin', 'type' => 'Produktif - Minggu 2', 'subjects' => 'PKK', 'dismissal_time' => '17.00 (Upacara 16.00)'],
            ['day' => 'Selasa', 'type' => 'Produktif - Minggu 2', 'subjects' => 'PKK - PW', 'dismissal_time' => '15.15'],
            ['day' => 'Rabu', 'type' => 'Produktif - Minggu 2', 'subjects' => 'PW', 'dismissal_time' => '17.00'],
            ['day' => 'Kamis', 'type' => 'Produktif - Minggu 2', 'subjects' => 'PW - BD', 'dismissal_time' => '15.15'],
            ['day' => 'Jumat', 'type' => 'Produktif - Minggu 2', 'subjects' => 'BD', 'dismissal_time' => '13.30'],
        ];

        foreach ($data as $item) {
            Schedule::create($item);
        }
    }
}
