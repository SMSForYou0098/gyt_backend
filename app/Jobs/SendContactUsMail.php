<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use App\Mail\ContactUsMail;
use App\Models\ContactUs;

class SendContactUsMail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    protected $contactId;
    protected $email;

    public function __construct($contactId, $email)
    {
        $this->contactId = $contactId;
        $this->email = $email;
    }

    public function handle()
    {
        $contactData = ContactUs::find($this->contactId);

        if ($contactData) {
            Mail::to($this->email)->send(new ContactUsMail($contactData));
        }
    }
}
