<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;

class BookingExport implements FromView
{
    protected $Booking;

    public function __construct($Booking)
    {
        $this->Booking = $Booking;
    }

    public function view(): View
    {
        return view('exports.Booking', [
            'Booking' => $this->Booking
        ]);
    }
}
