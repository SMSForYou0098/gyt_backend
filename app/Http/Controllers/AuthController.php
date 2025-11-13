<?php

namespace App\Http\Controllers;

use App\Jobs\SendEmailJob;
use App\Mail\SendEmail;
use App\Models\EmailTemplate;
use App\Models\LoginHistory;
use App\Models\SmsConfig;
use App\Models\SmsTemplate;
use App\Models\User;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use Mail;
use phpseclib3\Crypt\RC2;
use Illuminate\Support\Str;
use Jenssegers\Agent\Agent;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    public function verifyUser(Request $request)
    {

        try {

            $loginCredential = $request->input('data');
            //return $loginCredential;
            $userIp = $request->ip();
            $loginTime = now();
            if ($this->isSuspiciousInput($loginCredential)) {
                return response()->json([
                    'message' => 'Possible injection attempt blocked.',
                    'error' => 'Invalid input detected.'
                ], 400);
            }

            $normalizedNumber = preg_replace('/\D/', '', $loginCredential); // keep only digits

            $with91 = $without91 = null;
            if (strlen($normalizedNumber) === 12 && substr($normalizedNumber, 0, 2) === '91') {
                $with91    = $normalizedNumber;
                $without91 = substr($normalizedNumber, 2);
            } elseif (strlen($normalizedNumber) === 10) {
                $with91    = '91' . $normalizedNumber;
                $without91 = $normalizedNumber;
            }

            Cache::forget('login_attempt_' . $loginCredential);

            // --- Check user by email or number (with OR without 91) ---
            $user = User::where('email', $loginCredential)
                ->orWhere('number', $with91)
                ->orWhere('number', $without91)
                ->first();

            if (!$user) {
                return response()->json(['status' => false, 'error' => "Oops! We couldn't verify your login information"], 404);
            }

            if ($user->status != 1) {
                return response()->json(['error' => 'Your account has been blocked. Please contact administrator'], 404);
            }
            if ($user) {
                if ($user->authentication == 0) {
                    $this->sendOTP($user, $loginCredential);
                } else {
                    $sessionId = Str::random(40);

                    Cache::put('auth_session_' . $user->id, [
                        'session_id' => $sessionId,
                        'user_id' => $user->id,
                        'ip_address' => $userIp,
                        'login_time' => $loginTime,
                    ], now()->addMinutes(30));
                    return response()->json([
                        'status' => true,
                        'session_id' => $sessionId,
                        'pass_req' => true,
                        // 'user' => $user,
                        'auth_session' => $user->id,
                        'ip_address' => $userIp,
                        'login_time' => $loginTime,
                    ], 200);
                }
            }
            return response()->json(['status' => true], 200);
        } catch (\Exception $e) {
            // Return a generic error response
            return response()->json([
                'message' => 'An unexpected error occurred. Please try again later.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    //     public function verifyUser(Request $request)
    // {

    //     try {
          	
    //         $loginCredential = $request->input('data');
    //       //return $loginCredential;
    //         $userIp = $request->ip();
    //         $loginTime = now();
	// 		 if ($this->isSuspiciousInput($loginCredential)) {
    //             return response()->json([
    //                 'message' => 'Possible injection attempt blocked.',
    //                 'error' => 'Invalid input detected.'
    //             ], 400);
    //         }
    //         Cache::forget('login_attempt_' . $loginCredential);
    //         $user = User::where('email', $loginCredential)->orWhere('number', $loginCredential)->first();

    //         if (!$user) {
    //             return response()->json(['status' => false, 'error' => "Oops! We couldn't verify your login information"], 404);
    //         }

    //         if ($user->status != 1) {
    //             return response()->json(['error' => 'Your account has been blocked. Please contact administrator'], 404);
    //         }
    //         if ($user) {
    //             if ($user->authentication == 0) {
    //                 $this->sendOTP($user, $loginCredential);
    //             } else {
    //                 $sessionId = Str::random(40);

    //                 Cache::put('auth_session_' . $user->id, [
    //                     'session_id' => $sessionId,
    //                     'user_id' => $user->id,
    //                     'ip_address' => $userIp,
    //                     'login_time' => $loginTime,
    //                 ], now()->addMinutes(30));
    //                 return response()->json([
    //                     'status' => true,
    //                     'session_id' => $sessionId,
    //                     'pass_req' => true,
    //                     // 'user' => $user,
    //                     'auth_session' => $user->id,
    //                     'ip_address' => $userIp,
    //                     'login_time' => $loginTime,
    //                 ], 200);
    //             }
    //         }
    //         return response()->json(['status' => true], 200);
    //     } catch (\Exception $e) {
    //         // Return a generic error response
    //         return response()->json([
    //             'message' => 'An unexpected error occurred. Please try again later.',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }
    private function isSuspiciousInput($input): bool
    {
        $suspiciousPatterns = [
            '/(\b(SELECT|INSERT|UPDATE|DELETE|DROP|UNION|OR|AND|--|;)\b)/i',
            '/[\'"=;#%]/',
        ];

        if (is_bool($input)) return true; // block boolean
        if (is_array($input)) return true; // block arrays
        if (is_numeric($input)) return false;
        if (!is_string($input)) return false;

        $trimmed = strtolower(trim($input));

        $logicTricks = ['true', 'false', 'null', 'undefined', '1=1', '0=0', 'or 1=1', 'and 1=1'];

        foreach ($logicTricks as $trick) {
            if (str_contains($trimmed, $trick)) {
                return true;
            }
        }

        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }

        return false;
    }

    public function verifyUserRequest(Request $request)
    {

        $inputsToCheck = [
            'session_id'       => $request->session_id,
            'password'         => $request->password,
            'auth_session'     => $request->auth_session,
            'number'           => $request->number,
            'otp'              => $request->otp,
        ];

        foreach ($inputsToCheck as $key => $value) {
            if ($this->isSuspiciousInput($value)) {
                return response()->json([
                    'message' => "Suspicious input in '$key'",
                    'error' => "Invalid input detected in '$key'",
                ], 400);
            }
        }



        $passwordRequired = $request->passwordRequired;

        // $agent = new Agent();
        // $device = $agent->device();
        // $browser = $agent->browser();
        // $platform = $agent->platform();
        // $ip = $request->ip();

        if ($passwordRequired) {
            $usersessionId = $request->session_id;
            $password = $request->password;
            $authSession = $request->auth_session;

            $user =  $this->verifyPassword($usersessionId, $password, $authSession);
            // return  $this->verifyPassword($usersessionId, $password, $authSession);
            $data = $user->getData(true);
            $userId = $data['user']['id'] ?? null;


            if ($user) {
                $this->storeLoginHistory($userId);
            }

            return $user;
        } else {
            $number = $request->number;
            $otp = $request->otp;
            // return $this->verifyOTP($number, $otp);
            $user = $this->verifyOTP($number, $otp);
            $data = $user->getData(true);
            $userId = $data['user']['id'] ?? null;
            if ($user) {
                $this->storeLoginHistory($userId);
            }

            return $user;
        }
    }

    protected function storeLoginHistory($user)
    {
        // Skip storing login history if user is not authenticated
        $ip = request()->ip();

        $locationData = [];
        try {
            $locationData = Http::timeout(5)->get("http://ip-api.com/json/{$ip}")->json();
        } catch (\Exception $e) {
            $locationData = [];
        }

        LoginHistory::create([
            'user_id'   => $user,
          //'user_id'   => auth()->id(),
            'ip_address' => $ip,
            'country'    => $locationData['country']     ?? 'Unknown',
            'state'      => $locationData['regionName']  ?? 'Unknown',
            'city'       => $locationData['city']        ?? 'Unknown',
            'location'   => isset($locationData['lat'], $locationData['lon'])
                ? $locationData['lat'] . ',' . $locationData['lon']
                : null,
            'login_time' => now(),
        ]);
    }

    public function verifyUserSession(Request $request)
    {
        $token = $request->bearerToken(); // Get token from Authorization header

        if (!$token) {
            return response()->json(['message' => 'No Token Provided'], 401);
        }

        $user = Auth::guard('api')->user();

        if (!$user) {
            return response()->json(['message' => 'Invalid Token'], 401);
        }

        // Check if session_key is provided in request
        $sessionKey = $request->session_key;
        if (!$sessionKey) {
            return response()->json(['message' => 'No Session Key Provided'], 401);
        }

        // Retrieve session from Cache
        $cachedSession = Cache::get('auth_session_key_' . $user->id);
        if (!$cachedSession) {
            return response()->json(['message' => 'Session Expired or Not Found'], 401);
        }

        // Validate session key
        if (!isset($cachedSession['session_key']) || $cachedSession['session_key'] !== $sessionKey) {
            return response()->json(['message' => 'Invalid Session Key'], 401);
        }

        // Session expiration check
        if (!isset($cachedSession['expires_at']) || now()->gt($cachedSession['expires_at'])) {
            return response()->json(['status' => false, 'message' => 'Session expired'], 401);
        }

        // IP Address Match Check
        if (!isset($cachedSession['ip_address']) || $cachedSession['ip_address'] !== $request->ip()) {
            return response()->json(['message' => 'IP Address Mismatch'], 401);
        }

        // return response()->json([
        //     'message' => 'Valid Token and Session',
        //     'status' => true
        // ], 200);
        $userIp = $request->ip();

        return $this->_generateAuthResponse(
            $user,
            'Login successful',
            $cachedSession['session_key'],
            $userIp,
            $cachedSession['login_time'] ?? now()
        );
    }
    private function sendOTP($user, $loginCredential)
    {
        $number = $user->number;
        $email = $user->email;
        $otp = $this->generateOTP();
        $adminUsers = User::whereHas('roles', function ($query) {
            $query->where('name', 'Admin');
        })->first();
        $smsConfig = SmsConfig::where('user_id', $adminUsers->id)->first();
        $templateData = SmsTemplate::where('template_name', 'Login Template')->first();
        $apiKey = $smsConfig->api_key;
        $templateID = $templateData->template_id;
        $message = $templateData->content;
        $finalMessage = str_replace(':OTP', $otp, $message);
        //  $message = "Please use this OTP : " . $otp . " to continue on login \nSevak Trust\nGet Your Ticket\nSMS4U";
        $encodedMessage = urlencode($finalMessage);
        $modifiedNumber = $this->modifyNumber($number);

        $otpApi = "https://login.smsforyou.biz/V2/http-api.php?apikey=$apiKey&senderid=GTIKET&number=" . $modifiedNumber . "&message=" . $encodedMessage . "&format=json&template_id=$templateID";
        try {
            $client = new Client();
            $response = $client->request('GET', $otpApi);
            $this->sendMail($email, $otp);
            $responseBody = json_decode($response->getBody(), true);
            $cacheKey = 'otp_' . $loginCredential;
            Cache::put($cacheKey, $otp, now()->addMinutes(5));

            return response()->json(['message' => $responseBody, true]);
        } catch (RequestException $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function verifyPassword($usersessionId, $password, $auth_session)
    {
        if (!$usersessionId) {
            return response()->json(['status' => false, 'message' => 'Session ID is required'], 400);
        }
        $authSession = Cache::get('auth_session_' . $auth_session);

        if (!$authSession || !isset($authSession['session_id']) || $authSession['session_id'] !== $usersessionId) {
            return response()->json(['status' => false, 'message' => 'Session expired. Please refresh the page.'], 404);
        }

        $user = User::find($auth_session);
        if (!$user) {
            return response()->json(['status' => false, 'message' => 'User not found'], 404);
        }

        // Check if the user is blocked
        if (!$user->status) {
            return response()->json(['status' => false, 'message' => 'Your account is blocked due to multiple failed attempts. Please contact support.'], 403);
        }

        $failedAttemptsKey = 'failed_attempts_' . $user->id;
        $failedAttempts = Cache::get($failedAttemptsKey, 0);

        // Check password
        if (!Hash::check($password, $user->password)) {
            $failedAttempts++;

            if ($failedAttempts >= 5) {
                $user->update(['status' => 0]);
                session([$failedAttemptsKey => 0]);
                return response()->json(['status' => false, 'message' => 'Too many failed attempts. Your account is now blocked.'], 403);
            }

            // Store failed attempts count
            Cache::put($failedAttemptsKey, $failedAttempts, now()->addMinutes(30));


            return response()->json([
                'status' => false,
                'message' => 'Invalid password. Attempts remaining: ' . (5 - $failedAttempts),
            ], 401);
        }

        // Reset failed attempts on success
        Cache::forget($failedAttemptsKey);
        $userIp = request()->ip();
        $loginTime = now();
        $sessionKey = $user->id . '_' . time() . '_' . Str::random(10) . '_' . str_replace('.', '_', $userIp);
        $sessionData = [
            'session_key' => $sessionKey,
            'user_id' => $user->id,
            'ip_address' => $userIp,
            'login_time' => $loginTime,
            'expires_at' => now()->addSeconds(30), // Expiry Time Added
        ];
        Cache::put('auth_session_key_' . $user->id, $sessionData, now()->addSeconds(30));

        if (Hash::check($password, $user->password)) {
            return $this->_generateAuthResponse($user, 'Password verified successfully', $sessionKey, $userIp, $loginTime);
        } else {
            return response()->json(['status' => false, 'message' => 'Invalid password'], 404);
        }
    }

    private function _generateAuthResponse($user, $message, $sessionKey, $userIp, $loginTime)
    {
        $token = $user->createToken('MyAppToken')->accessToken;
        $role = $user->roles->first();
        $rolePermissions = $role ? $role->permissions : collect();
        $userPermissions = $user ? $user->permissions : collect();

        $allPermissions = $rolePermissions->merge($userPermissions)->unique('name');
        $allPermissionNames = $allPermissions->pluck('name');

        $userArray = $user->toArray();
        $userArray['role'] = $role ? $role->name : null;
        $userArray['permissions'] = $allPermissionNames;
        unset($userArray['roles']);

        return response()->json([
            'status' => true,
            'token' => $token,
            'user' => $userArray,
            'session_key' => $sessionKey,
            'user_ip' => $userIp,
            'login_time' => $loginTime,
            'message' => $message
        ], 200);
    }

    public function sendMail($email, $otp)
    {
        try {

            $template = 'Login Tempplate';
            $emailTemplate = EmailTemplate::where('template_id', $template)->firstOrFail();
            //return response()->json($emailTemplate,200);
            return $this->sendLoginOTPMail($email, $otp, $emailTemplate);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch data', 'message' => $e->getMessage()], 500);
        }
    }

    private function sendLoginOTPMail($email, $otp, $emailTemplate)
    {
        $body = $emailTemplate->body;
        $body = str_replace(':OTP:', $otp, $body);
        $subject = $emailTemplate->subject;
        // return response()->json($body,200);
        return $this->SendEmail($email, $subject, $body);
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

    private function modifyNumber($number)
    {
        $mobNumber = (string) $number;
        if (strlen($mobNumber) === 10) {
            $mobNumber = '91' . $mobNumber;
            return $mobNumber;
        } else if (strlen($mobNumber) === 12) {
            return $number;
        }
        return null; // Handle invalid number lengths if needed
    }
    private function generateOTP($length = 6)
    {
        $otp = '';
        for ($i = 0; $i < $length; $i++) {
            $otp .= mt_rand(0, 9);
        }
        return $otp;
    }
    private function verifyOTP($number, $otp)
    {
        $loginCredential = $number;

        // Retrieve OTP from cache
        $cacheKey = 'otp_' . $loginCredential;
        $cachedOtp = Cache::get($cacheKey);

        if ($cachedOtp && ($cachedOtp == $otp)) {
            if (filter_var($loginCredential, FILTER_VALIDATE_EMAIL)) {
                $user = User::where('email', $loginCredential)->first();
            } else {
                $user = User::where('number', $loginCredential)->first();
            }
            if ($user) {
                $userIp = request()->ip();
                $loginTime = now();
                $sessionKey = $user->id . '_' . time() . '_' . Str::random(10) . '_' . str_replace('.', '_', $userIp);

                $sessionData = [
                    'session_key' => $sessionKey,
                    'user_id' => $user->id,
                    'ip_address' => $userIp,
                    'login_time' => $loginTime,
                    'expires_at' => now()->addSeconds(30), // Expiry Time Added
                ];
                Cache::put('auth_session_key_' . $user->id, $sessionData, now()->addSeconds(30));


                $token = $user->createToken('MyAppToken')->accessToken;
                $role = $user->roles->first();
                $rolePermissions = $role ? $role->permissions : collect();
                $userPermissions = $user ? $user->permissions : collect();
                // Merge the collections and remove duplicates
                $allPermissions = $rolePermissions->merge($userPermissions)->unique('name');

                // Pluck the 'name' attribute
                $allPermissionNames = $allPermissions->pluck('name');
                $userArray = $user->toArray();
                $userArray['role'] = $role->name;
                $userArray['permissions'] = $allPermissionNames;
                unset($userArray['roles']);
                if ($user) {
                    return $this->_generateAuthResponse($user, 'OTP verified successfully', $sessionKey, $userIp, $loginTime);
                }
            } else {
                return response()->json(['error' => 'Invalid or expired OTP'], 401);
            }
        } else {
            return response()->json(['error' => 'Invalid or expired OTP'], 401);
        }
    }

    public function Backuplogin(Request $request)
    {
        try {
            $email = $request->input('email');
            $password = $request->input('password');
            $ip = file_get_contents('https://api.ipify.org');
            $user = User::where('email', $email)->first();

            if (!$user) {
                return response()->json(['emailError' => 'Wrong email', 'ip' => $ip], 401);
            }

            if ($user->status != 1) {
                return response()->json(['error' => 'Your account has been blocked. Please contact administrator'], 401);
            }

            if (!Hash::check($password, $user->password)) {
                $cacheKey = 'login_attempt_' . $email;
                $attemptData = Cache::get($cacheKey, ['count' => 0, 'last_attempt' => null]);
                $lastAttemptTime = $attemptData['last_attempt'];

                if ($lastAttemptTime && now()->diffInMinutes($lastAttemptTime) < 1) {
                    $attemptData['count']++;
                } else {
                    $attemptData['count'] = 1;
                    $attemptData['last_attempt'] = now();
                }

                Cache::put($cacheKey, $attemptData, now()->addMinutes(1));

                if ($attemptData['count'] >= 5) {
                    return $this->DisableUser($user);
                }

                return response()->json(['code' => 'WP', 'passwordError' => 'Wrong password', 'ip' => $ip], 401);
            }

            Cache::forget('login_attempt_' . $email);
            $token = $user->createToken('MyAppToken')->accessToken;

            $userArray = $user->toArray();

            if ($user->ip_auth === 'true') {
                $userIPs = json_decode($user->ip_addresses);
                if (in_array($ip, $userIPs)) {
                    if ($user->two_fector_auth === 'true') {
                        return response()->json(['token' => $token, 'user' => $userArray, 'ip' => $ip, 'two_factor_auth' => true], 200);
                    } else {
                        return response()->json(['message' => 'Login By Ip', 'token' => $token, 'user' => $userArray, 'ip' => $ip], 200);
                    }
                } else {
                    return response()->json(['ipAuthError' => 'IP authentication failed', 'ip' => $ip], 401);
                }
            }

            if ($user->two_fector_auth === 'true') {
                return response()->json(['token' => $token, 'user' => $userArray, 'ip' => $ip, 'two_factor_auth' => true], 200);
            }

            return response()->json(['status' => true, 'token' => $token, 'user' => $userArray, 'ip' => $ip], 200);
        } catch (\Exception $e) {
            // Return a generic error response
            return response()->json([
                'message' => 'An unexpected error occurred. Please try again later.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    protected function DisableUser($user)
    {
        $user->status = 'inactive';
        $user->save();
        return response()->json(['error' => 'Your account has been blocked. Please contact administrator.'], 429);
    }

    public function register(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string',
                'email' => 'required|email|unique:users,email',
                'number' => 'required|number|unique:users,number',
                'password' => 'required|string|min:6',
            ]);

            $user = new User([
                'name' => $request->name,
                'email' => $request->email,
                'number' => $request->number,
                'password' => Hash::make($request->password),
            ]);

            $user->save();

            return response()->json(['status' => true, 'message' => 'User registered successfully'], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Registration failed',
                'error' => $e->getMessage()
            ], 500); // 500 for internal server error
        }
    }

    public function changePassword(Request $request, $id)
    {
        try {
            // Validate request data
            $request->validate([
                'current_password' => 'required',
                'password' => 'required|min:8',
            ]);
            // Get the authenticated user
            $user = User::findOrFail($id);

            // Check if the current password matches the one provided
            if (!Hash::check($request->current_password, $user->password)) {
                throw new \Exception('Current password is incorrect');
            }

            // Update the user's password
            $user->password = Hash::make($request->password);
            $user->save();

            return response()->json([
                'status' => true,
                'message' => 'Password updated successfully',
                'email' => $user->email
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
