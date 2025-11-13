<?php

namespace App\Http\Controllers;

use App\Jobs\SendEventNotification;
use App\Models\AccreditationBooking;
use App\Models\Agent;
use App\Models\Announcement;
use App\Models\Booking;
use App\Models\ComplimentaryBookings;
use App\Models\Event;
use App\Models\FcmToken;
use App\Models\PosBooking;
use App\Models\SponsorBooking;
use App\Models\Ticket;
use App\Services\FirebaseNotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{

    protected $serverKey;

    public function __construct()
    {
        // $this->middleware(['auth', 'admin']);
        $this->serverKey = config('services.firebase.server_key');
    }

    public function sendToToken(Request $request)
    {
        $request->validate([
            'title' => 'required|string',
            'body' => 'required|string',
            'data' => 'nullable|array',
            'url' => 'nullable|string',
        ]);

        $userGroup = $request->input('userGroup');

        $query = FcmToken::query();

        switch ($userGroup) {
            case 'live':
                $query->where('created_at', '>=', Carbon::now()->subMinutes(5));
                break;
            case '4hours':
                $query->where('created_at', '>=', Carbon::now()->subHours(4));
                break;
            case 'today':
                $query->where('created_at', '>=', Carbon::today());
                break;
            case '2days':
                $query->where('created_at', '>=', Carbon::now()->subDays(2));
                break;
            case 'all':
            default:
                // No filtering
                break;
        }

        $tokens = $query->pluck('token')->toArray();

        // $image = $request->image ;
        $image = $this->storeNotificationImage($request);


        $url = $request->url ?? 'https://www.ticket.getyourticekt.in';
        $imageUrl = $image ?? rtrim(env('IMAGE_URL', 'https://server.getyourticket.in'), '/') . '/uploads/setting/get-your-ticket-6805f026ded03_Untitled%20design%203.png';

        // $imageUrl = $image ?? 'https://server.getyourticket.in/uploads/setting/get-your-ticket-6805f026ded03_Untitled%20design%203.png';

        // Dispatch job
        // SendPushNotificationJob::dispatch(
        //     $tokens,
        //     $request->title,
        //     $request->body,
        //     $url,
        //     $imageUrl,
        //     $request->data ?? []
        // );
        $firebaseService = new FirebaseNotificationService();
        $result = $firebaseService->sendToMultipleDevices(
            $tokens,
            $request->title,
            $request->body,
            $url,
            $imageUrl,
            $request->data ?? []
        );
        $this->storeDataAnnouncements($request->title, $request->body, $request->url, $image, $tokens);

        return response()->json([
            'success' => true,
            'message' => 'Notification dispatched to ' . count($tokens) . ' devices',
            'result' => $result,
        ]);
    }


    public function storeToken(Request $request)
    {
        try {
            $request->validate([
                'token' => 'required|string',
                'user_id' => 'nullable|numeric'
            ]);

            FcmToken::updateOrInsert(
                ['token' => $request->token],
                [
                    'user_id' => $request->user_id ?? null,
                    'updated_at' => now(),
                    'created_at' => DB::raw('IFNULL(created_at, NOW())')
                ]
            );

            return response()->json(['success' => true, 'message' => 'Token saved']);
        } catch (\Exception $e) {
            Log::error('Error saving FCM token: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error saving token'], 500);
        }
    }


    private function storeDataAnnouncements($title, $body, $url, $image, $tokens)
    {
        try {
            $announcement = new Announcement();
            $announcement->user_id = auth()->id(); // Optional: only if user is authenticated
            $announcement->title = $title;
            $announcement->body = $body;
            $announcement->url = $url;
            $announcement->image_url = $image;
            $announcement->count = count($tokens); // optional usage of data array

            $announcement->save();

            return response()->json([
                'status' => true,
                'message' => 'Announcement saved successfully',
                'announcementData' => $announcement,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    private function storeNotificationImage(Request $request): ?string
    {
        if ($request->hasFile('image')) {
            $eventDirectory = "NotificationImages" . str_replace(' ', '_', strtolower($request->name));
            $layoutFolder = 'image';
            $file = $request->file('image');
            $user = Auth::user();
            $userName = $user->name ?? 'default';
            $safeUserName = str_replace(' ', '_', strtolower($userName));

            $folder = "{$eventDirectory}/{$layoutFolder}/{$safeUserName}";

            return $this->storeFile($file, $folder);
        }

        return null;
    }

    private function storeFile($file, $folder, $disk = 'public')
    {
        $filename = 'get-your-ticket-' . uniqid() . '_' . $file->getClientOriginalName();

        $path = $file->storeAs("uploads/{$folder}", $filename, $disk);

        return asset("/{$path}");
    }


    public function sendNotification(Request $request)
    {
        try {
            $eventIds = $request->input('event_ids', []);
            $eventDay = $request->input('eventDay');
            // $targetDate = ($type === 'tomorrow')
            //     ? now()->addDay()->format('Y-m-d')
            //     : now()->format('Y-m-d');
    
            $events = Event::whereIn('id', $eventIds)->get();
    
            $userEventMap = [];
    
            foreach ($events as $event) {
                $ticketIds = Ticket::where('event_id', $event->id)->pluck('id');
    
                // Main Bookings
                $bookings = Booking::whereIn('ticket_id', $ticketIds)->with('user')->get();
                foreach ($bookings as $booking) {
                    if ($booking->user) {
                        $number = $booking->number;
                        $userEventMap[$number]['name'] = $booking->name;
                        $userEventMap[$number]['events'][] = $event->name;
                        $userEventMap[$number]['thumbnail'] = $event->thumbnail;
                    }
                }
    
                // Additional Sources
                $sources = [
                    ['model' => Agent::class, 'nameField' => 'name', 'phoneField' => 'number'],
                    ['model' => ComplimentaryBookings::class, 'nameField' => 'name', 'phoneField' => 'number'],
                    ['model' => AccreditationBooking::class, 'nameField' => 'name', 'phoneField' => 'number'],
                    ['model' => SponsorBooking::class, 'nameField' => 'name', 'phoneField' => 'number'],
                    ['model' => PosBooking::class, 'nameField' => 'name', 'phoneField' => 'number'],
                ];
    
                foreach ($sources as $source) {
                    $entries = $source['model']::whereIn('ticket_id', $ticketIds)->get();
                    foreach ($entries as $entry) {
                        $number = $entry[$source['phoneField']];
                        $name = $entry[$source['nameField']] ?? $source['model'];
                        $userEventMap[$number]['name'] = $name;
                        $userEventMap[$number]['events'][] = $event->name;
                        $userEventMap[$number]['thumbnail'] = $event->thumbnail;
                    }
                }
            }
    
            // Prepare and Dispatch
            $finalData = [];
    
            foreach ($userEventMap as $number => $data) {
                $uniqueEvents = array_values(array_unique($data['events']));
                $eventName1 = $uniqueEvents[0] ?? 'N/A';
                $eventName2 = $uniqueEvents[1] ?? 'N/A';
    
                $userData = [
                    'name' => $data['name'],
                    'number' => $number,
                    'eventName1' => $eventName1,
                    'eventName2' => $eventName2,
                    'eventThumbnail' => $data['thumbnail'] ?? '',
                    'templateName' => $eventDay === 'tomorrow' ? 'Tomorrow Event Notify' : 'Today Event Notify',
                    'whatsappTemplateData' => 'Today Event Notify',
                    'eventDay' => $eventDay,
                ];
    
                dispatch(new SendEventNotification($userData));
    
                $finalData[] = [
                    'user_name' => $data['name'],
                    'user_phone' => $number,
                    'events' => $uniqueEvents
                ];
            }
    
            return response()->json([
                'status' => true,
                'message' => 'Notification sent successfully!',
                // 'data' => $finalData
            ], 200);
    
        } catch (\Exception $e) {
            Log::error('Notification Send Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
    
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong while sending notifications.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
