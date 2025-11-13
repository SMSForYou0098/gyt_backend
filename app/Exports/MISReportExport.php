<?php
namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class MISReportExport implements FromCollection, WithHeadings
{
    protected $reportData;

    public function __construct(array $reportData)
    {
        $this->reportData = $reportData;
    }

    public function collection()
    {
        $flattened = [];

        foreach ($this->reportData as $item) {
            foreach ($item['Users'] as $user) {
                $flattened[] = [
                    'Date' => $item['Date'],
                    'Event Name' => $item['Event Name'],
                    'Ticket Name' => $item['Ticket Name'],
                    'Promocode' => $item['Promocode'],
                    'User Name' => $user['User Name'],
                    'User Email' => $user['User Email'],
                    'Booking ID' => $user['Booking ID'],
                    'Discount' => $user['Discount'],
                ];
            }
        }

        return collect($flattened);
    }

    public function headings(): array
    {
        return [
            'Date',
            'Event Name',
            'Ticket Name',
            'Promocode',
            'User Name',
            'User Email',
            'Booking ID',
            'Discount',
        ];
    }
}
