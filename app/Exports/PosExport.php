<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;

class PosExport implements FromView
{
    protected $PosBooking;

    public function __construct($PosBooking)
    {
        $this->PosBooking = $PosBooking;
    }

    public function view(): View
    {
        return view('exports.PosBooking', [
            'PosBooking' => $this->PosBooking
        ]);
    }
}
