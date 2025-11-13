<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactUsMail extends Mailable
{
    use Queueable, SerializesModels;

    public $contactData;

    public function __construct($contactData)
    {
        $this->contactData = $contactData;
    }
    public function build()
    {
        return $this->subject('New Contact Us Submission')
            ->markdown('email.contact_us');

        if (!empty($this->contactData->image)) {
            $path = public_path('uploads/contactUs/' . $this->contactData->image);

            if (file_exists($path)) {
                $email->attach($path);
            }
        }

        return $email;
    }
}
