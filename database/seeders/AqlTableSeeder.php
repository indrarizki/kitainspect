<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AqlTableSeeder extends Seeder
{
    /**
     * AQL 2.5% lookup table — ISO 2859-1
     * Levels: I (lenient), II (normal/default), III (tightened)
     */
    public function run(): void
    {
        DB::table('aql_tables')->truncate();

        $data = [
            // Level I
            ['inspection_level' => 'I', 'lot_size_from' => 2,     'lot_size_to' => 8,      'sample_size' => 2,   'accept_no' => 0, 'reject_no' => 1],
            ['inspection_level' => 'I', 'lot_size_from' => 9,     'lot_size_to' => 15,     'sample_size' => 2,   'accept_no' => 0, 'reject_no' => 1],
            ['inspection_level' => 'I', 'lot_size_from' => 16,    'lot_size_to' => 25,     'sample_size' => 3,   'accept_no' => 0, 'reject_no' => 1],
            ['inspection_level' => 'I', 'lot_size_from' => 26,    'lot_size_to' => 50,     'sample_size' => 5,   'accept_no' => 0, 'reject_no' => 1],
            ['inspection_level' => 'I', 'lot_size_from' => 51,    'lot_size_to' => 90,     'sample_size' => 8,   'accept_no' => 0, 'reject_no' => 1],
            ['inspection_level' => 'I', 'lot_size_from' => 91,    'lot_size_to' => 150,    'sample_size' => 13,  'accept_no' => 1, 'reject_no' => 2],
            ['inspection_level' => 'I', 'lot_size_from' => 151,   'lot_size_to' => 280,    'sample_size' => 20,  'accept_no' => 1, 'reject_no' => 2],
            ['inspection_level' => 'I', 'lot_size_from' => 281,   'lot_size_to' => 500,    'sample_size' => 32,  'accept_no' => 2, 'reject_no' => 3],
            ['inspection_level' => 'I', 'lot_size_from' => 501,   'lot_size_to' => 1200,   'sample_size' => 50,  'accept_no' => 3, 'reject_no' => 4],
            ['inspection_level' => 'I', 'lot_size_from' => 1201,  'lot_size_to' => 3200,   'sample_size' => 80,  'accept_no' => 5, 'reject_no' => 6],
            ['inspection_level' => 'I', 'lot_size_from' => 3201,  'lot_size_to' => 10000,  'sample_size' => 125, 'accept_no' => 7, 'reject_no' => 8],
            ['inspection_level' => 'I', 'lot_size_from' => 10001, 'lot_size_to' => 35000,  'sample_size' => 200, 'accept_no' => 10,'reject_no' => 11],
            ['inspection_level' => 'I', 'lot_size_from' => 35001, 'lot_size_to' => 150000, 'sample_size' => 315, 'accept_no' => 14,'reject_no' => 15],

            // Level II (Normal — default)
            ['inspection_level' => 'II', 'lot_size_from' => 2,     'lot_size_to' => 8,      'sample_size' => 2,   'accept_no' => 0,  'reject_no' => 1],
            ['inspection_level' => 'II', 'lot_size_from' => 9,     'lot_size_to' => 15,     'sample_size' => 3,   'accept_no' => 0,  'reject_no' => 1],
            ['inspection_level' => 'II', 'lot_size_from' => 16,    'lot_size_to' => 25,     'sample_size' => 5,   'accept_no' => 0,  'reject_no' => 1],
            ['inspection_level' => 'II', 'lot_size_from' => 26,    'lot_size_to' => 50,     'sample_size' => 8,   'accept_no' => 0,  'reject_no' => 1],
            ['inspection_level' => 'II', 'lot_size_from' => 51,    'lot_size_to' => 90,     'sample_size' => 13,  'accept_no' => 1,  'reject_no' => 2],
            ['inspection_level' => 'II', 'lot_size_from' => 91,    'lot_size_to' => 150,    'sample_size' => 20,  'accept_no' => 1,  'reject_no' => 2],
            ['inspection_level' => 'II', 'lot_size_from' => 151,   'lot_size_to' => 280,    'sample_size' => 32,  'accept_no' => 2,  'reject_no' => 3],
            ['inspection_level' => 'II', 'lot_size_from' => 281,   'lot_size_to' => 500,    'sample_size' => 50,  'accept_no' => 3,  'reject_no' => 4],
            ['inspection_level' => 'II', 'lot_size_from' => 501,   'lot_size_to' => 1200,   'sample_size' => 80,  'accept_no' => 5,  'reject_no' => 6],
            ['inspection_level' => 'II', 'lot_size_from' => 1201,  'lot_size_to' => 3200,   'sample_size' => 125, 'accept_no' => 7,  'reject_no' => 8],
            ['inspection_level' => 'II', 'lot_size_from' => 3201,  'lot_size_to' => 10000,  'sample_size' => 200, 'accept_no' => 10, 'reject_no' => 11],
            ['inspection_level' => 'II', 'lot_size_from' => 10001, 'lot_size_to' => 35000,  'sample_size' => 315, 'accept_no' => 14, 'reject_no' => 15],
            ['inspection_level' => 'II', 'lot_size_from' => 35001, 'lot_size_to' => 150000, 'sample_size' => 500, 'accept_no' => 21, 'reject_no' => 22],

            // Level III (Tightened)
            ['inspection_level' => 'III', 'lot_size_from' => 2,     'lot_size_to' => 8,      'sample_size' => 3,   'accept_no' => 0,  'reject_no' => 1],
            ['inspection_level' => 'III', 'lot_size_from' => 9,     'lot_size_to' => 15,     'sample_size' => 5,   'accept_no' => 0,  'reject_no' => 1],
            ['inspection_level' => 'III', 'lot_size_from' => 16,    'lot_size_to' => 25,     'sample_size' => 8,   'accept_no' => 0,  'reject_no' => 1],
            ['inspection_level' => 'III', 'lot_size_from' => 26,    'lot_size_to' => 50,     'sample_size' => 13,  'accept_no' => 1,  'reject_no' => 2],
            ['inspection_level' => 'III', 'lot_size_from' => 51,    'lot_size_to' => 90,     'sample_size' => 20,  'accept_no' => 1,  'reject_no' => 2],
            ['inspection_level' => 'III', 'lot_size_from' => 91,    'lot_size_to' => 150,    'sample_size' => 32,  'accept_no' => 2,  'reject_no' => 3],
            ['inspection_level' => 'III', 'lot_size_from' => 151,   'lot_size_to' => 280,    'sample_size' => 50,  'accept_no' => 3,  'reject_no' => 4],
            ['inspection_level' => 'III', 'lot_size_from' => 281,   'lot_size_to' => 500,    'sample_size' => 80,  'accept_no' => 5,  'reject_no' => 6],
            ['inspection_level' => 'III', 'lot_size_from' => 501,   'lot_size_to' => 1200,   'sample_size' => 125, 'accept_no' => 7,  'reject_no' => 8],
            ['inspection_level' => 'III', 'lot_size_from' => 1201,  'lot_size_to' => 3200,   'sample_size' => 200, 'accept_no' => 10, 'reject_no' => 11],
            ['inspection_level' => 'III', 'lot_size_from' => 3201,  'lot_size_to' => 10000,  'sample_size' => 315, 'accept_no' => 14, 'reject_no' => 15],
            ['inspection_level' => 'III', 'lot_size_from' => 10001, 'lot_size_to' => 35000,  'sample_size' => 500, 'accept_no' => 21, 'reject_no' => 22],
            ['inspection_level' => 'III', 'lot_size_from' => 35001, 'lot_size_to' => 150000, 'sample_size' => 800, 'accept_no' => 21, 'reject_no' => 22],
        ];

        DB::table('aql_tables')->insert($data);

        $this->command->info('AQL tables seeded: ' . count($data) . ' rows (Level I, II, III)');
    }
}