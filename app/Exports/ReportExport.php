<?php

namespace App\Exports;

use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Concerns\FromCollection;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Concerns\WithStyles;


class ReportExport implements FromCollection
{
    /**
     * @return \Illuminate\Support\Collection
     */
    private $reports;
    public function __construct($reports)
    {
        $this->reports = $reports;
    }
    public function collection()
    {
        return new Collection($this->reports);
    }
    public function headings(): array
    {
        return [
            'Name',
            'From',
            'To',
            'From Name',
            'Message',
            // 'date'
        ];
    }
    public function styles(Worksheet $sheet)
    {
        // Apply styles to the header row
        $sheet->getStyle('A1:F1')->applyFromArray([
            'font' => [
                'bold' => true,
            ]
        ]);
    }
}
