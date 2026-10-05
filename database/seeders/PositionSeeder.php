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
                'name' => 'Deputy University Librarian',
                'code' => 'DEP-UNIV-LIB',
            ],
            [
                'name' => 'Senior Librarian - Technical Services',
                'code' => 'SR-LIB-TECH',
            ],
            [
                'name' => 'Senior Librarian - Digital Services',
                'code' => 'SR-LIB-DIG',
            ],
            [
                'name' => 'Senior Librarian - Circulation, User & Reference Services',
                'code' => 'SR-LIB-CURS',
            ],
            [
                'name' => 'Senior Librarian - Archives & Special Collections',
                'code' => 'SR-LIB-ARCH',
            ],
            [
                'name' => 'Senior Librarian - E-Resources and Acquisitions',
                'code' => 'SR-LIB-ERES',
            ],
            [
                'name' => 'Senior Librarian - In Charge Campus/Institute',
                'code' => 'CAMP-LIB',
            ],
            [
                'name' => 'Senior Librarian',
                'code' => 'SR-LIB',
            ],
            [
                'name' => 'Librarian',
                'code' => 'LIB',
            ],
            [
                'name' => 'Assistant Librarian',
                'code' => 'ASST-LIB',
            ],
            [
                'name' => 'Library Assistant',
                'code' => 'LIB-AST',
            ],
            [
                'name' => 'Librarian - Systems Librarian',
                'code' => 'LIB-SYS',
            ],
            [
                'name' => 'Assistant Librarian - Systems Administrator',
                'code' => 'ASST-LIB-SYS',
            ],
            [
                'name' => 'Administrative Secretary',
                'code' => 'ADMIN-SEC',
            ],
            [
                'name' => 'Librarian - Head Binder',
                'code' => 'LIB-HEAD-BIND',
            ],
            [
                'name' => 'Library Assistant - Book Binder',
                'code' => 'LIB-AST-BIND',
            ],
            [
                'name' => 'Senior Library Clerk - Senior Book Binder',
                'code' => 'SR-CLERK-BIND',
            ],
            [
                'name' => 'Senior Library Clerk - ICT Technician',
                'code' => 'SR-CLERK-ICT',
            ],
            [
                'name' => 'Senior Library Clerk',
                'code' => 'SR-LIB-CLERK',
            ],
            [
                'name' => 'Library Clerk',
                'code' => 'LIB-CLERK',
            ],
            [
                'name' => 'Library Attendant',
                'code' => 'LIB-ATT',
            ],
            [
                'name' => 'Office Attendant',
                'code' => 'OFF-ATT',
            ],
            [
                'name' => 'Intern',
                'code' => 'INTERN',
            ],
            [
                'name' => 'Other',
                'code' => 'OTHER',
            ],
            [
                'name' => 'Monitoring & Evaluation Officer',
                'code' => 'MEO',
            ],
        ];

        $activeCodes = collect($positions)->pluck('code');

        foreach ($positions as $index => $position) {
            $record = Position::withTrashed()->firstOrNew(['code' => $position['code']]);
            $record->fill($position + [
                'is_active' => true,
                'sort_order' => $index + 1,
            ]);

            if ($record->trashed()) {
                $record->restore();
            } else {
                $record->save();
            }
        }

        Position::query()
            ->where(fn ($query) => $query->whereNull('code')->orWhereNotIn('code', $activeCodes))
            ->update(['is_active' => false]);
    }
}
