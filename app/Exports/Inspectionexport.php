<?php

namespace App\Exports;

use App\Models\Inspection;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class InspectionExport implements
    FromCollection,
    WithHeadings,
    WithMapping,
    WithStyles,
    ShouldAutoSize,
    WithTitle
{
    public function __construct(
        private Collection $inspections
    ) {}

    public function title(): string
    {
        return 'Laporan Inspeksi';
    }

    public function collection(): Collection
    {
        return $this->inspections;
    }

    public function headings(): array
    {
        return [
            'No',
            'Judul Inspeksi',
            'Lokasi',
            'Inspector',
            'Template',
            'Status',
            'Tgl Dibuat',
            'Tgl Disubmit',
            'Tgl Selesai',
        ];
    }

    public function map($inspection): array
    {
        static $no = 0;
        $no++;

        return [
            $no,
            $inspection->title,
            $inspection->location ?? '-',
            $inspection->assignedTo?->name ?? '-',
            $inspection->template?->title ?? '-',
            strtoupper($inspection->status),
            $inspection->created_at?->format('d/m/Y'),
            $inspection->submitted_at?->format('d/m/Y') ?? '-',
            $inspection->completed_at?->format('d/m/Y') ?? '-',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            // Header row styling
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType'   => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '534AB7'],
                ],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
        ];
    }
}