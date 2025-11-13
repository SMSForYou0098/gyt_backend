<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;

class SponsorBookingExport implements FromView
{
    protected $bookings;

    public function __construct($bookings)
    {
        $this->bookings = $bookings;
    }

    public function view(): View
    {
        return view('exports.sponsorBooking', [
            'bookings' => $this->bookings
        ]);
    }
}
