<?php

namespace App\Http\Controllers;

use App\Exports\UserExport;
use App\Models\User;
use Auth;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{

    public function index()
    {
        $loggedInUser = Auth::user();
        $date = Carbon::now()->format('Y-m-d');

        //return response()->json(['status' => true, 'users' => $loggedInUser]);
        if ($loggedInUser->hasRole('Admin')) {
            // If the logged-in user is an admin, return all users
            $users = User::with(['roles', 'reportingUser'])->latest()->get();
        } else {
            // If the logged-in user is not an admin, return only the users that report to them
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

            ];
        });
        $organizers = User::role('Organizer')->get();
        $formattedUsers = $users->map(function ($user) {
            return [
                'value' => $user->id,
                'label' => $user->name,
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

    // public function index()
    // {
    //     $loggedInUser = Auth::user();
    //     $todayDate = Carbon::now()->format('Y-m-d');

    //     if ($loggedInUser->hasRole('Admin')) {
    //         $users = User::with(['roles', 'reportingUser'])->whereDate('created_at', $todayDate)->latest()->get();
    //     } else {
    //         // If the logged-in user is not an admin, return only the users that report to them
    //         $users = User::with(['roles', 'reportingUser'])
    //             ->where('reporting_user', $loggedInUser->id)
    //             ->whereDate('created_at', $todayDate)
    //             ->latest()->get();
    //     }
    //     $allUsers = $users->map(function ($user) {
    //         return [
    //             'id' => $user->id,
    //             'name' => $user->name,
    //             'contact' => $user->number,
    //             'email' => $user->email,
    //             'role_name' => $user->roles->pluck('name')->first(),
    //             'status' => $user->staus,
    //             'reporting_user' => $user->reportingUser ? $user->reportingUser->name : null,
    //             'organisation' => $user->organisation ? $user->organisation : null,
    //             'created_at' => $user->created_at,

    //         ];
    //     });
    //     $organizers = User::role('Organizer')->whereDate('created_at', $todayDate)->get();
    //     $formattedUsers = $users->map(function ($user) {
    //         return [
    //             'value' => $user->id,
    //             'label' => $user->name,
    //         ];
    //     });
    //     $org = $organizers->map(function ($user) {
    //         return [
    //             'value' => $user->id,
    //             'label' => $user->name,
    //         ];
    //     });

    //     return response()->json(['status' => true, 'users' => $formattedUsers, 'allData' => $allUsers, 'organizers' => $org]);
    // }

    public function create(Request $request)
    {
        try {
            $request->validate([
                'number' => [
                    'required',
                    'string',
                    Rule::unique('users', 'number')->whereNull('deleted_at'),
                ],
            ], [
                'number.unique' => 'The mobile number has already been taken.',
            ]);

            $request->validate([
                'email' => 'required|email|unique:users,email',
            ], [
                'email.unique' => 'The email has already been taken.',
            ]);
            // $request->validate([
            //     'email' => [
            //         'required',
            //         'email',
            //         Rule::unique('users', 'email')->whereNull('deleted_at'),
            //     ],
            // ], [
            //     'email.unique' => 'The email has already been taken.',
            // ]);
            // $request->validate([
            //     'number' => 'required|string|unique:users,number',
            // ], [
            //     'number.unique' => 'The mobile number has already been taken.',
            // ]);

            // // Validate the email next
            // $request->validate([
            //     'email' => 'required|email|unique:users,email',
            // ], [
            //     'email.unique' => 'The email has already been taken.',
            // ]);

            // Additional validation for other fields
            $validatedData = $request->validate([
                'name' => 'required|string|max:255',
            ]);
            $user = new User();
            $user->name = $request->name;
            $user->email = $request->email;
            $user->number = $request->number;
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
            $user->status = true;
            $user->password = Hash::make($request->password);
            $user->save();
            $this->updateUserRole($request, $user);
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

        $user = User::with('reportingUser')->where('id', $id)->first();

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
            'reporting_user' => $user->reportingUser ? $user->reportingUser->name : 'Admin User',
            'qr_length' => $user->qr_length,
        ];
        return response()->json(['status' => true, 'user' => $userWithReportingUserNames, 'allUser' => $allUser, 'roles' => $roles]);
    }

    public function update(Request $request, string $id)
    {
        try {
            $user = User::findOrFail($id);
            if ($request->has('name')) {
                $user->name = $request->name;
            }

            if ($request->has('email')) {
                $user->email = $request->email;
            }

            if ($request->has('number')) {
                $user->number = $request->number;
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

            if ($request->has('status')) {
                $user->status = $request->status;
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
            // $role = Role::where('id', $request->role_id)->first();
            // if ($role) {
            //     $user->assignRole($role);
            // }
            $user->save();

            return response()->json(['status' => true, 'message' => 'User Updated Successfully', 'role' => $role, 'user' => $user], 200);
        } catch (\Exception $e) {

            // Return an error response
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
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
        $query = User::query();

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

    public function export(Request $request)
    {
        $role = $request->input('role');
        $status = $request->input('status');
        $dates = $request->input('date') ? explode(',', $request->input('date')) : null;

        $query = User::query();

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

        $users = $query->get();
        // return response()->json(['user' => $users]);
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
}
