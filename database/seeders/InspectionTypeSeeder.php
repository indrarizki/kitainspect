<?php

namespace Database\Seeders;

use App\Models\InspectionType;
use Illuminate\Database\Seeder;

class InspectionTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'name'        => 'Final Inspection',
                'slug'        => 'final',
                'description' => 'Inspeksi akhir sebelum produk dikemas dan dikirim.',
            ],
            [
                'name'        => 'Inline Inspection',
                'slug'        => 'inline',
                'description' => 'Inspeksi saat proses produksi berlangsung (mid-production).',
            ],
            [
                'name'        => 'Sample Inspection',
                'slug'        => 'sample',
                'description' => 'Inspeksi berdasarkan sampel dari lot produksi.',
            ],
            [
                'name'        => 'Pre-shipment Inspection',
                'slug'        => 'pre_shipment',
                'description' => 'Inspeksi sebelum pengiriman — memastikan produk sesuai PO.',
            ],
        ];

        foreach ($types as $type) {
            InspectionType::firstOrCreate(
                ['slug' => $type['slug']],
                [...$type, 'is_active' => true]
            );
        }

        $this->command->info('Inspection types seeded: ' . count($types) . ' types');
    }
}