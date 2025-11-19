<?php

namespace App\Http\Controllers;

use App\Exports\UserExport;
use App\Jobs\SendEmailJob;
use App\Models\AgentEvent;
use App\Models\EmailTemplate;
use App\Models\Event;
use App\Models\ScannerGate;
use App\Models\Shop;
use App\Models\User;
use App\Models\UserTicket;
use App\Models\WhatsappApi;
use App\Services\SmsService;
use App\Services\WhatsappService;
use Auth;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Crypt;
use App\Services\PermissionService;

class UserController extends Controller
{

    public function indexlist()
    {
        $loggedInUser = Auth::user();
        $date = Carbon::now()->format('Y-m-d');

        if ($loggedInUser->hasRole('Admin')) {

            $users = User::with(['roles', 'reportingUser'])->latest()->get();
        } elseif ($loggedInUser->hasRole('Box Office Manager')) {
            $organizerId = $loggedInUser->reporting_user ?? $loggedInUser->id;

            $userIds = $this->getAllReportingUserIds($organizerId);

            $users = User::with(['roles', 'reportingUser'])
                ->whereIn('id', $userIds)
                ->latest()
                ->get();
        } else {
            $users = User::with(['roles', 'reportingUser'])
                ->where('reporting_user', $loggedInUser->id)
                ->latest()->get();
        }


        $allUsers = $users->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'contact' => $user->number,
                'email' => $user->email,
                'role_name' => $user->roles->pluck('name')->first(),
                'status' => $user->staus,
                'reporting_user' => $user->reportingUser ? $user->reportingUser->name : null,
                'organisation' => $user->organisation ? $user->organisation : null,
                'created_at' => $user->created_at,
                'authentication' => $user->authentication,

            ];
        });
        $organizers = User::role('Organizer')->get();
        $formattedUsers = $users->map(function ($user) {
            return [
                'value' => $user->id,
                'label' => $user->name,
                'number' => $user->number,
                'email' => $user->email,
                'role_name' => $user->roles->pluck('name')->first(),
            ];
        });
        $org = $organizers->map(function ($user) {
            return [
                'value' => $user->id,
                'label' => $user->name,
            ];
        });

        return response()->json(['status' => true, 'users' => $formattedUsers, 'allData' => $allUsers, 'organizers' => $org]);
    }

    private function getAllReportingUserIds($organizerId)
    {
        $userIds = collect([$organizerId]);

        $children = User::where('reporting_user', $organizerId)->pluck('id');

        foreach ($children as $childId) {
            $userIds = $userIds->merge($this->getAllReportingUserIds($childId));
        }

        return $userIds->unique();
    }


    public function index(Request $request, PermissionService $permissionService)
    {
        $loggedInUser = Auth::user();
        $eventType = $request->type;

        // Determine date range
        if ($eventType === 'all') {
            $startDate = null;
            $endDate = null;
        } elseif ($request->has('date')) {
            $dates = explode(',', $request->date);
            if (count($dates) === 1 || ($dates[0] === $dates[1])) {
                $startDate = Carbon::parse($dates[0])->startOfDay();
                $endDate = Carbon::parse($dates[0])->endOfDay();
            } elseif (count($dates) === 2) {
                $startDate = Carbon::parse($dates[0])->startOfDay();
                $endDate = Carbon::parse($dates[1])->endOfDay();
            } else {
                return response()->json(['status' => false, 'message' => 'Invalid date format'], 400);
            }
        } else {
            $startDate = Carbon::today()->startOfDay();
            $endDate = Carbon::today()->endOfDay();
        }

        // Base query
        // if ($loggedInUser->hasRole('Admin')) {
        //     $query = User::with(['roles', 'reportingUser']);
        // } else {
        //     $query = User::with(['roles', 'reportingUser'])
        //         ->where('reporting_user', $loggedInUser->id);
        // }
        if ($loggedInUser->hasRole('Admin')) {
            // Admin → all users
            $query = User::with(['roles', 'reportingUser', 'latestLoginHistory']);
        } elseif ($loggedInUser->hasRole('Organizer')) {
            // Organizer → only their sub-users
            $query = User::with(['roles', 'reportingUser', 'latestLoginHistory'])
                ->where('reporting_user', $loggedInUser->id);
        } else {
            // Other roles → only self
            $query = User::with(['roles', 'reportingUser', 'latestLoginHistory'])
                ->where('id', $loggedInUser->id);
        }

        // Apply date filter only if not "all"
        if ($eventType !== 'all' && $startDate && $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        }

        $users = $query->latest()->get();
        $permissions = $permissionService->check(['View Contact', 'View Email']);
        // return response()->json($permissions);
        $canViewContact = $permissions['View Contact'];
        $canViewEmail = $permissions['View Email'];

        $allUsers = $users->map(function ($user) use ($canViewContact, $canViewEmail) {
            $login = $user->latestLoginHistory;
            return [
                'id' => $user->id,
                'name' => $user->name,
                'contact' => $canViewContact ? $user->number : null,
                'email' => $canViewEmail ? $user->email : null,
                'role_name' => $user->roles->pluck('name')->first(),
                'status' => $user->staus,
                'reporting_user' => $user->reportingUser ? $user->reportingUser->name : null,
                'organisation' => $user->organisation,
                'created_at' => $user->created_at,
                'authentication' => $user->authentication,

                'ip_address' => $login->ip_address ?? null,
                'city'       => $login->city ?? null,
                'state'      => $login->state ?? null,
                'last_login' => $login->created_at ?? null,
            ];
        });

        $organizers = User::role('Organizer')->get();
        $formattedUsers = $users->map(function ($user) use ($canViewContact, $canViewEmail) {
            return [
                'value' => $user->id,
                'label' => $user->name,
                'number' => $canViewContact ? $user->number : null,
                'email' => $canViewEmail ? $user->email : null,
                'role_name' => $user->roles->pluck('name')->first(),
            ];
        });

        $org = $organizers->map(function ($user) {
            return [
                'value' => $user->id,
                'label' => $user->name,
            ];
        });

        return response()->json([
            'status' => true,
            'users' => $formattedUsers,
            'allData' => $allUsers,
            'organizers' => $org
        ]);
    }

    public function create(Request $request, SmsService $smsService, WhatsappService $whatsappService)
    {
        try {
            $request->validate([
                'number' => 'required|string|unique:users,number',
            ], [
                'number.unique' => 'The mobile number has already been taken.',
            ]);
            if ($request->email) {

                $request->validate([
                    'email' => 'email|unique:users,email,NULL,id,deleted_at,NULL',
                    // 'email' => 'required|email|unique:users,email',
                ], [
                    'email.unique' => 'The email has already been taken.',
                ]);
            }

            // Additional validation for other fields
            $validatedData = $request->validate([
                'name' => 'required|string|max:255',
            ]);

            $user = new User();
            $user->name = $request->name;
            $user->email = $request->email ?? $request->number . '@gyt.co.in';
            $user->number = $request->number;
            $user->company_name = $request->company_name;
            $user->designation = $request->designation;
            $user->address = $request->address;
            $user->organisation = $request->organisation;
            $user->alt_number = $request->alt_number;
            $user->pincode = $request->pincode;
            $user->state = $request->state;
            $user->city = $request->city;
            $user->bank_name = $request->bank_name;
            $user->bank_number = $request->bank_number;
            $user->bank_ifsc = $request->bank_ifsc;
            $user->bank_branch = $request->bank_branch;
            $user->bank_micr = $request->bank_micr;
            $user->tax_number = $request->tax_number;
            $user->reporting_user = $request->reporting_user;
            $user->authentication = $request->authentication;
            $user->payment_method = $request->payment_method;
            $user->agent_disc = $request->agent_disc;
            $user->status = true;
            $user->agreement_status = filter_var($request->agreement_status, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
            $user->org_type_of_company = $request->org_type_of_company;
            $user->org_office_address = $request->org_office_address;
            $user->org_name_signatory = $request->org_name_signatory;
            $user->org_signature_type = $request->org_signature_type;
            $user->org_gst_no = $request->org_gst_no;
            $user->pan_no = $request->pan_no;
            $user->account_holder = $request->account_holder;
            $user->password = Hash::make($request->password);

            if ($request->hasFile('photo')) {
                $file = $request->file('photo');
                if ($file->isValid()) {
                    $folder = 'profile/' . str_replace(' ', '_', $request->name);
                    $filePath = $this->storeFile($file, $folder); // uses your storeFile method
                    $user->photo = $filePath;
                }
            }
            if ($request->hasFile('org_signatory_image')) {
                $file = $request->file('org_signatory_image');
                if ($file->isValid()) {
                    $folder = 'org_sign_image/' . str_replace(' ', '_', $request->org_signatory_image);
                    $filePath = $this->storeFile($file, $folder); // uses your storeFile method
                    $user->photo = $filePath;
                }
            }
            if ($request->hasFile('doc')) {
                $file = $request->file('doc');
                if ($file->isValid()) {
                    $folder = 'document/' . str_replace(' ', '_', $request->name);
                    $filePath = $this->storeFile($file, $folder); // uses your storeFile method
                    $user->doc = $filePath;
                }
            }

            $user->save();
            $userId = $user->id;
            $this->updateUserRole($request, $user);
            if ($request->role_name == 'Shop Keeper') {
                $this->shopStore($request, $userId);
            }
            if ($request->role_name == 'Agent') {
                $this->agentEventStore($request, $userId);
            }
            if ($request->role_name == 'Scanner') {
                $this->scannerGateStore($request, $userId);
            }
            if ($request->role_name == 'Accreditation') {
                $this->userTicketStore($request, $userId);
            }

            //send whatsapp or sms
            if ($request->role_name == 'Organizer') {
                $buttonValue = User::where('id', $request->user_id)->first();
                $filename = $buttonValue ? basename($buttonValue->card_url) : null;

                $whatsappTemplate = WhatsappApi::where('title', 'Acc Ready')->first();
                $whatsappTemplateName = $whatsappTemplate->template_name ?? '';

                $admin = User::role('Admin', 'api')->first();
                $organizer = $user;

                if ($admin) {
                    $adminData = (object) [
                        'name' => $admin->name,
                        'number' => $admin->number,
                        'templateName' => 'new registatiion admin reminder',
                        'replacements' => [
                            ':O_Name' => $organizer->name,
                            ':O_number' => $organizer->number,
                            ':C_Email' => $organizer->email,
                            ':C_Registered' => $organizer->id,
                        ]
                    ];

                    $smsService->send($adminData);
                }

                // === Send to ORGANIZER ===
                if ($organizer) {
                    $allowedDomain = rtrim(env('ALLOWED_DOMAIN', 'https://ssgarba.com/'), '/');
                    $organizerData = (object) [
                        'name' => $organizer->name,
                        'number' => $organizer->number,
                        'templateName' => 'organizer registration',
                        'replacements' => [
                            ':S_Link' => $allowedDomain . '/',
                            // ':S_Link'     => 'https://getyourticket.in/',
                            ':C_number' => $admin->number,
                        ]
                    ];

                    $smsService->send($organizerData);
                }
            }

            return response()->json(['status' => true, 'message' => 'User Created Successfully', 'user' => $user], 201);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to create user', 'error' => $e->getMessage()], 500);
        }
    }

    private function updateUserRole($request, $user)
    {
        $defaultRoleName = 'User';
        if ($request->has('role_id') && $request->role_id) {
            $role = Role::find($request->role_id);
            if ($role) {
                $user->syncRoles([]);
                $user->assignRole($role);
            }
        } else {
            $defaultRole = Role::where('name', $defaultRoleName)->first();
            if ($defaultRole) {
                $user->syncRoles([]);
                $user->assignRole($defaultRole);
            }
        }
    }

    public function edit(string $id)
    {
        $allUser = User::all();
        $roles = Role::all();

        $user = User::with(['reportingUser', 'shop'])->where('id', $id)->first();
        // return response()->json($user);
        $userWithReportingUserNames = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone_number' => $user->number,
            'organisation' => $user->organisation,
            'alt_number' => $user->alt_number,
            'pincode' => $user->pincode,
            'state' => $user->state,
            'city' => $user->city,
            'bank_name' => $user->bank_name,
            'bank_number' => $user->bank_number,
            'bank_ifsc' => $user->bank_ifsc,
            'bank_branch' => $user->bank_branch,
            'bank_micr' => $user->bank_micr,
            'two_fector_auth' => $user->two_fector_auth,
            'ip_auth' => $user->ip_auth,
            'ip_addresses' => $user->ip_addresses,
            'status' => $user->status,
            'role' => $user->roles->first(),
            'email_alert' => $user->email_alerts,
            'whatsapp_alert' => $user->whatsapp_alerts,
            'sms_alert' => $user->text_alerts,
            'reporting_user_id' => $user->reportingUser ? $user->reportingUser->id : null,
            'shop' => $user->shop ? $user->shop : null,
            'reporting_user' => $user->reportingUser ? $user->reportingUser->name : 'Admin User',
            'qr_length' => $user->qr_length,
            'authentication' => $user->authentication,
            'agent_disc' => $user->agent_disc,
            'agreement_status' => $user->agreement_status,
            'payment_method' => $user->payment_method,
            'org_type_of_company' => $user->org_type_of_company,
            'org_office_address' => $user->org_office_address,
            'org_signature_type' => $user->org_signature_type,
            'org_name_signatory' => $user->org_name_signatory,
            'org_signatory_image' => $user->org_signatory_image,
            'org_gst_no' => $user->org_gst_no,
            'pan_no' => $user->pan_no,
            'account_holder' => $user->account_holder,
        ];

        $eventDataagent = AgentEvent::where('user_id', $id)->first();
        if ($eventDataagent) {

            $eventIds = json_decode($eventDataagent->event_id, true);
            $ticketIds = json_decode($eventDataagent->ticket_id, true);

            $events = Event::with([
                'tickets' => function ($query) {
                    $query->select('id', 'event_id', 'name');
                }
            ])
                ->whereIn('id', $eventIds)
                ->select('id', 'name')
                ->get();

            // Attach full events detail
            $userWithReportingUserNames['events'] = $events;
            $userWithReportingUserNames['agentTicket'] = $ticketIds ? $ticketIds : [];
        } else {
            $userWithReportingUserNames['events'] = [];
            $userWithReportingUserNames['agentTicket'] = [];
        }

        $userTickets = UserTicket::where('user_id', $id)->get();
        $formattedTickets = [];
        foreach ($userTickets as $userTicket) {
            $ticketIds = $userTicket->ticket_id;

            // If it's an array (because it's cast as array in the model)
            if (is_array($ticketIds)) {
                foreach ($ticketIds as $ticketId) {
                    $ticket = \App\Models\Ticket::select('id', 'name', 'event_id')->find($ticketId);
                    if ($ticket) {
                        $formattedTickets[] = [
                            'value' => $ticket->id,
                            'label' => $ticket->name,
                            'eventId' => $ticket->event_id,
                        ];
                    }
                }
            }
        }

        $userWithReportingUserNames['tickets'] = $formattedTickets;

        return response()->json(['status' => true, 'user' => $userWithReportingUserNames, 'roles' => $roles]);
        // return response()->json(['status' => true, 'user' => $userWithReportingUserNames, 'allUser' => $allUser, 'roles' => $roles]);
    }

    public function update(Request $request, string $id, SmsService $smsService, WhatsappService $whatsappService)
    {
        try {
            // return response()->json($request->all());
            $user = User::findOrFail($id);
            $role = null;
            if ($request->has('name')) {
                $user->name = $request->name;
            }

            if ($request->has('email')) {
                $user->email = $request->email;
            }
            if ($request->has('password')) {
                $user->password = Hash::make($request->password);
            }

            if ($request->has('number')) {
                $user->number = $request->number;
            }
            if ($request->has('address')) {
                $user->address = $request->address;
            }
            if ($request->has('company_name')) {
                $user->company_name = $request->company_name;
            }
            if ($request->has('designation')) {
                $user->designation = $request->designation;
            }

            if ($request->has('reporting_user')) {
                $user->reporting_user = $request->reporting_user;
            }

            if ($request->has('organisation')) {
                $user->organisation = $request->organisation;
            }

            if ($request->has('alt_number')) {
                $user->alt_number = $request->alt_number;
            }

            if ($request->has('pincode')) {
                $user->pincode = $request->pincode;
            }

            if ($request->has('state')) {
                $user->state = $request->state;
            }

            if ($request->has('city')) {
                $user->city = $request->city;
            }

            if ($request->has('bank_name')) {
                $user->bank_name = $request->bank_name;
            }

            if ($request->has('bank_number')) {
                $user->bank_number = $request->bank_number;
            }

            if ($request->has('bank_ifsc')) {
                $user->bank_ifsc = $request->bank_ifsc;
            }

            if ($request->has('bank_branch')) {
                $user->bank_branch = $request->bank_branch;
            }

            if ($request->has('bank_micr')) {
                $user->bank_micr = $request->bank_micr;
            }

            if ($request->has('tax_number')) {
                $user->tax_number = $request->tax_number;
            }

            if ($request->has('qr_length')) {
                $user->qr_length = $request->qr_length;
            }
            if ($request->has('authentication')) {
                $user->authentication = $request->authentication;
            }
            if ($request->has('agent_disc')) {
                $user->agent_disc = $request->agent_disc;
            }

            if ($request->has('status')) {
                $user->status = $request->status;
            }

            if ($request->has('payment_method')) {
                $user->payment_method = $request->payment_method;
            }
            if ($request->has('org_type_of_company')) {
                $user->org_type_of_company = $request->org_type_of_company;
            }
            if ($request->has('org_office_address')) {
                $user->org_office_address = $request->org_office_address;
            }
            if ($request->has('org_name_signatory')) {
                $user->org_name_signatory = $request->org_name_signatory;
            }
            if ($request->has('org_gst_no')) {
                $user->org_gst_no = $request->org_gst_no;
            }
            if ($request->has('pan_no')) {
                $user->pan_no = $request->pan_no;
            }
            if ($request->has('account_holder')) {
                $user->account_holder = $request->account_holder;
            }
            if ($request->has('org_signature_type')) {
                $user->org_signature_type = $request->org_signature_type;
            }

            if ($request->has('agreement_status')) {
                $user->agreement_status = filter_var($request->agreement_status, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
            }

            if ($request->hasFile('photo')) {
                $file = $request->file('photo');
                if ($file->isValid()) {
                    $folder = 'profile/' . str_replace(' ', '_', $user->name);
                    $filePath = $this->storeFile($file, $folder); // store and get full URL
                    $user->photo = $filePath;
                }
            }
            if ($request->hasFile('org_signatory_image')) {
                $file = $request->file('org_signatory_image');
                if ($file->isValid()) {
                    $folder = 'org_sign_image/' . str_replace(' ', '_', $user->org_signatory_image);
                    $filePath = $this->storeFile($file, $folder); // store and get full URL
                    $user->photo = $filePath;
                }
            }
            if ($request->hasFile('doc')) {
                $file = $request->file('doc');
                if ($file->isValid()) {
                    $folder = 'document/' . str_replace(' ', '_', $user->name);
                    $filePath = $this->storeFile($file, $folder); // store and get full URL
                    $user->doc = $filePath;
                }
            }

            if ($request->has('role_id') && $request->role_id) {
                $role = Role::find($request->role_id);

                if ($role) {
                    // Remove all current roles
                    $user->syncRoles([]);

                    // Assign the new role
                    $user->assignRole($role);
                }
            }
            if ($request->role_name == 'Shop Keeper') {
                $this->shopUpdate($request, $id);
            }
            if ($request->role_name == 'Agent' || $request->role_name == 'Sponsor' || $request->role_name == 'Accreditation') {
                $this->agentEventStore($request, $id);
            }
            if ($request->role_name == 'Accreditation') {
                $this->userTicketStore($request, $id);
            }

            // $role = Role::where('id', $request->role_id)->first();
            // if ($role) {
            //     $user->assignRole($role);
            // }
            $user->save();

            //send whatsapp or sms
            if ($request->role_name == 'Organizer') {
                $buttonValue = User::where('id', $request->user_id)->first();
                $filename = $buttonValue ? basename($buttonValue->card_url) : null;

                $whatsappTemplate = WhatsappApi::where('title', 'Acc Ready')->first();
                $whatsappTemplateName = $whatsappTemplate->template_name ?? '';

                if ($user->agreement_status == 1) {
                    $allowedDomain = rtrim(env('ALLOWED_DOMAIN', 'https://getyourticket.in/'), '/');
                    $data = (object) [
                        'name' => $user->name,
                        'number' => $user->number,
                        'templateName' => 'application approved',
                        'whatsappTemplateData' => $whatsappTemplateName,

                        // 'values' => [
                        //     $user->name,
                        //     $user->userOrganisation->event_name,
                        //     'sms for you',
                        //     now()->format('Y-m-d H:i:s'),
                        //     $user->comp->number,
                        // ],

                        'replacements' => [
                            ':C_Name' => $user->name,
                            ':S_Link' => $allowedDomain . '/',
                            // ':S_Link'         => 'https://getyourticket.in/',
                        ]

                    ];

                    $response = $smsService->send($data);
                    // $response = $whatsappService->send($data);
                    $response = $this->sendMail($user->email);

                    // return response()->json($response);
                }
            }


            return response()->json(['status' => true, 'message' => 'User Updated Successfully', 'role' => $role, 'user' => $user], 200);
        } catch (\Exception $e) {

            // Return an error response
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    private function sendMail($email)
    {
        try {

            $template = 'Agreement Approval';
            $emailTemplate = EmailTemplate::where('template_id', $template)->firstOrFail();

            // return response()->json($emailTemplate,200);
            return $this->sendLoginOTPMail($email, $emailTemplate);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch data', 'message' => $e->getMessage()], 500);
        }
    }

    private function sendLoginOTPMail($email, $emailTemplate)
    {
        $body = $emailTemplate->body;
        // $body = str_replace($body);
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

    public function CheckValidUser($id)
    {
        try {
            $user = User::where('id', $id)->with(['balance', 'pricingModel'])->get();
            $user->each(function ($user) {
                $user->latest_balance = $user->balance()->latest()->first();
                $user->pricing = $user->pricingModel()->latest()->first();
                unset($user->balance);
                unset($user->pricingModel);
            });
            $user_balance = $user[0]->latest_balance->total_credits ?? 00.00;
            $marketing_price = $user[0]->pricing->marketing_price;
            if ($user_balance < $marketing_price) {
                $user_balance = $user[0]->latest_balance->total_credits ?? 0;
                return response()->json(['status' => false, 'message' => 'insufficient credits', 'balance' => $user_balance]);
            } else {
                return response()->json(['status' => true, 'balance' => $user_balance]);
            }
        } catch (QueryException $e) {
            $errorMessage = $e->getMessage();
            return response()->json(['status' => false, 'message' => 'Query Exception: ' . $errorMessage]);
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            return response()->json(['status' => false, 'message' => 'An error occurred while processing the request.' . $errorMessage]);
        }
    }

    // public function checkEmail(Request $request)
    // {
    //     $email = $request->input('email');
    //     $mobile = $request->input('number');

    //     $user = User::where('email', $email)
    //         ->orWhere('number', $mobile)
    //         ->first();
    //         // return response()->json([
    //         //     'exists' => $user,
    //         //     // 'message' => 'Both email and mobile are available'
    //         // ]);

    //     if ($user) {
    //         $emailExists = $user->email == $email;
    //         $mobileExists = $user->number == $mobile;

    //         return response()->json([
    //             'exists' => true,
    //             'message' => 'User exists',
    //             'email_exists' => $emailExists,
    //             'mobile_exists' => $mobileExists,
    //             'user' => $user
    //         ]);
    //     } else {
    //         return response()->json([
    //             'exists' => false,
    //             'message' => 'Both email and mobile are available'
    //         ]);
    //     }
    // }

    // public function checkEmail(Request $request)
    // {
    //     $emailExists = false;
    //     $mobileExists = false;
    //     $email = $request->input('email');
    //     $mobile = $request->input('number');

    //     $query = User::query();

    //     if ($mobile) {
    //         $query->orWhere('number', $mobile);
    //     }

    //     if ($email) {
    //         $query->orWhere('email', $email);
    //     }

    //     $user = $query->first();
    //     if ($user) {
    //         if ($email) {
    //             $emailExists = $user->email == $email;
    //         }
    //         if ($mobile) {
    //             $mobileExists = $user->number == $mobile;
    //         }
    //         return response()->json([
    //             'exists' => true,
    //             'message' => 'User exists',
    //             'email_exists' => $emailExists,
    //             'mobile_exists' => $mobileExists,
    //             'user' => $user
    //         ]);
    //     } else {
    //         return response()->json([
    //             'exists' => false,
    //             'message' => 'Both email and mobile are available'
    //         ]);
    //     }
    // }


    public function checkEmail(Request $request)
    {
        $emailExists = false;
        $mobileExists = false;
        $email = $request->input('email');
        $mobile = $request->input('number');

        // Start by checking if both email and mobile are provided
        $query = User::query()->select('id', 'name', 'email', 'number', 'photo', 'doc', 'company_name', 'designation');

        if ($mobile) {
            $query->orWhere('number', $mobile);
        }

        if ($email) {
            $query->orWhere('email', $email);
        }

        $user = $query->first();

        if ($user) {
            // Check if email exists for the matched user
            if ($email && $user->email == $email) {
                $emailExists = true;
            }

            // Check if mobile exists for the matched user
            if ($mobile && $user->number == $mobile) {
                $mobileExists = true;
            }

            // Handle case where email and mobile belong to different users
            $isEmailAndMobileFromDifferentUsers = false;
            if ($email && $mobile) {
                $otherUser = User::where('email', $email)->first();
                $otherMobileUser = User::where('number', $mobile)->first();

                if ($otherUser && $otherMobileUser && $otherUser->id != $otherMobileUser->id) {
                    $isEmailAndMobileFromDifferentUsers = true;
                }
            }

            return response()->json([
                'exists' => true,
                'message' => 'User exists',
                'email_exists' => $emailExists,
                'mobile_exists' => $mobileExists,
                'is_email_and_mobile_different_users' => $isEmailAndMobileFromDifferentUsers,
                'user' => $user
            ]);
        } else {
            return response()->json([
                'exists' => false,
                'message' => 'Both email and mobile are available'
            ]);
        }
    }


    public function checkMobile(Request $request)
    {
        $mobile = $request->input('number');
        $user = User::where('number', $mobile)->first();

        if ($user) {
            if (empty($user->email)) {
                return response()->json(['status' => true, 'message' => 'No email exists for this number.']);
            } else {
                return response()->json(['status' => false, 'message' => 'Email exists for this number.']);
            }
        } else {
            return response()->json(['status' => false, 'message' => 'Number not found in the users table.']);
        }
    }


    public function UpdateUserSecurity(Request $request)
    {
        try {
            $user = User::where('id', $request->id)->firstOrFail();

            $user->ip_auth = $request->ip_auth == true ? 'true' : 'false';
            $user->two_fector_auth = $request->two_fector_auth == true ? 'true' : 'false';
            $user->ip_addresses = $request->ip_addresses;
            $user->save();
            return response()->json(['status' => true, 'message' => 'Security Method Updated Successfully', 'email' => $user->email]);
        } catch (QueryException $e) {
            $errorMessage = $e->getMessage();
            return response()->json(['status' => false, 'message' => 'Query Exception: ' . $errorMessage]);
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            return response()->json(['status' => false, 'message' => 'An error occurred while processing the request.' . $errorMessage]);
        }
    }

    public function checkPassword(Request $request)
    {
        $user = User::find($request->id);

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $password = $request->password;

        if (Hash::check($password, $user->password)) {
            return response()->json(['message' => 'Password is correct, you are verified successfully'], 200);
        } else {
            return response()->json(['error' => 'Oops! Password is incorrect'], 401);
        }
    }

    public function CreditLimit(Request $request)
    {
        $user = User::firstOrFail($request->id);
        $user->low_credit_limit = $request->amount;
        $user->save();
        return response()->json(['message' => 'Limit Updated Successfully'], 200);
    }

    public function updateAlerts(Request $request, string $id)
    {
        try {
            $user = User::findOrFail($id); // Assuming $userId is the ID of the user you want to update

            // Update the user attributes except for the password
            // $user->email_alerts = null;
            // $user->whatsapp_alerts = null;
            // $user->text_alerts = null;
            if ($request->email_alerts) {
                $user->email_alerts = $request->email_alerts;
            } else if ($request->whatsapp_alerts) {
                $user->whatsapp_alerts = $request->whatsapp_alerts;
            } else if ($request->text_alerts) {
                $user->text_alerts = $request->text_alerts;
            }
            $user->save();

            return response()->json(['status' => true, 'message' => 'User Updated Successfully'], 200);
        } catch (\Exception $e) {
            // Log the error for debugging
            \Log::error('Error updating user: ' . $e->getMessage());

            // Return an error response
            return response()->json(['status' => false, 'message' => 'Failed to update user'], 500);
        }
    }

    public function lowBalanceUser($id)
    {
        $users = User::select('id', 'name', 'email', 'whatsapp_number', 'phone_number', 'email_alerts', 'whatsapp_alerts', 'text_alerts')
            ->with(['balance', 'pricingModel', 'ApiKey'])
            ->get();

        // Process each user to attach the latest balance and pricing information
        $filteredUsers = $users->filter(function ($user) {
            $totalCredits = $user->balance()->latest()->first();
            $user->latest_balance = optional($totalCredits)->total_credits;
            $user->pricing = $user->pricingModel()->latest()->first();
            $user->ApiKey = $user->ApiKey()->latest()->first();

            // Check if the latest balance is lower than the price_alert
            if ($user->latest_balance < optional($user->pricing)->price_alert) {
                return true;
            }
            return false;
        });
        // Remove unnecessary attributes
        $result = $filteredUsers->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'whatsapp_number' => $user->whatsapp_number,
                'phone_number' => $user->phone_number,
                'latest_balance' => $user->latest_balance,
                'price_alert' => optional($user->pricing)->price_alert,
                'ApiKey' => $user->ApiKey->key,
                'email_alert' => $user->email_alerts,
                'whatsapp_alert' => $user->whatsapp_alerts,
                'sms_alert' => $user->text_alerts,
            ];
        })->values();

        return response()->json(['user' => $result]);
    }

    // public function export(Request $request)
    // {
    //     $role = $request->input('role');
    //     $status = $request->input('status');
    //     $dates = $request->input('date') ? explode(',', $request->input('date')) : null;

    //     $query = User::query();

    //     if ($role) {
    //         $query->whereHas('roles', function ($query) use ($role) {
    //             $query->where('name', $role);
    //         });
    //     }

    //     if ($request->has('status')) {
    //         $query->where('status', $status);
    //     }

    //     if ($dates) {
    //         if (count($dates) === 1) {
    //             $singleDate = Carbon::parse($dates[0])->toDateString();
    //             $query->whereDate('created_at', $singleDate);
    //         } elseif (count($dates) === 2) {
    //             $startDate = Carbon::parse($dates[0])->startOfDay();
    //             $endDate = Carbon::parse($dates[1])->endOfDay();
    //             $query->whereBetween('created_at', [$startDate, $endDate]);
    //         }
    //     }

    //     $users = $query->get();
    //     // return response()->json(['user' => $users]);
    //     return Excel::download(new UserExport($users), 'users_export.xlsx');
    // }
    public function export(Request $request)
    {
        $loggedInUser = Auth::user();
        $role = $request->input('role');
        $status = $request->input('status');
        $dates = $request->input('date') ? explode(',', $request->input('date')) : null;

        // $query = User::query();
        $query = User::query()
            ->select('name', 'email', 'number', 'organisation')
            ->with([
                'roles' => function ($query) {
                    $query->select('id', 'name');
                }
            ])
            ->where('id', '!=', $loggedInUser->id);
        // Check if user is Admin or not
        if (!$loggedInUser->hasRole('Admin')) {
            // Get all users under the logged-in user
            $userIds = $this->getAllReportingUserIds($loggedInUser->id);
            $query->whereIn('id', $userIds);
        }

        // Apply filters
        if ($role) {
            $query->whereHas('roles', function ($query) use ($role) {
                $query->where('name', $role);
            });
        }

        if ($request->has('status')) {
            $query->where('status', $status);
        }

        if ($dates) {
            if (count($dates) === 1) {
                $singleDate = Carbon::parse($dates[0])->toDateString();
                $query->whereDate('created_at', $singleDate);
            } elseif (count($dates) === 2) {
                $startDate = Carbon::parse($dates[0])->startOfDay();
                $endDate = Carbon::parse($dates[1])->endOfDay();
                $query->whereBetween('created_at', [$startDate, $endDate]);
            }
        }

        $users = collect($query->get())->map(function ($user, $index) {
            return [
                'sr_no' => $index + 1,
                'name' => $user->name,
                'email' => $user->email,
                'number' => $user->number,
                'organisation' => $user->organisation,
            ];
        })->toArray();

        return Excel::download(new UserExport($users), 'users_export.xlsx');
    }

    public function getUsersByRole($role)
    {
        if ($role === 'Organizer') {
            $users = Role::where('name', 'Admin')->first()->users()->whereNull('deleted_at')->get();
        } elseif ($role === 'Agent' || $role === 'POS' || $role === 'Scanner') {
            $users = Role::where('name', 'Organizer')->first()->users()->whereNull('deleted_at')->get();
        } else {
            return response()->json(['error' => 'Invalid role'], 400);
        }

        $formattedUsers = $users->map(function ($user) {
            return [
                'value' => $user->id,
                'label' => $user->name,
                'organisation' => $user?->organisation,
            ];
        });

        return response()->json(['users' => $formattedUsers], 200);
    }

    public function destroy(string $id)
    {
        $userData = User::where('id', $id)->firstOrFail();
        if (!$userData) {
            return response()->json(['status' => false, 'message' => 'user not found'], 404);
        }

        $userData->delete();
        return response()->json(['status' => true, 'message' => 'user deleted successfully'], 200);
    }

    public function getQrLength(string $id)
    {
        $user = User::where('id', $id)->select('qr_length')->first();

        return response()->json(['status' => true, 'tokenLength' => $user->qr_length, 'message' => 'User Qr Length successfully'], 200);
    }

    public function createBulkUsers(Request $request)
    {
        try {
            $bulkInsertData = [];
            $userIds = [];
            $usersData = $request->users;

            foreach ($usersData as $user) {
                $bulkInsertData[] = [
                    'email' => $user['email'],
                    'number' => $user['number'],
                    'password' => Hash::make($user['number']), // Default password
                    'status' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // Bulk insert and retrieve inserted user IDs
            User::insert($bulkInsertData);
            $insertedUsers = User::whereIn('email', array_column($usersData, 'email'))->get();

            // Assign the "User" role in bulk
            foreach ($insertedUsers as $user) {
                $user->assignRole('User');
            }

            return response()->json(['status' => true, 'message' => 'Users Created Successfully'], 201);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to create users', 'error' => $e->getMessage()], 500);
        }
    }

    private function shopStore($request, $userId)
    {
        try {

            $shopData = new Shop();
            $shopData->user_id = $userId;
            $shopData->shop_name = $request->shop_name;
            $shopData->shop_no = $request->shop_no;
            $shopData->gst_no = $request->gst_no;

            $shopData->save();
            return response()->json(['status' => true, 'message' => 'shopData craete successfully', 'shopData' => $shopData,], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to shopData '], 404);
        }
    }


    private function shopUpdate($request, $id)
    {
        try {
            $shopData = Shop::where('user_id', $id)->firstOrFail();

            if ($request->has('shop_name')) {
                $shopData->shop_name = $request->shop_name;
            }

            if ($request->has('shop_no')) {
                $shopData->shop_no = $request->shop_no;
            }

            if ($request->has('gst_no')) {
                $shopData->gst_no = $request->gst_no;
            }

            $shopData->save();
            return $shopData;
            // return response()->json(['status' => true, 'message' => 'shopData update successfully', 'shopData' => $shopData,], 200);
        } catch (\Exception $e) {
            \Log::error('Error updating shop: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to update shop data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function agentEventStore($request, $userId)
    {
        try {

            // $created = AgentEvent::updateOrCreate(
            //     ['user_id' => $userId], // condition to check existing
            //     ['event_id' => json_encode($request->events) ?? []] // data to update or insert
            // );

            $created = AgentEvent::updateOrCreate(
                ['user_id' => $userId], // condition to check existing
                [
                    'event_id' => json_encode($request->events ?? []),
                    'ticket_id' => json_encode($request->tickets ?? []), // save tickets also
                ]
            );

            return response()->json(['status' => true, 'message' => 'AgentEvent craete successfully', 'AgentEvents' => $created,], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to AgentEvent '], 200);
        }
    }

    private function scannerGateStore($request, $userId)
    {
        try {

            $created = ScannerGate::updateOrCreate(
                ['user_id' => $userId], // condition to check existing
                ['gate_id' => json_encode($request->gates) ?? []] // data to update or insert
            );

            return response()->json(['status' => true, 'message' => 'ScannerGate craete successfully', 'ScannerGate' => $created,], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to ScannerGate '], 200);
        }
    }

    private function storeFile($file, $folder, $disk = 'public')
    {
        $filename = uniqid() . '_' . $file->getClientOriginalName();
        $path = $file->storeAs('uploads/' . $folder, $filename, $disk);
        return Storage::disk($disk)->url($path);
    }

    public function eventTicket($eventId)
    {
        try {
            // Get event data
            $event = Event::with('tickets')->find($eventId);

            if (!$event) {
                return response()->json([
                    'status' => false,
                    'message' => 'Event not found'
                ], 404);
            }

            return response()->json([
                'status' => true,
                'event' => [
                    'id' => $event->id,
                    'name' => $event->name,
                ],
                'tickets' => $event->tickets->map(function ($ticket) {
                    return [
                        'id' => $ticket->id,
                        'name' => $ticket->name,
                    ];
                })
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    private function userTicketStore($request, $userId)
    {
        try {
            $tickets = $request->tickets;

            if (!isset($tickets) || !is_array($tickets) || empty($tickets)) {
                return response()->json(['status' => false, 'message' => 'No tickets provided'], 400);
            }

            $grouped = collect($tickets)->groupBy('eventId');

            foreach ($grouped as $eventId => $ticketGroup) {
                $ticketIds = [];
                foreach ($ticketGroup as $ticket) {
                    if (isset($ticket['value'])) {
                        $ticketIds[] = $ticket['value'];
                    }
                }

                if (empty($ticketIds)) {
                    continue;
                }

                $userTicket = UserTicket::updateOrCreate(
                    [
                        'user_id' => $userId,
                        'event_id' => $eventId,
                    ],
                    [
                        'ticket_id' => $ticketIds,
                    ]
                );
            }

            return response()->json(['status' => true, 'message' => 'Tickets stored successfully', 'data' => $userTicket], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to store tickets: ' . $e->getMessage()
            ], 500);
        }
    }

    public function organizerList()
    {
        $organizers = User::whereHas('roles', function ($query) {
            $query->where('name', 'Organizer');
        })->select('id', 'name', 'email', 'number')
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Organizer list fetched successfully.',
            'data' => $organizers
        ], 200);
    }

    public function oneClickLogin(Request $request)
    {
        $request->validate([
            'session_id' => 'required|string',
            'auth_session' => 'required|string',
            'user_id' => 'required|exists:users,id',
        ]);

        try {
            $impersonator = auth()->user();

            if (!$impersonator || !$impersonator->hasPermissionTo('Impersonet')) {
                return response()->json([
                    'status' => false,
                    'error' => 'Unauthorized access. You need Impersonet permission.'
                ], 403);
            }

            $encryptedSessionId = Crypt::encryptString($request->session_id);
            $encryptedAuthSession = Crypt::encryptString($request->auth_session);

            $targetUser = User::findOrFail($request->user_id);

            // Store impersonator info in cache for 60 minutes
            $cacheKey = 'impersonator_' . $encryptedSessionId;
            Cache::put($cacheKey, $impersonator->id, now()->addMinutes(60));

            if (!Cache::has($cacheKey)) {
                return response()->json([
                    'status' => false,
                    'error' => 'Impersonator was not saved in cache.'
                ], 500);
            }

            $token = $targetUser->createToken('one-click-login')->accessToken;

            return response()->json([
                'status' => true,
                'token' => $token,
                'user' => $this->formatUserResponse($targetUser),
                'session_id' => $encryptedSessionId,
                'auth_session' => $encryptedAuthSession,
                'message' => 'Logged in using one-click login.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'error' => 'Something went wrong.',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    public function revertImpersonation(Request $request)
    {
        $cacheKey = 'impersonator_' . $request->session_id;

        if (!Cache::has($cacheKey)) {
            return response()->json([
                'status' => false,
                'error' => 'Session ID not found in cache.',
                'debug' => ['key' => $cacheKey]
            ], 400);
        }

        $impersonatorId = Cache::pull($cacheKey);
        $originalUser = User::find($impersonatorId);

        if (!$originalUser) {
            return response()->json([
                'status' => false,
                'error' => 'Original user not found.'
            ], 404);
        }

        $token = $originalUser->createToken('revert-login')->accessToken;

        return response()->json([
            'status' => true,
            'token' => $token,
            'user' => $this->formatUserResponse($originalUser),
            // 'session_id' => $request->session_id,
            // 'auth_session' => $request->auth_session,
            'message' => 'Reverted back to original user.'
        ]);
    }

    private function formatUserResponse(User $user): array
    {
        $role = $user->roles->first();
        $rolePermissions = $role ? $role->permissions : collect();
        $userPermissions = $user->permissions ?? collect();

        $allPermissions = $rolePermissions->merge($userPermissions)->unique('name');
        $permissionNames = $allPermissions->pluck('name');

        $userArray = $user->toArray();
        $userArray['role'] = $role ? $role->name : null;
        $userArray['permissions'] = $permissionNames;

        return $userArray;
    }

    // public function orgAgreementPdf(Request $request, $id)
    // {
    //     // user table માંથી organiser data લાવો
    //     $user = User::findOrFail($id); // id ન મળ્યું તો 404 throw થશે

    //     // Agreement data તૈયાર કરો
    //     $data = [
    //         // Agreement meta
    //         'signing_date'       => now()->format('d/m/Y'),

    //         // Event Organizer (Schedule 1)
    //         'org_name'           => $user->name ?? 'Organizer Pvt. Ltd.',
    //         'org_type'           => $user->org_type ?? 'Private Limited',
    //         'org_reg_address'    => $user->address ?? 'Registered Address not available',
    //         'org_signatory'      => $user->signatory_name ?? 'Authorized Person',

    //         // Statutory
    //         'gst'                => $user->gst_no ?? 'N/A',
    //         'pan'                => $user->pan_no ?? 'N/A',

    //         // Bank details
    //         'bank_beneficiary'   => $user->bank_beneficiary ?? 'N/A',
    //         'bank_account'       => $user->bank_account ?? 'N/A',
    //         'bank_ifsc'          => $user->bank_ifsc ?? 'N/A',
    //         'bank_name'          => $user->bank_name ?? 'N/A',
    //         'bank_branch'        => $user->bank_branch ?? 'N/A',

    //         // Event basics (જો user સાથે સંબંધિત હોય તો)
    //         'event_name'         => $user->event_name ?? 'Sample Event',
    //         'event_venue'        => $user->event_venue ?? 'Sample Venue',
    //         'event_dates'        => $user->event_dates ?? '01-03 Oct 2025',

    //         // Commercials (Schedule 2)
    //         'commission_percent' => $user->commission_percent ?? 3,
    //         'payment_terms'      => $user->payment_terms ?? 'Within 10 working days post the event',
    //         'term_text'          => $user->term_text ?? '12 months or until completion of payment obligations',

    //         // Notices
    //         'notice_to_name'     => 'Janak Rana',
    //         'notice_to_email'    => 'janak@getyourticket.in',
    //         'notice_to_address'  => '401, BLUE CRYSTAL COM, Vallabh Vidyanagar, Anand, Gujarat 388120',

    //         // Display options
    //         'show_watermark'     => true,
    //     ];

    //     // PDF generate કરો
    //     $pdf = Pdf::loadView('agreements.org-agreement', $data)->setPaper('A4');

    // // Direct download return
    //  return $pdf->stream("Organizer_Agreement_{$user->id}.pdf");
    // return $pdf->download("Organizer_Agreement_{$user->id}.pdf");
    // }

    //    public function viewAgreement($id)
    //     {
    //         $user = User::findOrFail($id);

    //         $data = $this->prepareAgreementData($user);

    //         return view('agreements.org-agreement', $data);
    //     }

    public function downloadAgreement($id)
    {
        $user = User::findOrFail($id);

        $data = $this->prepareAgreementData($user);

        $pdf = Pdf::loadView('agreements.org-agreement', $data)
            ->setPaper('a4');

        return $pdf->download("Organizer_Agreement_{$user->id}.pdf");
    }

    private function prepareAgreementData($user)
    {
        return [
            'signing_date' => now()->format('d/m/Y'),
            'org_name' => $user->name ?? 'Organizer Pvt. Ltd.',
            'org_type' => $user->orgType?->title ?? 'Private Limited',
            'org_reg_address' => $user->org_office_address ?? 'Not Available',
            'org_signatory' => $user->org_name_signatory ?? 'Authorized Person',
            'gst' => $user->org_gst_no ?? 'N/A',
            'pan' => $user->pan_no ?? 'N/A',
            'bank_beneficiary' => $user->bank_beneficiary ?? 'N/A',
            'bank_account' => $user->bank_account ?? 'N/A',
            'bank_ifsc' => $user->bank_ifsc ?? 'N/A',
            'bank_name' => $user->bank_name ?? 'N/A',
            'bank_branch' => $user->bank_branch ?? 'N/A',
            'event_name' => $user->event_name ?? 'Sample Event',
            'event_venue' => $user->event_venue ?? 'Sample Venue',
            'event_dates' => $user->event_dates ?? '01-03 Oct 2025',
            'commission_percent' => $user->commission_percent ?? 3,
            'payment_terms' => $user->payment_terms ?? 'Within 10 days after event',
            'term_text' => $user->term_text ?? '12 months',
            'notice_to_name' => 'Janak Rana',
            'notice_to_email' => 'janak@getyourticket.in',
            'notice_to_address' => '401, BLUE CRYSTAL COM, Vallabh Vidyanagar, Anand, Gujarat 388120',
            'show_watermark' => true,
        ];
    }
}
