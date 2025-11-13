<?php

namespace App\Console\Commands;

use App\Services\FirebaseNotificationService;
use Illuminate\Console\Command;
use App\Models\User;

class SendPushNotification extends Command
{
    protected $signature = 'push:send {user_id?} {--all : Send to all users}';
    protected $description = 'Send push notification to users';

    public function handle()
    {
        $fcmService = new FirebaseNotificationService();
        $userId = $this->argument('user_id');
        $sendToAll = $this->option('all');

        $title = $this->ask('Enter notification title');
        $body = $this->ask('Enter notification body');
        $data = ['type' => 'test', 'id' => '1'];

        if ($sendToAll) {
            $users = User::has('fcmTokens')->get();
            $bar = $this->output->createProgressBar(count($users));
            $this->info('Sending notification to all users...');
            
            foreach ($users as $user) {
                $fcmService->sendToUser($user->id, $title, $body, $data);
                $bar->advance();
            }
            
            $bar->finish();
            $this->newLine();
            $this->info('Notifications sent successfully!');
            
        } elseif ($userId) {
            $result = $fcmService->sendToUser($userId, $title, $body, $data);
            $this->info('Notification sent to user #' . $userId);
            $this->info(json_encode($result));
            
        } else {
            $this->error('Please provide a user_id or use --all option');
        }
    }
}