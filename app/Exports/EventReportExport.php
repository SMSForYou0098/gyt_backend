<?php

namespace App\Exports;


use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;

class EventReportExport implements FromView
{
    protected $eventReport;

    public function __construct($eventReport)
    {
        $this->eventReport = $eventReport;
    }

    public function view(): View
    {
        return view('exports.ExportEvent', [
            'eventReport' => $this->eventReport
        ]);
    }
}
