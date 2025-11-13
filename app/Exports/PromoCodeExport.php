<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;

class PromoCodeExport implements FromView
{
    protected $PromoCode;

    public function __construct($PromoCode)
    {
        $this->PromoCode = $PromoCode;
    }

    public function view(): View
    {
        return view('exports.PromoCode', [
            'PromoCode' => $this->PromoCode
        ]);
    }
}
