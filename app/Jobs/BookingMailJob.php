<?php

namespace App\Jobs;

use App\Mail\BookingMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mail;

class BookingMailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    protected $details;

    public function __construct($details)
    {
        $this->details = $details;

    }

    /**
     * Execute the job.
     */

    public function handle(): void
    {
        try {

            if (is_array($this->details) && isset($this->details[0]['email'])) {
                Mail::to($this->details[0]['email'])->send(new BookingMail($this->details));
            } else {
                Log::error('Invalid structure for details or email not found', ['details' => $this->details]);
                throw new \Exception('Invalid structure or email missing.');
            }
        } catch (\Exception $e) {
            DB::table('failed_jobs')->insert([
                'connection' => config('queue.default'),
                'queue' => 'default',
                'payload' => json_encode($this->details),
                'exception' => $e->getMessage(),
                'failed_at' => now(),
            ]);
            $this->fail($e);
        }
    }


}
