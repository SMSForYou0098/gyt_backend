<?php

namespace App\Http\Controllers;

use App\Models\ContactUs;
use App\Models\WhatsappApi;
use App\Services\SmsService;
use App\Services\WhatsappServiceForCountectUs;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class ContactUsController extends Controller
{
    public function index(Request $request)
    {
        if ($request->has('date')) {
            $dates = explode(',', $request->date);
            if (count($dates) === 1 || ($dates[0] === $dates[1])) {
                // Single date
                $startDate = Carbon::parse($dates[0])->startOfDay();
                $endDate = Carbon::parse($dates[0])->endOfDay();
            } elseif (count($dates) === 2) {
                // Date range
                $startDate = Carbon::parse($dates[0])->startOfDay();
                $endDate = Carbon::parse($dates[1])->endOfDay();
            } else {
                return response()->json(['status' => false, 'message' => 'Invalid date format'], 400);
            }
        } else {
            // Default: Today's bookings
            $startDate = Carbon::today()->startOfDay();
            $endDate = Carbon::today()->endOfDay();
        }

        $ContactUs = ContactUs::with('queryRelation')->whereBetween('created_at', [$startDate, $endDate])->get();
        if ($ContactUs->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'ContactUs not found'
            ], 200);
        }
        return response()->json([
            'status' => true,
            'data' => $ContactUs,
        ], 200);
    }

    public function store(Request $request, SmsService $smsService, WhatsappServiceForCountectUs $whatsappService)
    {
        try {

        $contactData = new ContactUs();
        $contactData->name = $request->name;
        $contactData->email = $request->email;
        $contactData->number = $request->phone;
        $contactData->address = $request->address;
        $contactData->message = $request->message;
        $contactData->query = $request->input('query');

        if ($request->hasFile('screenshot') && $request->file('screenshot')->isValid()) {
            $contactData->image = $this->storeFile($request->file('screenshot'), 'contactUs');
        }

        $contactData->save();

        // $emails = ['smit.bhagat@smsforyou.biz', 'janak.rana@smsforyou.biz'];
        // $emails = ['kinjal@yopmail.com', 'kinjal1@yopmail.com'];
        // foreach ($emails as $email) {
        //     dispatch(new SendContactUsMail($contactData->id, $email));
        // }

        $whatsappTemplate = WhatsappApi::where('title', 'Request Received Contact-us1')->first();
        $whatsappTemplateName = $whatsappTemplate->template_name ?? '';

        $data = (object) [
            'name' => $contactData->name,
            'number' => $contactData->number,
            'templateName' => 'Request Received Contact-us',
            'whatsappTemplateData' => $whatsappTemplateName,

            // 'values' => [
            //    $contactData->name ?? 'Guest',
            // //    $contactData->number ?? '0000000000',
            // ],
            // 'replacements' => [
            //     ':C_Name' => $contactData->name,

            // ]

        ];
        //return $data;
        if ($contactData) {
            $smsService->send($data);
            $whatsappService->send($data);
        }

        return response()->json(['status' => true, 'message' => 'contactData craete successfully', 'data' => $contactData], 200);
        } catch (\Exception $e) {
        return response()->json(['status' => false, 'message' => 'Failed to contactData '], 404);
        }
    }

    public function show($id)
    {
        $contactData = ContactUs::with('queryRelation')->find($id);

        if (!$contactData) {
            return response()->json(['status' => false, 'message' => 'contactData not found'], 200);
        }

        return response()->json(['status' => true, 'data' => $contactData], 200);
    }

    public function update(Request $request, $id)
    {
        try {
            $contactData = ContactUs::findOrFail($id); // existing record fetch

            $contactData->name = $request->name ?? $contactData->name;
            $contactData->email = $request->email ?? $contactData->email;
            $contactData->number = $request->phone ?? $contactData->number;
            $contactData->address = $request->address ?? $contactData->address;
            $contactData->message = $request->message ?? $contactData->message;
            $contactData->query = $request->query ?? $contactData->query;

            if ($request->hasFile('screenshot') && $request->file('screenshot')->isValid()) {
                $contactData->image = $this->storeFile($request->file('screenshot'), 'contactUs');
            }

            $contactData->save();

            return response()->json(['status' => true, 'message' => 'contactData updated successfully', 'data' => $contactData], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to update contactData'], 404);
        }
    }

    public function destroy(string $id)
    {
        $contactData = ContactUs::where('id', $id)->firstOrFail();
        if (!$contactData) {
            return response()->json(['status' => false, 'message' => 'contactData not found'], 404);
        }

        $contactData->delete();
        return response()->json(['status' => true, 'message' => 'contactData deleted successfully'], 200);
    }


    private function storeFile($file, $folder, $disk = 'public')
    {
        $filename = uniqid() . '_' . $file->getClientOriginalName();
        $path = $file->storeAs('uploads/' . $folder, $filename, $disk);
        return Storage::disk($disk)->url($path);
    }
}
