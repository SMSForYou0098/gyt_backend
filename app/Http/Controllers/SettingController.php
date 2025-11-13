<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

// use Storage;

class SettingController extends Controller
{
    public function index()
    {
        $settings = Setting::first();
        return response()->json(['status' => true, 'data' => $settings], 200);
    }

    //kinjal

    public function storeBanner(Request $request)
    {
        $settings = Setting::find(1);
        $banners = [];
        $index = 1;

        while ($request->has("banners_{$index}_pcUrl") || $request->has("banners_{$index}_mobileUrl") || $request->has("banners_{$index}_redirectUrl") || $request->has("banners_{$index}_type")) {
            $bannerData = [
                'banners_' . $index . '_mobileUrl' => null,
                'banners_' . $index . '_pcUrl' => null,
                'banners_' . $index . '_redirectUrl' => $request->input("banners_{$index}_redirectUrl"),
                'banners_' . $index . '_type' => $request->input("banners_{$index}_type"),
            ];

            if ($request->hasFile("banners_{$index}_mobileUrl") && $request->file("banners_{$index}_mobileUrl") instanceof \Illuminate\Http\UploadedFile) {
                $mobilePath = $request->file("banners_{$index}_mobileUrl")->store('uploads/system/SuccessfulEvent/banners/mobile', 'public');
                $bannerData['banners_' . $index . '_mobileUrl'] = asset($mobilePath);
            } else {
                // Fallback for when no new file uploaded but input has old URL
                $bannerData['banners_' . $index . '_mobileUrl'] = $request->input("banners_{$index}_mobileUrl");
            }

            if ($request->hasFile("banners_{$index}_pcUrl") && $request->file("banners_{$index}_pcUrl") instanceof \Illuminate\Http\UploadedFile) {
                $pcPath = $request->file("banners_{$index}_pcUrl")->store('uploads/system/SuccessfulEvent/banners/pc', 'public');
                $bannerData['banners_' . $index . '_pcUrl'] = asset($pcPath);
            } else {
                // Fallback for when no new file uploaded but input has old URL
                $bannerData['banners_' . $index . '_pcUrl'] = $request->input("banners_{$index}_pcUrl");
            }

            $banners[] = $bannerData;
            $index++;
        }

        if ($settings) {
            $settings->update(['banners' => json_encode($banners)]);
        } else {
            Setting::create(['id' => 1, 'banners' => json_encode($banners)]);
        }

        return response()->json(['status' => true, 'message' => 'Banners saved successfully.', 'data' => $banners]);
    }



    public function store(Request $request)
    {
        try {
            function storeFile($file, $folder = 'setting', $disk = 'public')
            {
                $filename = 'get-your-ticket-' . uniqid() . '_' . $file->getClientOriginalName();
                if (Storage::disk($disk)->exists('uploads/' . $folder . '/' . $filename)) {
                    Storage::disk($disk)->delete('uploads/' . $folder . '/' . $filename);
                }
                $path = $file->storeAs('uploads/' . $folder, $filename, $disk);
                return Storage::disk($disk)->url($path);
            }

            $settings = Setting::firstOrNew([]);

            // Update fields only if they exist in the request
            $settings->app_name = $request->input('app_name', $settings->app_name);
            $settings->meta_title = $request->input('meta_title', $settings->meta_title);
            $settings->meta_tag = $request->input('meta_tag', $settings->meta_tag);
            $settings->meta_description = $request->input('meta_description', $settings->meta_description);
            $settings->copyright = $request->input('copyright', $settings->copyright);
            $settings->copyright_link = $request->input('copyright_link', $settings->copyright_link);
            $settings->complimentary_attendee_validation = $request->input('complimentary_attendee_validation', $settings->complimentary_attendee_validation);
            $settings->footer_address = $request->input('footer_address', $settings->footer_address);
            $settings->footer_contact = $request->input('footer_contact', $settings->footer_contact);
            $settings->site_credit = $request->input('site_credit', $settings->site_credit);
            $settings->missed_call_no = $request->input('missed_call_no', $settings->missed_call_no);
            $settings->whatsapp_number = $request->input('whatsapp_number', $settings->whatsapp_number);
            $settings->footer_email = $request->input('footer_email', $settings->footer_email);
            $settings->footer_whatsapp_number = $request->input('footer_whatsapp_number', $settings->footer_whatsapp_number);
            $settings->notify_req = $request->input('notify_req', $settings->notify_req);
            $settings->home_divider_url = $request->input('home_divider_url', $settings->home_divider_url);
            $settings->navColor = $request->input('navColor', $settings->navColor);
            $settings->fontColor = $request->input('fontColor', $settings->fontColor);
            $settings->footer_font_Color = $request->input('footer_font_Color', $settings->footer_font_Color);
            $settings->home_bg_color = $request->input('home_bg_color', $settings->home_bg_color);

            // Handle file uploads
            if ($request->hasFile('logo')) {
                $settings->logo = storeFile($request->file('logo'));
            }
            if ($request->hasFile('mo_logo')) {
                $settings->mo_logo = storeFile($request->file('mo_logo'));
            }
            if ($request->hasFile('favicon')) {
                $settings->favicon = storeFile($request->file('favicon'));
            }
            if ($request->hasFile('footer_logo')) {
                $settings->footer_logo = storeFile($request->file('footer_logo'));
            }
            if ($request->hasFile('nav_logo')) {
                $settings->nav_logo = storeFile($request->file('nav_logo'));
            }
            if ($request->hasFile('auth_logo')) {
                $settings->auth_logo = storeFile($request->file('auth_logo'));
            }
            if ($request->hasFile('footer_bg')) {
                $settings->footer_bg = storeFile($request->file('footer_bg'));
            }
            if ($request->hasFile('home_divider')) {
                $settings->home_divider = storeFile($request->file('home_divider'));
            }
            if ($request->hasFile('e_signature')) {
                $settings->e_signature = storeFile($request->file('e_signature'));
            }
            if ($request->hasFile('agreement_pdf')) {
                $settings->agreement_pdf = storeFile($request->file('agreement_pdf'));
            }

            // Save the settings
            $settings->save();

            return response()->json(['status' => true, 'success' => 'Settings saved successfully.'], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'error' => 'Failed to save settings.', 'message' => $e->getMessage()], 500);
        }
    }


    public function getBanners()
    {
        $settings = Setting::first();
        $banners = json_decode($settings->banners, true); // decode as associative array
        $lastUpdated = $settings->updated_at;
    
        // Reverse the banners array so that last banner comes first
        $reversedBanners = array_reverse($banners);
    
        return response()->json([
            'banners' => $reversedBanners,
            'last_updated' => $lastUpdated,
        ]);
    }
    

    public function updateLiveUser(Request $request, $id)
    {
        // Validate the request
        $request->validate([
            'live_user' => 'required|integer', // Ensure live_user is provided and is an integer
        ]);

        try {
            // Find the setting by ID
            $setting = Setting::findOrFail($id);

            // Update the live_user field
            $setting->live_user = $request->live_user;
            $setting->save();

            return response()->json(['status' => true, 'message' => 'Live user updated successfully', 'setting' => $setting], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Setting not found or update failed'], 404);
        }
    }

    public function sponsorsImages(Request $request)
    {
        $imageUrls = [];

        for ($i = 1; $i <= 4; $i++) {
            if ($request->hasFile("image_$i")) {
                $image = $request->file("image_$i");

                if ($image instanceof \Illuminate\Http\UploadedFile) {
                    $eventDirectory = 'sponsors_images';
                    $fileName = 'get-your-ticket-' . uniqid() . '_' . $image->getClientOriginalName();
                    $path = $image->storeFile("uploads/$eventDirectory", $fileName, 'public');
                    $imageUrls[] = Storage::disk('public')->url($path);
                }
            }
        }
        $sponsor = Setting::where('id', 1)->update([
            'sponsors_images' => json_encode($imageUrls),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Images uploaded successfully',
            'data' => $imageUrls,
        ], 201);
    }

    public function getSponsorsImages()
    {
        // Fetch the setting record with id = 1
        $sponsorSetting = Setting::where('id', 1)->first();

        // Decode the JSON-encoded sponsors_images field
        $imageUrls = json_decode($sponsorSetting->sponsors_images, true);

        // Return the image URLs in the response
        return response()->json([
            'status' => true,
            'message' => 'Sponsors images fetched successfully',
            'data' => $imageUrls, // Return the array of image URLs
        ], 200);
    }

    public function pcSponsorsImages(Request $request)
    {
        $imageUrls = [];

        for ($i = 1; $i <= 4; $i++) {
            if ($request->hasFile("image_$i")) {
                $image = $request->file("image_$i");

                if ($image instanceof \Illuminate\Http\UploadedFile) {
                    $eventDirectory = 'pc_sponsors_images';

                    // Generate a unique file name with "get-your-ticket-" prefix
                    $fileName = 'get-your-ticket-' . uniqid() . '_' . $image->getClientOriginalName();

                    // Store the file with the new name
                    $path = $image->storeFile("uploads/$eventDirectory", $fileName, 'public');

                    // Get the URL of the stored file
                    $imageUrls[] = Storage::disk('public')->url($path);
                }
            }
        }
        $sponsor = Setting::where('id', 1)->update([
            'pc_sponsors_images' => json_encode($imageUrls),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Images uploaded successfully',
            'data' => $imageUrls,
        ], 201);
    }

    public function getPcSponsorsImages()
    {
        // Fetch the setting record with id = 1
        $sponsorSetting = Setting::where('id', 1)->first();

        $imageUrls = json_decode($sponsorSetting->pc_sponsors_images, true);

        // Return the image URLs in the response
        return response()->json([
            'status' => true,
            'message' => 'Sponsors images fetched successfully',
            'data' => $imageUrls, // Return the array of image URLs
        ], 200);
    }

    public function footerDataGet()
    {
        $settings = Setting::first();
        return response()->json(['status' => true, 'data' => $settings], 200);
    }

    private function storeFile($file, $folder, $disk = 'public')
    {
        $filename = uniqid() . '_' . $file->getClientOriginalName();
        $path = $file->storeAs('uploads/' . $folder, $filename, $disk);
        return Storage::disk($disk)->url($path);
    }
}
