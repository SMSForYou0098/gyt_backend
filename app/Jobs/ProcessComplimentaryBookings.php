<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\ComplimentaryBookings;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class ProcessComplimentaryBookings implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $userList;
    protected $userId;
    protected $ticketId;
    protected $batchId;


    /**
     * Create a new job instance.
     */
    public function __construct($userData, $userId, $ticketId)
    {
        $this->userList = $userData;
        $this->userId = $userId;
        $this->ticketId = $ticketId;
        $this->batchId = uniqid();
    }
    /**
     * Execute the job.
     */
    public function handle()
    {
        DB::beginTransaction();
        try {
            $bookings = [];
            $data = unserialize($this->userList);
            Log::debug('User data inside handle:');
            // if (is_null($this->userList)) {
            //     throw new \Exception("User is null");
            // }
            foreach ($data as $userData) {
                // Find or create user
                $existingUser = User::where('number', $userData['number'])
                    ->orWhere('email', $userData['email'])
                    ->first();

                if (!$existingUser) {
                    $existingUser = User::create([
                        'name' => $userData['name'],
                        'email' => $userData['email'],
                        'number' => $userData['number'],
                        'password' => Hash::make($userData['number']),
                        'status' => true,
                        'reporting_user' => $this->userId
                    ]);

                    // Assign role
                   // $existingUser->assignRole('User');
                }

                $bookings[] = [
                    'user_id' => $existingUser->id,
                    'batch_id' => $this->batchId,
                    'ticket_id' => $this->ticketId,
                    'token' => $userData['token'] ?? $this->generateRandomCode(),
                    'name' => $existingUser->name,
                    'email' => $existingUser->email,
                    'number' => $existingUser->number,
                    'reporting_user' => $this->userId,
                    'status' => 0,
                    'created_at' => now(),
                    'type' => 'imported'
                ];
            }

            // Bulk insert bookings
            ComplimentaryBookings::insert($bookings);

            DB::commit();

        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage());
            Log::error($e->getLine());
            throw $e;
        }
    }


    private function generateRandomCode($length = 8)
    {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789@$*';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }
}
