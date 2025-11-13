<?php

namespace App\Http\Controllers;

use App\Exports\AttndyBookingExport;
use App\Models\AccreditationBooking;
use App\Models\Agent;
use App\Models\Attndy;
use App\Models\Booking;
use App\Models\Category;
use App\Models\Catrgoty_has_Field;
use App\Models\CorporateBooking;
use App\Models\CorporateUser;
use App\Models\CustomField;
use App\Models\Event;
use App\Models\PosBooking;
use App\Models\Ticket;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use ZipArchive;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;

class AttndyController extends Controller
{

    //list
    public function fieldsList()
    {
        $customFields = CustomField::orderBy('sr_no')->get();

        return response()->json([
            'status' => true,
            'message' => 'CustomFields fetched successfully',
            'customFields' => $customFields
        ], 200);
    }

    //list name
    public function fieldsListName()
    {
        $customFields = CustomField::select('id', 'sr_no', 'field_name', 'field_type')
            ->orderBy('sr_no')
            ->get();
        return response()->json([
            'status' => true,
            'message' => 'CustomFields fetched successfully',
            'customFields' => $customFields
        ], 200);
    }

    //store
    public function store(Request $request)
    {
        try {

            $request->validate([
                'field_name' => 'required|string|max:255|unique:custom_fields,field_name',
                'field_type' => [
                    'required',
                    'string',
                    'in:text,textarea,select,radio,checkbox,switch,boolean,number,range,date,file,color,dropdown,email', // Allowed types
                ],
                'field_required' => 'required|boolean',
                'fixed' => 'required|boolean',
            ]);

            $maxSrNo = CustomField::max('sr_no');
            $srNo = $maxSrNo ? $maxSrNo + 1 : 1;

            $customField = new CustomField();
            $customField->sr_no = $srNo;
            $customField->field_name = $request->input('field_slug');
            $customField->lable = $request->input('field_name');
            $customField->field_type = $request->input('field_type');
            $customField->field_required = $request->input('field_required');
            $customField->fixed = $request->input('fixed');
            $customField->field_value = $request->input('field_type');

            if (in_array($customField->field_type, ['radio', 'checkbox', 'dropdown', 'select'])) {
                $customField->field_options = json_encode($request->input('field_options'));
            }

            $customField->save();

            $this->addFieldToTable($customField, 'attndies');
            $this->addFieldToTable($customField, 'corporate_users');

            $attndiesColumns = DB::getSchemaBuilder()->getColumnListing('attndies');
            $corporateColumns = DB::getSchemaBuilder()->getColumnListing('corporate_users');


            return response()->json([
                'status' => true,
                'message' => 'CustomField Added Successfully',
                'customField' => $customField,
                'attndies_columns' => $attndiesColumns,
                'corporate_columns' => $corporateColumns
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' =>  $e->getMessage()
            ], 500);
        }
    }

    public function addFieldToTable(CustomField $customField, $tableName)
    {
        $fieldName = $customField->field_name;
        $fieldType = $customField->field_type;

        // Only add column if it doesn't already exist
        if (!Schema::hasColumn($tableName, $fieldName)) {
            Schema::table($tableName, function ($table) use ($fieldName, $fieldType) {
                switch ($fieldType) {
                    case 'text':
                    case 'textarea':
                        $table->text($fieldName)->nullable();
                        break;

                    case 'select':
                    case 'radio':
                    case 'dropdown':
                    case 'email':
                    case 'file':
                    case 'color':
                        $table->string($fieldName)->nullable();
                        break;

                    case 'checkbox':
                        $table->json($fieldName)->nullable();
                        break;

                    case 'switch':
                    case 'boolean':
                        $table->boolean($fieldName)->default(false);
                        break;

                    case 'number':
                    case 'range':
                        $table->bigInteger($fieldName)->nullable();
                        break;

                    case 'date':
                        $table->date($fieldName)->nullable();
                        break;

                    default:
                        throw new \Exception("Unsupported field type: {$fieldType}");
                }
            });
        }
    }


    public function addFieldToAttndies(CustomField $customField)
    {
        $fieldName = $customField->field_name;
        $fieldType = $customField->field_type;
        $fieldOptions = $customField->field_options ?? null;

        Schema::table('attndies', function ($table) use ($fieldName, $fieldType, $fieldOptions) {
            switch ($fieldType) {
                case 'text':
                case 'textarea':
                    $table->text($fieldName)->nullable();
                    break;

                case 'select':
                case 'radio':
                case 'dropdown':
                    $table->string($fieldName)->nullable();
                    break;

                case 'checkbox':
                    $table->json($fieldName)->nullable();
                    break;

                case 'switch':
                case 'boolean':
                    $table->boolean($fieldName)->default(false);
                    break;

                case 'number':
                case 'range':
                    $table->bigint($fieldName)->nullable();
                    break;

                case 'date':
                    $table->date($fieldName)->nullable();
                    break;

                case 'file':
                    $table->string($fieldName)->nullable();
                    break;

                case 'color':
                    $table->string($fieldName, 7)->nullable();
                    break;

                case 'email':
                    $table->string($fieldName)->nullable();
                    break;

                default:
                    throw new \Exception("Field type '{$fieldType}' is not supported.");
            }
        });
    }

    // public function update(Request $request, $id)
    // {
    //     try {
    //         // $request->validate([
    //         //     'field_name' => [
    //         //         'required',
    //         //         'string',
    //         //         'max:255',
    //         //         Rule::unique('custom_fields', 'field_name')->ignore($id),
    //         //     ],
    //         //     'field_type' => [
    //         //         'required',
    //         //         'string',
    //         //         'in:text,textarea,select,radio,checkbox,switch,boolean,number,range,date,file,color,dropdown,email',
    //         //     ],
    //         //     'field_required' => 'required|boolean',
    //         //     'fixed' => 'required|boolean',

    //         // ]);

    //         $customField = CustomField::findOrFail($id);
    //         $oldFieldName = $customField->field_name;
    //         $oldFieldType = $customField->field_type;

    //         $customField->field_name = $request->input('field_slug', $oldFieldName);
    //         $customField->lable = $request->input('field_name', $oldFieldName);
    //         $customField->field_type = $request->input('field_type', $oldFieldType);
    //         $customField->field_value = $request->input('field_value', $customField->field_value);
    //         $customField->field_required = $request->input('field_required', $customField->field_required);
    //         $customField->fixed = $request->input('fixed', $customField->fixed);

    //         if (in_array($customField->field_type, ['radio', 'checkbox', 'dropdown', 'select'])) {
    //             $customField->field_options = json_encode($request->input('field_options'));
    //         } else {
    //             $customField->field_options = null;
    //         }

    //         if ($oldFieldName !== $customField->field_name || $oldFieldType !== $customField->field_type) {
    //             Schema::table('attndies', function ($table) use ($oldFieldName, $customField) {
    //                 if (Schema::hasColumn('attndies', $oldFieldName)) {
    //                     $table->dropColumn($oldFieldName);
    //                 }

    //                 switch ($customField->field_type) {
    //                     case 'string':
    //                         $table->string($customField->field_name)->nullable();
    //                         break;
    //                     case 'integer':
    //                         $table->integer($customField->field_name)->nullable();
    //                         break;
    //                     case 'boolean':
    //                         $table->boolean($customField->field_name)->default(false);
    //                         break;
    //                     case 'radio':
    //                     case 'dropdown':
    //                         $table->string($customField->field_name)->nullable();
    //                         break;
    //                     case 'checkbox':
    //                         $table->json($customField->field_name)->nullable();
    //                     case 'email':
    //                         $table->json($customField->field_name)->nullable();
    //                         break;
    //                 }
    //             });
    //         }

    //         $customField->save();

    //         $columns = DB::getSchemaBuilder()->getColumnListing('attndies');

    //         return response()->json([
    //             'status' => true,
    //             'message' => 'CustomField Updated Successfully',
    //             'customField' => $customField,
    //             'attndies_columns' => $columns
    //         ], 200);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => $e->getMessage()
    //         ], 500);
    //     }
    // }

    public function update(Request $request, $id)
    {
        try {
            $customField = CustomField::findOrFail($id);

            $oldFieldName = $customField->field_name;
            $oldFieldType = $customField->field_type;

            // Update values from request
            $customField->field_name = $request->input('field_slug', $oldFieldName);
            $customField->lable = $request->input('field_name', $customField->lable);
            $customField->field_type = $request->input('field_type', $oldFieldType);
            $customField->field_value = $request->input('field_value', $customField->field_value);
            $customField->field_required = $request->input('field_required', $customField->field_required);
            $customField->fixed = $request->input('fixed', $customField->fixed);

            if (in_array($customField->field_type, ['radio', 'checkbox', 'dropdown', 'select'])) {
                $customField->field_options = json_encode($request->input('field_options'));
            } else {
                $customField->field_options = null;
            }

            // If field name or type changed, update in both tables
            if ($oldFieldName !== $customField->field_name || $oldFieldType !== $customField->field_type) {
                $this->updateFieldInTable('attndies', $oldFieldName, $customField);
                $this->updateFieldInTable('corporate_users', $oldFieldName, $customField);
            }

            $customField->save();

            $attndiesColumns = DB::getSchemaBuilder()->getColumnListing('attndies');
            $corporateColumns = DB::getSchemaBuilder()->getColumnListing('corporate_users');

            return response()->json([
                'status' => true,
                'message' => 'CustomField updated in both tables successfully',
                'customField' => $customField,
                'attndies_columns' => $attndiesColumns,
                'corporate_columns' => $corporateColumns
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    private function updateFieldInTable($tableName, $oldFieldName, CustomField $customField)
    {
        if (Schema::hasColumn($tableName, $oldFieldName)) {
            Schema::table($tableName, function (Blueprint $table) use ($oldFieldName) {
                $table->dropColumn($oldFieldName);
            });
        }

        Schema::table($tableName, function (Blueprint $table) use ($customField) {
            $name = $customField->field_name;
            $type = $customField->field_type;

            switch ($type) {
                case 'text':
                case 'textarea':
                    $table->text($name)->nullable();
                    break;

                case 'select':
                case 'radio':
                case 'dropdown':
                case 'file':
                case 'color':
                case 'email':
                    $table->string($name)->nullable();
                    break;

                case 'checkbox':
                    $table->json($name)->nullable();
                    break;

                case 'switch':
                case 'boolean':
                    $table->boolean($name)->default(false);
                    break;

                case 'number':
                case 'range':
                    $table->bigInteger($name)->nullable();
                    break;

                case 'date':
                    $table->date($name)->nullable();
                    break;

                default:
                    throw new \Exception("Unsupported field type: {$type}");
            }
        });
    }


    //delelte
    // public function destroy($id)
    // {
    //     try {
    //         $customField = CustomField::findOrFail($id);
    //         $fieldName = $customField->field_name;

    //         Schema::table('attndies', function ($table) use ($fieldName) {
    //             $table->dropColumn($fieldName);
    //         });

    //         $customField->delete();

    //         $columns = DB::getSchemaBuilder()->getColumnListing('attndies');

    //         return response()->json([
    //             'status' => true,
    //             'message' => 'CustomField Deleted Successfully',
    //             'attndies_columns' => $columns
    //         ], 200);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'status' => false,
    //             'message' =>  $e->getMessage()
    //         ], 500);
    //     }
    // }

    public function destroy($id)
    {
        try {
            $customField = CustomField::findOrFail($id);
            $fieldName = $customField->field_name;

            // Drop column from both tables if it exists
            $this->dropFieldFromTable('attndies', $fieldName);
            $this->dropFieldFromTable('corporate_users', $fieldName);

            // Delete the custom field record
            $customField->delete();

            // Get updated columns from both tables
            $attndiesColumns = DB::getSchemaBuilder()->getColumnListing('attndies');
            $corporateColumns = DB::getSchemaBuilder()->getColumnListing('corporate_users');

            return response()->json([
                'status' => true,
                'message' => 'CustomField deleted from both tables successfully',
                'attndies_columns' => $attndiesColumns,
                'corporate_columns' => $corporateColumns
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }


    private function dropFieldFromTable($tableName, $fieldName)
    {
        if (Schema::hasColumn($tableName, $fieldName)) {
            Schema::table($tableName, function ($table) use ($fieldName) {
                $table->dropColumn($fieldName);
            });
        }
    }


    //catrgoty-fields-store
    public function catrgotyFields(Request $request)
    {
        try {
            $customFieldsIds = $request->input('custom_fields_id');
            $customFieldsIdsString = implode(',', $customFieldsIds);

            $categoryHasField = Catrgoty_has_Field::updateOrCreate(
                ['category_id' => $request->input('category_id')],
                ['custom_fields_id' => $customFieldsIdsString]
            );

            return response()->json(['status' => true, 'message' => 'Record added or updated successfully', 'data' => $categoryHasField], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to add or update record: ' . $e->getMessage()], 500);
        }
    }

    //category-fields-update
    public function catrgotyFieldsUpdate(Request $request, $id)
    {
        try {

            $categoryHasField = Catrgoty_has_Field::findOrFail($id);

            $categoryHasField->category_id = $request->input('category_id');

            if ($request->has('custom_fields_id')) {
                $customFieldsIds = $request->input('custom_fields_id');
                $categoryHasField->custom_fields_id = implode(',', $customFieldsIds);
            }

            $categoryHasField->save();

            return response()->json([
                'status' => true,
                'message' => 'Record updated successfully',
                'data' => $categoryHasField
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to update record: ' . $e->getMessage()
            ], 500);
        }
    }

    // Delete a record
    public function catrgotyFieldsdestroy($id)
    {
        $categoryHasField = Catrgoty_has_Field::findOrFail($id);
        $categoryHasField->delete();

        return response()->json(['status' => true, 'message' => 'Record deleted successfully'], 200);
    }

    //list
    public function catrgotyFieldsList()
    {
        try {
            $records = Catrgoty_has_Field::with('category')->get();

            $records = $records->map(function ($record) {
                $record->custom_fields = $record->customFields();
                return $record;
            });

            return response()->json(['status' => true, 'data' => $records], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to retrieve records: ' . $e->getMessage()], 500);
        }
    }

    public function catrgotyFieldsListId($title)
    {
        try {
            $category = Category::where('title', $title)->first();

            if (!$category) {
                return response()->json(['status' => 'false', 'message' => 'Category not found'], 404);
            }

            $records = Catrgoty_has_Field::with('category')->where('category_id', $category->id)->get();

            $records = $records->map(function ($record) {
                $record->custom_fields = $record->customFields();
                return $record;
            });

            return response()->json([
                'status' => true,
                'data' => $records
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve records: ' . $e->getMessage()
            ], 500);
        }
    }

    //CustomField reaarange
    public function rearrangeCustomField(Request $request)
    {
        try {
            $srNoCount = [];

            foreach ($request->data as $item) {
                if (isset($srNoCount[$item['sr_no']])) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Duplicate sr_no values detected: ' . $item['sr_no'],
                    ], 400);
                }
                $srNoCount[$item['sr_no']] = true;
            }

            foreach ($request->data as $item) {
                $CustomField = CustomField::findOrFail($item['id']);
                $CustomField->sr_no = $item['sr_no'];
                $CustomField->save();
            }

            $updatedCustomField = CustomField::orderBy('sr_no')->get();

            return response()->json([
                'status' => true,
                'message' => 'CustomField rearranged successfully',
                'data' => $updatedCustomField,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to rearrange CustomField',
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    //attndyStore
    public function attndyStore(Request $request)
    {
        try {
            $attendees = $request->input('attendees');
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

                $attendee = null;

                if ($id) {
                    $attendee = Attndy::updateOrCreate(['id' => $id], $data);
                } else {
                    $existingAttendee = Attndy::where($data)->first();
                    if ($existingAttendee) {
                        $attendee = $existingAttendee;
                        $attendee->update($data);
                    } else {
                        $attendee = Attndy::create($data);
                        $attendee->token = $this->generateHexadecimalCode();
                    }
                }

                if ($request->hasFile("attendees.$index")) {
                    foreach ($request->file("attendees.$index") as $fileKey => $file) {
                        if ($file->isValid()) {
                            // $fileName = $attendee->id . '.jpg'; // force JPG (or use extension if needed)
                            $fileName = $attendee->id . '_' . str_replace(' ', '_', strtolower($attendee->Name)) . '.jpg';


                            $folder = str_replace(' ', '_', $eventName) . '/attendees/' . $fileKey;

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
                'message' => 'Attendee data stored or updated successfully',
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
    public function attndyUpdate(Request $request, $id)
    {

        try {
            $attendee = Attndy::findOrFail($id);
            $userName = $request->user_name;
            $eventName = $request->event_name;
            $attendeesData = $request->input('attendees');
            $isAgentBooking = $request->isAgentBooking;

            foreach ($attendeesData as $key => $value) {
                if ($request->hasFile("attendees.$key")) {
                    $files = $request->file("attendees.$key");

                    if ($files->isValid()) {
                        // $filePath = $this->storeFile($files, $eventName . '/attendees_photos/' . $userName);
                        // $attendee->$key = $filePath;
                        // $fileName = $attendee->id . '.jpg'; // You can use extension if needed
                        $fileName = $attendee->id . '_' . str_replace(' ', '_', strtolower($attendee->Name)) . '.jpg';

                        $folder = str_replace(' ', '_', $eventName) . '/attendees/' . $key;

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


            $attendee->save();

            return response()->json([
                'status' => true,
                'message' => 'Attendee data updated successfully',
                'data' => $attendee,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to update attendee data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // private function storeFile($file, $folder, $disk = 'public')
    // {
    //     $filename = uniqid() . '_' . $file->getClientOriginalName();
    //     $path = $file->storeAs('uploads/' . $folder, $filename, $disk);
    //     return Storage::disk($disk)->url($path);
    // }

    //user wise attendy
    public function userAttendy(Request $request, $userId, $category_id)
    {
        try {
            $categoryId = $category_id;
            $isAgent = $request->isAgent;

            $categoryData = Catrgoty_has_Field::where('category_id', $categoryId)->first();
            $customFieldIds = explode(',', $categoryData->custom_fields_id);

            $customFields = CustomField::whereIn('id', $customFieldIds)->pluck('field_name')->toArray();

            $query = Attndy::query();

            if ($isAgent == 'true') {
                $query->where('agent_id', $userId);
            } else {
                $query->where('user_id', $userId);
            }

            $attendees = $query->get()->map(function ($attendee) use ($customFields) {
                return array_merge(
                    ['id' => $attendee->id],
                    $attendee->only($customFields)
                );
            });

            return response()->json([
                'status' => true,
                'message' => 'User Attendee data  successfully',
                'attendees' => $attendees,

            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to User attendee data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function attendyList(Request $request, $userId, $event_id)
    {
        try {
            $loggedInUser = User::find($userId);
            if (!$loggedInUser) {
                return response()->json(['error' => 'User not found'], 404);
            }

            $isAdmin = $loggedInUser->hasRole('Admin');
            $isOrganizer = $loggedInUser->hasRole('Organizer');
            $isUser = $loggedInUser->hasRole('User');

            $attendeeIds = collect();
            $corporateIds = collect();

            if ($isAdmin) {
                $bookingAttendeeIds = Booking::whereHas('ticket', function ($query) use ($event_id) {
                    $query->where('event_id', $event_id);
                })->pluck('attendee_id');

                $agentBookingAttendeeIds = Agent::whereHas('ticket', function ($query) use ($event_id) {
                    $query->where('event_id', $event_id);
                })->pluck('attendee_id');

                $corporateBookingAttendeeIds = CorporateBooking::whereHas('ticket', function ($query) use ($event_id) {
                    $query->where('event_id', $event_id);
                })->pluck('attendee_id');

                $attendeeIds = $bookingAttendeeIds->merge($agentBookingAttendeeIds)->unique();
                $corporateIds = $corporateBookingAttendeeIds->unique();
            } elseif ($isOrganizer) {
                $event = Event::where('id', $event_id)->where('user_id', $userId)->first();
                if (!$event) {
                    return response()->json(['error' => 'Event not found or unauthorized'], 403);
                }

                $ticketIds = $event->tickets->pluck('id');

                $bookingAttendeeIds = Booking::whereIn('ticket_id', $ticketIds)->pluck('attendee_id');
                $agentBookingAttendeeIds = Agent::whereIn('ticket_id', $ticketIds)->pluck('attendee_id');
                $corporateBookingAttendeeIds = CorporateBooking::whereIn('ticket_id', $ticketIds)->pluck('attendee_id');

                $attendeeIds = $bookingAttendeeIds->merge($agentBookingAttendeeIds)->unique();
                $corporateIds = $corporateBookingAttendeeIds->unique();
            } elseif ($isUser) {
                $attendeeIds = Attndy::where('user_id', $userId)->pluck('id');
                $corporateIds = CorporateUser::where('agent_id', $userId)->pluck('id');
            } else {
                return response()->json(['error' => 'Role not recognized'], 403);
            }

            // Fetch corporate users first
            $corporates = CorporateUser::with('userData')
                ->whereIn('id', $corporateIds)
                ->get()
                ->map(function ($item) {
                    $item->type = 'corporate';
                    return $item;
                });

            // Then fetch attendees
            $attendees = Attndy::with('userData')
                ->whereIn('id', $attendeeIds)
                ->get()
                ->map(function ($item) {
                    $item->type = 'attendee';
                    return $item;
                });

            // Merge: corporate first, then attendee
            $finalList = $corporates->concat($attendees)
                ->sortByDesc('updated_at') // sort by latest
                ->values();                // reset the keys

            // $finalList = $corporates->concat($attendees)->values();

            return response()->json([
                'status' => true,
                'message' => 'Attendee data fetched successfully',
                'attendees' => $finalList,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch attendee data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    // public function attendyList(Request $request, $userId, $event_id)
    // {
    //     try {
    //         $loggedInUser = User::find($userId);
    //         if (!$loggedInUser) {
    //             return response()->json(['error' => 'User not found'], 404);
    //         }

    //         $isAdmin = $loggedInUser->hasRole('Admin');
    //         $isOrganizer = $loggedInUser->hasRole('Organizer');
    //         $isUser = $loggedInUser->hasRole('User');

    //         $attendeeIds = collect();

    //         if ($isAdmin) {
    //             $bookingAttendeeIds = Booking::whereHas('ticket', function ($query) use ($event_id) {
    //                 $query->where('event_id', $event_id);
    //             })->pluck('attendee_id');

    //             $agentBookingAttendeeIds = Agent::whereHas('ticket', function ($query) use ($event_id) {
    //                 $query->where('event_id', $event_id);
    //             })->pluck('attendee_id');

    //             $attendeeIds = $bookingAttendeeIds->merge($agentBookingAttendeeIds)->unique();
    //         } elseif ($isOrganizer) {
    //             $event = Event::where('id', $event_id)->where('user_id', $userId)->first();

    //             if (!$event) {
    //                 return response()->json(['error' => 'Event not found or unauthorized'], 403);
    //             }

    //             $ticketIds = $event->tickets->pluck('id');

    //             $bookingAttendeeIds = Booking::whereIn('ticket_id', $ticketIds)->pluck('attendee_id');
    //             $agentBookingAttendeeIds = Agent::whereIn('ticket_id', $ticketIds)->pluck('attendee_id');

    //             $attendeeIds = $bookingAttendeeIds->merge($agentBookingAttendeeIds)->unique();
    //         } elseif ($isUser) {
    //             $attendeeIds = Attndy::where('user_id', $userId)->pluck('id');
    //         } else {
    //             return response()->json(['error' => 'Role not recognized'], 403);
    //         }

    //         $attendees = Attndy::with('userData')->whereIn('id', $attendeeIds)->orderBy('id', 'desc')->get();
    //         return response()->json([
    //             'status' => true,
    //             'message' => 'Attendee data fetched successfully',
    //             'attendees' => $attendees,
    //         ], 200);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Failed to fetch attendee data',
    //             'error' => $e->getMessage(),
    //         ], 500);
    //     }
    // }

    // public function attendyList(Request $request, $userId, $event_id)
    // {
    //     try {
    //         $loggedInUser = User::find($userId);
    //         if (!$loggedInUser) {
    //             return response()->json(['error' => 'User not found'], 404);
    //         }

    //         $isAdmin = $loggedInUser->hasRole('Admin');
    //         $isOrganizer = $loggedInUser->hasRole('Organizer');
    //         $isUser = $loggedInUser->hasRole('User');

    //         $attendeeIds = collect();

    //         if ($isAdmin) {
    //             $bookingAttendeeIds = Booking::whereHas('ticket', function ($query) use ($event_id) {
    //                 $query->where('event_id', $event_id);
    //             })->pluck('attendee_id');

    //             $agentBookingAttendeeIds = Agent::whereHas('ticket', function ($query) use ($event_id) {
    //                 $query->where('event_id', $event_id);
    //             })->pluck('attendee_id');

    //             $attendeeIds = $bookingAttendeeIds->merge($agentBookingAttendeeIds)->unique();
    //         } elseif ($isOrganizer) {
    //             $event = Event::where('id', $event_id)->where('user_id', $userId)->first();

    //             if (!$event) {
    //                 return response()->json(['error' => 'Event not found or unauthorized'], 403);
    //             }

    //             $ticketIds = $event->tickets->pluck('id');

    //             $bookingAttendeeIds = Booking::whereIn('ticket_id', $ticketIds)->pluck('attendee_id');
    //             $agentBookingAttendeeIds = Agent::whereIn('ticket_id', $ticketIds)->pluck('attendee_id');

    //             $attendeeIds = $bookingAttendeeIds->merge($agentBookingAttendeeIds)->unique();
    //         } elseif ($isUser) {
    //             $attendeeIds = Attndy::where('user_id', $userId)->pluck('id');
    //         } else {
    //             return response()->json(['error' => 'Role not recognized'], 403);
    //         }

    //         $attendees = Attndy::with('userData')->whereIn('id', $attendeeIds)->orderBy('id', 'desc')->get();

    //         $attendeesData = $attendees->map(function ($attendee) {
    //             $bookingData = Booking::where('attendee_id', $attendee->id)->orderBy('created_at', 'desc')->get();
    //             $agentData = Agent::where('attendee_id', $attendee->id)->orderBy('created_at', 'desc')->get();

    //             $totalBooking = $bookingData->count();
    //             $totalAgent = $agentData->count();
    //             $totalAll = $totalBooking + $totalAgent;

    //             $lastBooking = null;
    //             $lastSource = null;

    //             if ($bookingData->first() && $agentData->first()) {
    //                 if ($bookingData->first()->created_at > $agentData->first()->created_at) {
    //                     $lastBooking = $bookingData->first()->created_at;
    //                     $lastSource = 'Online';
    //                 } else {
    //                     $lastBooking = $agentData->first()->created_at;
    //                     $lastSource = 'Agent';
    //                 }
    //             } elseif ($bookingData->first()) {
    //                 $lastBooking = $bookingData->first()->created_at;
    //                 $lastSource = 'Online';
    //             } elseif ($agentData->first()) {
    //                 $lastBooking = $agentData->first()->created_at;
    //                 $lastSource = 'Agent';
    //             }

    //             return [
    //                 'attendee' => $attendee,
    //                 'total_bookings' => $totalAll,
    //                 'online_bookings' => $totalBooking,
    //                 'agent_bookings' => $totalAgent,
    //                 'last_booking_at' => $lastBooking,
    //                 'last_booking_source' => $lastSource,
    //             ];
    //         });

    //         return response()->json([
    //             'status' => true,
    //             'message' => 'Attendee data fetched successfully',
    //             'attendees' => $attendeesData,
    //         ], 200);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Failed to fetch attendee data',
    //             'error' => $e->getMessage(),
    //         ], 500);
    //     }
    // }


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

    // public function attendeeImages(Request $request)
    // {
    //     try {
    //         $attendees = Attndy::select('Photo')->get();

    //         $imagePaths = $attendees->map(function ($attendee) {
    //             return $attendee->Photo ? public_path(parse_url($attendee->Photo, PHP_URL_PATH)) : null;
    //         })->filter(function ($path) {
    //             return file_exists($path);
    //         })->values();

    //         if ($imagePaths->isEmpty()) {
    //             return response()->json([
    //                 'status' => false,
    //                 'message' => 'No valid images found to zip.',
    //             ], 404);
    //         }

    //         $zipFileName = 'attendee_images_' . now()->timestamp . '.zip';
    //         $zipFilePath = public_path('uploads/zips/' . $zipFileName);

    //         // Create uploads/zips directory if not exists
    //         if (!file_exists(public_path('uploads/zips'))) {
    //             mkdir(public_path('uploads/zips'), 0755, true);
    //         }

    //         $zip = new ZipArchive;
    //         if ($zip->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
    //             foreach ($imagePaths as $fullPath) {
    //                 $zip->addFile($fullPath, basename($fullPath));
    //             }
    //             $zip->close();
    //         } else {
    //             return response()->json([
    //                 'status' => false,
    //                 'message' => 'Could not create ZIP file.',
    //             ], 500);
    //         }

    //         $downloadUrl = url('uploads/zips/' . $zipFileName);

    //         return response()->json([
    //             'status' => true,
    //             'message' => 'ZIP file created successfully.',
    //             'download_url' => $downloadUrl
    //         ]);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Failed to generate ZIP file.',
    //             'error' => $e->getMessage(),
    //         ], 500);
    //     }
    // }


    public function attendeeImages(Request $request)
    {
        try {
            // Get Attndy Photos
            $attendeePhotos = Attndy::select('Photo')->get()->pluck('Photo')->toArray();

            // Get CorporateUser Photos
            $corporatePhotos = CorporateUser::select('Photo')->get()->pluck('Photo')->toArray();

            // Merge both
            $allPhotos = array_merge($attendeePhotos, $corporatePhotos);

            // Map to valid file paths
            $imagePaths = collect($allPhotos)->map(function ($photo) {
                return $photo ? public_path(parse_url($photo, PHP_URL_PATH)) : null;
            })->filter(function ($path) {
                return file_exists($path);
            })->values();

            if ($imagePaths->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'No valid images found to zip.',
                ], 404);
            }

            // ZIP filename and path
            $zipFileName = 'attendee_images_' . now()->timestamp . '.zip';
            $zipDir = public_path('uploads/zips');
            $zipFilePath = $zipDir . '/' . $zipFileName;

            // Ensure directory exists
            if (!file_exists($zipDir)) {
                mkdir($zipDir, 0755, true);
            }

            $zip = new ZipArchive;
            if ($zip->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                foreach ($imagePaths as $fullPath) {
                    $zip->addFile($fullPath, basename($fullPath));
                }
                $zip->close();
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'Could not create ZIP file.',
                ], 500);
            }

            $downloadUrl = url('uploads/zips/' . $zipFileName);

            return response()->json([
                'status' => true,
                'message' => 'ZIP file created successfully.',
                'download_url' => $downloadUrl
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to generate ZIP file.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function attendeeJsone(Request $request)
    {
        try {
            // 1. From Booking table
            // $bookings = Booking::with(['user', 'ticket.event'])->get();
            // $bookingData = $bookings->map(function ($booking) {
            //     return [
            //         'ticket_id'  => $booking->token,
            //         'name'       => $booking->name ?? '',
            //         'mobile_no'  => $booking->number ?? '',
            //         'email_id'   => $booking->email ?? '',
            //         'photo'      => $booking->user?->photo ? basename($booking->user->photo) : null,
            //         'event_name' => $booking->ticket->event->name ?? '',
            //     ];
            // });

            // // 2. From Agent table
            // $agents = Agent::with(['user', 'ticket.event'])->get();
            // $agentData = $agents->map(function ($agent) {
            //     return [
            //         'ticket_id'  => $agent->token,
            //         'name'       => $agent->name ?? '',
            //         'mobile_no'  => $agent->number ?? '',
            //         'email_id'   => $agent->email ?? '',
            //         'photo'      => $agent->user?->photo ? basename($agent->user->photo) : null,
            //         'event_name' => $agent->ticket->event->name ?? '',
            //     ];
            // });

            // // 3. From Pos table
            // $pos = PosBooking::with(['user', 'ticket.event'])->get();
            // $posData = $pos->map(function ($p) {
            //     return [
            //         'ticket_id'  => $p->token,
            //         'name'       => $p->name ?? '',
            //         'mobile_no'  => $p->number ?? '',
            //         'email_id'   => $p->user->email ?? '',
            //         'photo'      => $p->user?->photo ? basename($p->user->photo) : null,
            //         'event_name' => $p->ticket->event->name ?? '',
            //     ];
            // });

            // 4. From AccreditationBooking table
            $accreditation = AccreditationBooking::with(['user', 'ticket.event'])->get();
            $accreditationData = $accreditation->map(function ($a) {
                return [
                    'ticket_id'  => $a->token,
                    'name'       => $a->name ?? '',
                    'mobile_no'  => $a->number ?? '',
                    'email_id'   => $a->user->email ?? '',
                    'photo'      => $a->user?->photo ? basename($a->user->photo) : null,
                    'event_name' => $a->ticket->event->name ?? '',
                ];
            });

            // Combine all 4 datasets
            // $attendees = collect()
            //     ->merge($bookingData)
            //     ->merge($agentData)
            //     ->merge($posData)
            //     ->merge($accreditationData)
            //     ->values();

            return response()->json([
                'status' => true,
                'message' => 'Attendee data fetched successfully',
                'data' => $accreditationData,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch attendee data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Export Bookings
    public function export(Request $request, $event_id)
    {
        try {
            $eventData = Event::findOrFail($event_id);

            // Get attendee IDs from all bookings
            $bookingAttendeeIds = Booking::whereHas('ticket', fn($q) => $q->where('event_id', $event_id))->pluck('attendee_id');
            $agentBookingAttendeeIds = Agent::whereHas('ticket', fn($q) => $q->where('event_id', $event_id))->pluck('attendee_id');
            $corporateBookingAttendeeIds = CorporateBooking::whereHas('ticket', fn($q) => $q->where('event_id', $event_id))->pluck('attendee_id');

            $attendeeIds = $bookingAttendeeIds->merge($agentBookingAttendeeIds)->merge($corporateBookingAttendeeIds)->unique();

            // Fetch Attendee records
            $attendeesQuery = Attndy::withTrashed()
                ->with(['event.category', 'userData'])
                ->whereIn('id', $attendeeIds);

            // Apply date filter if given
            $dates = $request->input('date') ? explode(',', $request->input('date')) : null;
            if ($dates) {
                if (count($dates) === 1) {
                    $attendeesQuery->whereDate('created_at', Carbon::parse($dates[0])->toDateString());
                } elseif (count($dates) === 2) {
                    $attendeesQuery->whereBetween('created_at', [
                        Carbon::parse($dates[0])->startOfDay(),
                        Carbon::parse($dates[1])->endOfDay(),
                    ]);
                }
            }
            $attendees = $attendeesQuery->orderBy('id', 'desc')->get();

            // 🔹 Fetch CorporateUsers from CorporateBooking attendee IDs
            $corporateUsers = CorporateUser::whereIn('id', $corporateBookingAttendeeIds)->get();

            // 🔹 Merge both data types into one export
            return Excel::download(new AttndyBookingExport($attendees, $corporateUsers, $eventData->name), 'Attendees_Export.xlsx');
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Export failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // public function export(Request $request, $event_id)
    // {
    //     try {
    //         $eventData = Event::findOrFail($event_id);
    //         // Get attendee IDs from bookings and agent bookings
    //         $bookingAttendeeIds = Booking::whereHas('ticket', function ($query) use ($event_id) {
    //             $query->where('event_id', $event_id);
    //         })->pluck('attendee_id');

    //         $agentBookingAttendeeIds = Agent::whereHas('ticket', function ($query) use ($event_id) {
    //             $query->where('event_id', $event_id);
    //         })->pluck('attendee_id');

    //         $attendeeIds = $bookingAttendeeIds->merge($agentBookingAttendeeIds)->unique();

    //         // Build query
    //         $query = Attndy::withTrashed()
    //             ->with(['event.category', 'userData'])
    //             ->whereIn('id', $attendeeIds);

    //         // Date filter (if passed)
    //         $dates = $request->input('date') ? explode(',', $request->input('date')) : null;

    //         if ($dates) {
    //             if (count($dates) === 1) {
    //                 $query->whereDate('created_at', Carbon::parse($dates[0])->toDateString());
    //             } elseif (count($dates) === 2) {
    //                 $query->whereBetween('created_at', [
    //                     Carbon::parse($dates[0])->startOfDay(),
    //                     Carbon::parse($dates[1])->endOfDay(),
    //                 ]);
    //             }
    //         }
    //         $attendees = $query->orderBy('id', 'desc')->get();

    //         return Excel::download(new AttndyBookingExport($attendees, $eventData->name), 'Attendees_export.xlsx');
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Export failed',
    //             'error' => $e->getMessage(),
    //         ], 500);
    //     }
    // }
  
  
  public function flowData(Request $request)
    {
        // Forward the incoming request data to the external API
        $response = Http::post('https://rtt.smsforyou.biz/api/flow', $request->all());

        // Return the external API's response back to the caller
        return response()->json($response->json(), $response->status());
    }
  
 public function health()
    {
        return response()->json([
    'status' => 'healthy',
    'timestamp' => now()->toISOString(),
    'message' => 'WhatsApp Flow endpoint is ready'
], 200, ['Content-Type' => 'application/json']);
    }
}
