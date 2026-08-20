<?php

namespace Database\Seeders;

use App\Models\Position;
use Illuminate\Database\Seeder;

class PositionSeeder extends Seeder
{
    public function run(): void
    {
        $positions = [
            [
                'name' => 'University Librarian',
                'code' => 'UNIV-LIB',
            ],
            [
                'name' => 'Campus Librarian',
                'code' => 'CAMP-LIB',
            ],
            [
                'name' => 'Librarian',
                'code' => 'LIB',
            ],
            [
                'name' => 'Library Assistant',
                'code' => 'LIB-AST',
            ],
            [
                'name' => 'ICT Staff',
                'code' => 'ICT',
            ],
            [
                'name' => 'Monitoring & Evaluation Officer',
                'code' => 'MEO',
            ],
            [
                'name' => 'Intern',
                'code' => 'INTERN',
            ],
            [
                'name' => 'Library Attendant',
                'code' => 'LIB-ATT',
            ],
            [
                'name' => 'Other',
                'code' => 'OTHER',
            ],
        ];

        foreach ($positions as $position) {
            Position::updateOrCreate(
                ['code' => $position['code']],
                $position
            );
        }
    }
}
