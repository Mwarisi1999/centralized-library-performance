<?php

namespace Database\Seeders;

use App\Models\Campus;
use Illuminate\Database\Seeder;

class CampusSeeder extends Seeder
{
    public function run(): void
    {
        $campuses = [
            [
                'code' => 'MAIN',
                'name' => 'Main Campus',
                'location' => 'Busitema',
            ],
            [
                'code' => 'NANG',
                'name' => 'Nangongera Campus',
                'location' => 'Nangongera',
            ],
            [
                'code' => 'ARAP',
                'name' => 'Arapai Campus',
                'location' => 'Arapai',
            ],
            [
                'code' => 'MBALE',
                'name' => 'Mbale Campus',
                'location' => 'Mbale',
            ],
            [
                'code' => 'NAMA',
                'name' => 'Namasagali Campus',
                'location' => 'Namasagali',
            ],
            [
                'code' => 'PALL',
                'name' => 'Pallisa Campus',
                'location' => 'Pallisa',
            ],
        ];

        foreach ($campuses as $campus) {
            Campus::updateOrCreate(
                ['code' => $campus['code']],
                $campus
            );
        }
    }
}
