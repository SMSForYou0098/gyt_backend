<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;

class AttndyBookingExport implements FromView
{
    protected $attendees;
    protected $corporateUsers;
    protected $eventName;

    public function __construct($attendees,$corporateUsers, $eventName)
    {
        $this->attendees = $attendees;
        $this->corporateUsers = $corporateUsers;
        $this->eventName = $eventName;
    }

    public function view(): View
    {
        return view('exports.attendyBooking', [
            'attendees' => $this->attendees,
            'corporateUsers' => $this->corporateUsers, // ✅ Add this line
            'eventName' => $this->eventName,
        ]);
    }
}
