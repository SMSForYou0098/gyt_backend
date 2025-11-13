<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;


class ComplimentaryBookingsExport implements FromView
{
    protected $ComplimentaryBookings;

    public function __construct($ComplimentaryBookings)
    {
        $this->ComplimentaryBookings = $ComplimentaryBookings;
    }

    public function view(): View
    {
        return view('exports.ComplimentaryBookings', [
            'ComplimentaryBookings' => $this->ComplimentaryBookings
        ]);
    }
}
