<?php

namespace App\Http\Controllers;

use App\Models\Catrgoty_has_Field;
use App\Models\CorporateUser;
use App\Models\CustomField;
use App\Models\User;
use Illuminate\Http\Request;

class CorporateUserController extends Controller
{
    //CorporateUserStore
    public function corporateUserStore(Request $request)
    {
        try {
            $attendees = $request->input('corporateUser');
            $userName = $request->user_name;
            $eventName = $request->event_name;
            $isAgentBooking = $request->isAgentBooking;

            $savedAttendees = [];

            foreach ($attendees as $index => $attendeeData) {
                $id = $attendeeData['id'] ?? null;

                $data = [];
                foreach ($attendeeData as $key => $value) {
                    if ($key !== 'id') {
                        $data[$key] = $value;
                    }
                }

                if ($isAgentBooking) {
                    $data['agent_id'] = $request->user_id;
                    $data['user_id'] = null;
                } else {
                    $data['user_id'] = $request->user_id;
                    $data['agent_id'] = null;
                }


                $user = User::updateOrCreate(
                    ['number' => $attendeeData['Mo']]  ?? null, // unique constraint on email
                    [
                        'name' => $attendeeData['Name'] ?? 'Unknown',
                        'email' => $attendeeData['Email'] ?? null,
                        'number' => $attendeeData['Mo'] ?? null,
                        'password' => bcrypt('123'),
                        'status' => true
                    ]
                );

                // Attach `user_id` in corporate_users table
                $data['user_id'] = $user->id;

                $attendee = null;

                if ($id) {
                    $attendee = CorporateUser::updateOrCreate(['id' => $id], $data);
                } else {
                    $existingAttendee = CorporateUser::where($data)->first();
                    if ($existingAttendee) {
                        $attendee = $existingAttendee;
                        $attendee->update($data);
                    } else {
                        $attendee = CorporateUser::create($data);
                        $attendee->token = $this->generateHexadecimalCode();
                    }
                }

                if ($request->hasFile("corporateUser.$index")) {
                    foreach ($request->file("corporateUser.$index") as $fileKey => $file) {
                        if ($file->isValid()) {
                            // $fileName = $attendee->id . '.jpg'; // force JPG (or use extension if needed)
                            $fileName = $attendee->id . '_' . str_replace(' ', '_', strtolower($attendee->Name)) . '.jpg';


                            $folder = str_replace(' ', '_', $eventName) . '/corporateUser/' . $fileKey;

                            $filePath = $this->storeFile($file, $folder, $fileName); // pass custom filename

                            $attendee->$fileKey = $filePath;
                        } else {
                            return response()->json([
                                'status' => false,
                                'message' => "Invalid file upload for attendee at index $index.",
                            ], 400);
                        }
                    }
                }



                $attendee->save();
                $savedAttendees[] = $attendee;
            }

            return response()->json([
                'status' => true,
                'message' => 'corporateUser data stored or updated successfully',
                'data' => $savedAttendees,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to store or update attendee data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    //update
    public function corporateUserUpdate(Request $request, $id)
    {

        try {
            $attendee = CorporateUser::findOrFail($id);
            $userName = $request->user_name;
            $eventName = $request->event_name;
            $attendeesData = $request->input('corporateUser');
            $isAgentBooking = $request->isAgentBooking;

            foreach ($attendeesData as $key => $value) {
                if ($request->hasFile("corporateUser.$key")) {
                    $files = $request->file("corporateUser.$key");

                    if ($files->isValid()) {
                        // $filePath = $this->storeFile($files, $eventName . '/attendees_photos/' . $userName);
                        // $attendee->$key = $filePath;
                        // $fileName = $attendee->id . '.jpg'; // You can use extension if needed
                        $fileName = $attendee->id . '_' . str_replace(' ', '_', strtolower($attendee->Name)) . '.jpg';

                        $folder = str_replace(' ', '_', $eventName) . '/corporateUser/' . $key;

                        $filePath = $this->storeFile($files, $folder, $fileName);
                        $attendee->$key = $filePath;
                    } else {
                        return response()->json([
                            'status' => false,
                            'message' => "Invalid file upload for $key.",
                        ], 400);
                    }
                } else {
                    $attendee->$key = $value;
                }
            }



            if ($isAgentBooking) {
                $data['agent_id'] = $request->user_id;
                $data['user_id'] = null;
            } else {
                $data['user_id'] = $request->user_id;
                $data['agent_id'] = null;
            }


            if (isset($updatedFields['Email'])) {
                $user = User::updateOrCreate(
                    ['email' => $updatedFields['Email']],
                    [
                        'name' => $updatedFields['Name'] ?? $attendee->Name ?? 'Unknown',
                        'email' => $updatedFields['Email'],
                        'number' => $updatedFields['Mo'] ?? $attendee->Mo ?? null,
                        'password' => bcrypt('123') // only if new user created
                    ]
                );

                $attendee->user_id = $user->id;
            }

            $attendee->save();

            return response()->json([
                'status' => true,
                'message' => 'corporateUser data updated successfully',
                'data' => $attendee,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to update corporateUser data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function storeFile($file, $folder, $fileName = null, $disk = 'public')
    {
        $fileName = $fileName ?? uniqid() . '_' . $file->getClientOriginalName();
        $folderPath = public_path('uploads/' . $folder);

        if (!file_exists($folderPath)) {
            mkdir($folderPath, 0755, true);
        }

        $file->move($folderPath, $fileName);

        return url('uploads/' . $folder . '/' . $fileName);
    }


    private function generateHexadecimalCode($length = 8)
    {
        $characters = '0123456789ABCDEF'; // Hexadecimal characters
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    //user wise attendy
    public function corporateUserAttendy(Request $request, $userId, $category_id)
    {
        try {
            $categoryId = $category_id;
            $isAgent = $request->isAgent;

            $categoryData = Catrgoty_has_Field::where('category_id', $categoryId)->first();
            $customFieldIds = explode(',', $categoryData->custom_fields_id);

            $customFields = CustomField::whereIn('id', $customFieldIds)->pluck('field_name')->toArray();

            $query = CorporateUser::query();

            // if ($isAgent == 'true') {
                $query->where('agent_id', $userId);
            // } else {
            //     $query->where('user_id', $userId);
            // }

            $attendees = $query->get()->map(function ($attendee) use ($customFields) {
                return array_merge(
                    ['id' => $attendee->id],
                    $attendee->only($customFields)
                );
            });

            return response()->json([
                'status' => true,
                'message' => 'User CorporateUser data  successfully',
                'attendees' => $attendees,

            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to User CorporateUser data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
