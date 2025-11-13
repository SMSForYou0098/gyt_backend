<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Mail\SendEmail;
use Illuminate\Support\Facades\DB;
use Mail;

class SendEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $details;

    /**
     * Create a new job instance.
     */
    public function __construct($details)
    {
        $this->details = $details;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // $email = new SendEmail();

        // Mail::to($this->details['email'])->send($email);

        try {
            // Send email
            Mail::to($this->details['email'])->send(new SendEmail($this->details['title'], $this->details['body']));
        } catch (\Exception $e) {
            DB::table('failed_jobs')->insert([
                'connection' => config('queue.default'),
                'queue' => 'default',
                'payload' => json_encode($this->details),
                'exception' => $e->getMessage(),
                'failed_at' => now(),
            ]);

            // Fail the job
            $this->fail($e);
        }
    }
}
