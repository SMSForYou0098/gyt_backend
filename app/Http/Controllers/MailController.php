<?php

namespace App\Http\Controllers;

use App\Jobs\SendEmailJob;
use App\Models\EmailConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\SendEmail;
class MailController extends Controller
{

    public function send(Request $request)
    {
        $details = [
            'email' => 'janak.rana@smsforyou.biz',
            'title' => 'Test Email from Laravel',
            'body' => 'This is a test email using dynamic configuration.'
        ];

        // Dispatch email job to the queue
        dispatch(new SendEmailJob($details));

        return response()->json([
            'message' => 'Email has been queued successfully.',
            'status' => true
        ], 200);
    }
 

    public function index()
    {
        $emailConfig = EmailConfig::first();
        return response()->json(['status' => true, 'data' => $emailConfig], 200);
    }

    public function store(Request $request)
    {
        try {
            // Retrieve existing email configuration or create a new instance
            $emailConfig = EmailConfig::firstOrNew([]);
            // Update email configuration with request data
            $emailConfig->mail_driver = $request->input('mail_driver');
            $emailConfig->mail_host = $request->input('mail_host');
            $emailConfig->mail_port = $request->input('mail_port');
            $emailConfig->mail_username = $request->input('mail_username');
            $emailConfig->mail_password = $request->input('mail_password');
            $emailConfig->mail_encryption = $request->input('mail_encryption');
            $emailConfig->mail_from_address = $request->input('mail_from_address');
            $emailConfig->mail_from_name = $request->input('mail_from_name');

            // Save the email configuration
            $emailConfig->save();

            return response()->json(['status' => true, 'success' => 'Email configuration saved successfully.'], 200);
        } catch (\Exception $e) {
            // Handle errors
            return response()->json(['status' => false, 'error' => 'Failed to save email configuration.', 'message' => $e->getMessage()], 500);
        }
    }
}
