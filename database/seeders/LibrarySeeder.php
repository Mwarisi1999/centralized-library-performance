<?php

namespace Database\Seeders;

use App\Models\Campus;
use App\Models\Library;
use Illuminate\Database\Seeder;

class LibrarySeeder extends Seeder
{
    public function run(): void
    {
        $libraries = [
            [
                'campus_code' => 'MAIN',
                'name' => 'Engineering Library',
                'code' => 'ENG-LIB',
            ],
            [
                'campus_code' => 'NANG',
                'name' => 'Science & Education Library',
                'code' => 'SCI-EDU-LIB',
            ],
            [
                'campus_code' => 'ARAP',
                'name' => 'Agriculture and Animal Sciences Library',
                'code' => 'AGR-ANI-LIB',
            ],
            [
                'campus_code' => 'MBALE',
                'name' => 'Health Sciences Library',
                'code' => 'HEALTH-LIB',
            ],
            [
                'campus_code' => 'NAMA',
                'name' => 'Natural Resources & Environmental Sciences Library',
                'code' => 'NRES-LIB',
            ],
            [
                'campus_code' => 'PALL',
                'name' => 'Management Sciences Library',
                'code' => 'MGT-LIB',
            ],
        ];

        foreach ($libraries as $libraryData) {
            $campus = Campus::where('code', $libraryData['campus_code'])->firstOrFail();

            Library::updateOrCreate(
                ['code' => $libraryData['code']],
                [
                    'campus_id' => $campus->id,
                    'name' => $libraryData['name'],
                    'is_active' => true,
                ]
            );
        }
    }
}
