<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;

class CorporateExport implements FromView
{
    protected $CorporateBooking;

    public function __construct($CorporateBooking)
    {
        $this->CorporateBooking = $CorporateBooking;
    }

    public function view(): View
    {
        return view('exports.CorporateBooking', [
            'CorporateBooking' => $this->CorporateBooking
        ]);
    }
}
