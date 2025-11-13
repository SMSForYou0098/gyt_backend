<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class EventExport implements FromCollection, WithHeadings
{
    protected $events;

    public function __construct($events)
    {
        $this->events = $events;
    }

    public function collection()
    {
        return collect($this->events);
    }

    public function headings(): array
    {
        return [
            'Sr No',
            'Name',
            'Category',
            'Organizer',
            'Event Date',
            'Event Type',
            'Status',
            'Organisation'
        ];
    }
}