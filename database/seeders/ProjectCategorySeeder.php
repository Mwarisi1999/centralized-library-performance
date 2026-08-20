<?php

namespace Database\Seeders;

use App\Models\ProjectCategory;
use Illuminate\Database\Seeder;

class ProjectCategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'Library Services',
            'Research Support',
            'Digital Repository',
            'User Training',
            'Cataloguing',
            'Circulation Services',
            'M&E Reporting',
            'Administration',
            'Project Management',
            'Website Management',
            'ICT Support & Assistance',
            'Digitisation',
            'Staff Development',
            'Community Outreach',
            'Other',
        ] as $name) {
            ProjectCategory::updateOrCreate(
                ['name' => $name],
                ['is_active' => true],
            );
        }
    }
}
