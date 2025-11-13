<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithHeadings;

// class UserExport implements FromView
class UserExport implements FromCollection, WithHeadings
{
    protected $users;

    public function __construct($users)
    {
        $this->users = $users;
    }
    public function collection()
    {
        return collect($this->users);
    }

    public function view(): View
    {
        return view('exports.users', [
            'users' => $this->users
        ]);
    }
    public function headings(): array
    {
        return [
            'Sr No',
            'Name',
            'Email',
            'Mobile Number',
            'Organisation'
        ];
    }
}
