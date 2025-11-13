<?php

namespace App\Http\Controllers;

use App\Jobs\SendEmailJob;
use App\Models\EmailTemplate;
use App\Models\User;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\SendEmail;

class EmailTemplateController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index($id)
    {
        $emailTemplate = EmailTemplate::where('user_id', $id)->get();
        return response()->json([
            'templates' => $emailTemplate,
            'status' => true
        ], 200);
    }

    public function send(Request $request, $id)
    {
        $template = $request->template;
        $userEmail = $request->email;
        $emailTemplate = EmailTemplate::where('template_id', $request->template)->firstOrFail();
        if ($template === 'Login Tempplate') {
            return $this->sendLoginOTPMail($request, $userEmail, $emailTemplate);
        } else if ($template === 'Low Credit Alert') {
            return $this->sendBalanceAlertMail($request, $id, $emailTemplate);
        } else if ($template === 'Login Otp Template') {
            return $this->sendLoginAlertMail($request, $userEmail, $emailTemplate);
        } else if ($template === 'API Key Generate') {
            return $this->sendKeyGenerateAlertMail($request, $userEmail, $emailTemplate);
        } else if ($template === 'Forgot Password') {
            return $this->sendForgotPasswordLink($request, $userEmail, $emailTemplate);
        } else if ($template === 'Password Changed') {
            return $this->sendForgotPasswordLink($request, $userEmail, $emailTemplate);
        } else if ($template === 'Schedule Campaign Execute') {
            return $this->sendScheduleCampaignConfirmation($request, $userEmail, $emailTemplate);
        } else if ($template === 'Login Security Status Update') {
            return $this->sendLoginSecurity($request, $userEmail, $emailTemplate);
        }
    }
    private function sendLoginOTPMail($request, $email, $emailTemplate)
    {
        $body = $emailTemplate->body;
        $body = str_replace('#otp', $request->otp, $body);
        $subject = $emailTemplate->subject;
        return $this->SendEmail($email, $subject, $body);
    }
    private function sendLoginSecurity($request, $email, $emailTemplate)
    {
        $body = $emailTemplate->body;
        $subject = $emailTemplate->subject;
        return $this->SendEmail($email, $subject, $body);
    }
    private function sendTwoFectorAlertIP($request, $email, $emailTemplate)
    {

        $body = $emailTemplate->body;
        $subject = $emailTemplate->subject;
        return $this->SendEmail($email, $subject, $body);
    }
    private function sendScheduleCampaignConfirmation($request, $email, $emailTemplate)
    {

        $body = $emailTemplate->body;
        $subject = $emailTemplate->subject;
        return $this->SendEmail($email, $subject, $body);
    }
    private function sendPasswordChangeAlert($request, $email, $emailTemplate)
    {

        $body = $emailTemplate->body;
        $body = str_replace(':OTP:', $request->otp, $body);
        $subject = $emailTemplate->subject;
        return $this->SendEmail($email, $subject, $body);
    }
    private function sendForgotPasswordLink($request, $email, $emailTemplate)
    {

        $body = $emailTemplate->body;
        $subject = $emailTemplate->subject;
        return $this->SendEmail($email, $subject, $body);
    }
    private function sendKeyGenerateAlertMail($request, $email, $emailTemplate)
    {

        $body = $emailTemplate->body;
        $subject = $emailTemplate->subject;
        return $this->SendEmail($email, $subject, $body);
    }
    private function sendLoginAlertMail($request, $email, $emailTemplate)
    {

        $body = $emailTemplate->body;
        $body = str_replace('#otp', $request->otp, $body);
        $subject = $emailTemplate->subject;
        return $this->SendEmail($email, $subject, $body);
    }
    private function sendBalanceAlertMail($request, $id, $emailTemplate)
    {
        $user = User::with(['balance', 'pricingModel'])->findOrFail($id);

        $user->latest_balance = optional($user->balance()->latest()->first())->total_credits;
        $user->role_name = $user->roles[0]->name;
        $user->pricing = $user->pricingModel()->latest()->first()->price_alert;
        unset($user->balance);
        unset($user->roles);
        unset($user->pricingModel);


        $body = $emailTemplate->body;
        $body = str_replace('[User Name]', $user->name, $body);
        $body = str_replace('[Latest Balance]', $user->latest_balance, $body);
        $body = str_replace('[Price Alert]', $user->pricing, $body);
        $subject = $emailTemplate->subject;
        return $this->SendEmail($user->email, $subject, $body);
    }

    private function SendEmail($email, $subject, $body)
    {
        try {
            $details = [
                'email' => $email,
                'title' => $subject,
                'body' => $body,
            ];

            // Dispatch email job to the queue
            dispatch(new SendEmailJob($details)); // FIXED

            return response()->json([
                'message' => 'Email has been queued successfully.',
                'status' => true
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to send email.',
                'status' => 'error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $template = new EmailTemplate();
        $template->user_id = $request->user_id;
        $template->template_id = $request->template_name;
        $template->subject = $request->subject;
        $template->body = $request->body;
        $template->status = 'active';
        $template->save();
        return response()->json(['status' => true, 'message' => 'Template Saved Successfully'], 200);
    }
    public function update(Request $request)
    {
        $template = EmailTemplate::find($request->id);
        $template->user_id = $request->user_id;
        $template->template_id = $request->template_name;
        $template->subject = $request->subject;
        $template->body = $request->body;
        $template->status = 'active';
        $template->save();
        return response()->json(['status' => true, 'message' => 'Template Updated Successfully'], 200);
    }
}
