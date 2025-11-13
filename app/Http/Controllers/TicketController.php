<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Event;
use App\Models\Ticket;
use Illuminate\Http\Request;
use App\Models\PromoCode;
use App\Models\TicketHistory;
use Storage;

class TicketController extends Controller
{

    public function index($id)
    {
        $tickets = Ticket::where('event_id', $id)->get();
        return response()->json(['status' => true, 'tickets' => $tickets], 200);
    }

    public function info($id)
    {
        // Fetch the ticket by ID
        $ticket = Ticket::find($id);

        if (!$ticket) {
            return response()->json(['status' => false, 'message' => 'Ticket not found'], 404);
        }

        // Calculate total available tickets
        $totalTickets = $ticket->ticket_quantity;

        // Calculate total booked tickets from all booking types
        $bookedTickets = $ticket->bookings->count() + $ticket->posBookings->count() + $ticket->complimentaryBookings->count();

        // Calculate remaining tickets
        $remainingTickets = $totalTickets - $bookedTickets;

        return response()->json([
            'status' => true,
            'ticket' => [
                'total' => $totalTickets,
                'remaining' => $remainingTickets,
            ]
        ], 200);
    }

    //kinjal
    public function create(Request $request, $id)
    {
        try {
            $event_id = Event::where('id', $id)->firstOrFail();
            $bookingIds = $request->input('access_area');


            if (is_null($bookingIds)) {
                $bookingIds = [];
            } elseif (is_string($bookingIds)) {
                $bookingIds = explode(',', $bookingIds);
            }
            
            $bookingIds = array_map('intval', array_filter($bookingIds, fn($id) => trim($id) !== ''));

            $ticket = new Ticket();
            $ticket->event_id = $event_id->id;
            $ticket->name = $request->ticket_title;
            $ticket->currency = $request->currency;
            $ticket->price = $request->price;
            $ticket->ticket_quantity = $request->ticket_quantity;
            $ticket->booking_per_customer = $request->booking_per_customer;
            $ticket->user_booking_limit = $request->user_booking_limit;
         
            // $ticket->description = $request->ticket_description;
            $ticket->taxes = $request->taxes;
            $ticket->sale = $request->sale === 'true' ? 1 : 0;
            $ticket->sale_date = $request->sale_date;
            $ticket->sale_price = $request->sale_price;
            $ticket->sold_out = $request->sold_out === 'true' ? 1 : 0;
            $ticket->booking_not_open = $request->booking_not_open === 'true' ? 1 : 0;
            $ticket->ticket_template = $request->ticket_template;
            $ticket->fast_filling = $request->fast_filling === 'true' ? 1 : 0;
            $ticket->status = $request->status === 'true' ? 1 : 0;
            $ticket->modify_as = $request->status === 'modify_access_area' ? 1 : 0;
            $ticket->sold_out = $request->sold_out === 'true' ? 1 : 0;
            $ticket->allow_pos = $request->allow_pos === 'true' ? 1 : 0;
            $ticket->access_area = $bookingIds;

            $ticket->batch_id = 'TICKET-' . $event_id->id . '-' . strtoupper(uniqid());

            if ($request->hasFile('background_image') && $request->file('background_image')->isValid()) {
                $file = $request->file('background_image');
                $fileName = 'get-your-ticket-' . uniqid() . '-' . $file->getClientOriginalName();
                $folder = 'uploads/Ticket/backgrounds';
                $file->move(public_path($folder), $fileName);
                $imagePath = url($folder . '/' . $fileName);

                $ticket->background_image = $imagePath;
            }

            // Parse and validate promocode_codes
            if (isset($request->promocode_codes)) {
                $promocodeCodes = is_array($request->promocode_codes)
                    ? $request->promocode_codes
                    : explode(',', trim($request->promocode_codes, '[]'));
                $validPromocodeIds = [];
                foreach ($promocodeCodes as $code) {
                    $cleanedCode = trim($code, ' "');
                    $promocode = Promocode::where('id', $cleanedCode)->first();


                    if (!$promocode) {
                        return response()->json(['status' => false, 'message' => "Invalid promocode: $cleanedCode"], 400);
                    }

                    $validPromocodeIds[] = $promocode->id;
                }

                $ticket->promocode_ids = json_encode($validPromocodeIds);
            } else {
                $ticket->promocode_ids = null;
            }

            $ticket->save();
            $ticket->load('event');

            $tickets = Ticket::where('event_id', $event_id->id)->get();

            $history = new TicketHistory();
            $history->ticket_id = $ticket->id;
            $history->batch_id = $ticket->batch_id;
            $history->name = $ticket->name;
            $history->price = $ticket->price;
            $history->currency = $ticket->currency;
            $history->ticket_quantity = $ticket->ticket_quantity;
            $history->booking_per_customer = $ticket->booking_per_customer;
            // $history->description = $ticket->description;
            $history->taxes = $ticket->taxes;
            $history->sale = $ticket->sale;
            $history->sale_date = $ticket->sale_date;
            $history->sale_price = $ticket->sale_price;
            $history->sold_out = $ticket->sold_out;
            $history->booking_not_open = $ticket->booking_not_open;
            $history->ticket_template = $ticket->ticket_template;
            $history->fast_filling = $ticket->fast_filling;
            $history->status = $ticket->status;
            $history->background_image = $ticket->background_image;
            $history->promocode_ids = $ticket->promocode_ids;
            $history->user_booking_limit = $ticket->user_booking_limit;

            $history->save();


            return response()->json(['status' => true, 'message' => 'Ticket Created Successfully', 'tickets' => $tickets], 201);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to create Ticket: ' . $e->getMessage()], 500);
        }
    }

    // public function update(Request $request, $id)
    // {
    //     try {
    //         $ticket = Ticket::findOrFail($id);
    //         $originalTicket = $ticket->replicate(); // Original copy for comparison

    //         // Basic Fields Update
    //         $ticket->fill([
    //             'name' => $request->ticket_title,
    //             'currency' => $request->currency,
    //             'price' => $request->price,
    //             'ticket_quantity' => $request->ticket_quantity,
    //             'booking_per_customer' => $request->booking_per_customer,
    //             'description' => $request->ticket_description,
    //             'taxes' => $request->taxes,
    //             'sale' => $request->sale === 'true' ? 1 : 0,
    //             'sale_date' => $request->sale_date,
    //             'sale_price' => $request->sale_price,
    //             'sold_out' => $request->sold_out === 'true' ? 1 : 0,
    //             'booking_not_open' => $request->booking_not_open === 'true' ? 1 : 0,
    //             'ticket_template' => $request->ticket_template,
    //             'fast_filling' => $request->fast_filling === 'true' ? 1 : 0,
    //         ]);

    //         $ticket->batch_id = 'TICKET-' . $ticket->event_id . '-' . strtoupper(uniqid());

    //         // Background Image Handling
    //         if ($request->hasFile('background_image') && $request->file('background_image')->isValid()) {
    //             $file = $request->file('background_image');
    //             $fileName = 'get-your-ticket-' . uniqid() . '-' . $file->getClientOriginalName();
    //             $folder = 'uploads/Ticket/backgrounds';

    //             if ($ticket->background_image) {
    //                 $oldImagePath = public_path(str_replace(url('/'), '', $ticket->background_image));
    //                 if (file_exists($oldImagePath)) {
    //                     unlink($oldImagePath);
    //                 }
    //             }

    //             $file->move(public_path($folder), $fileName);
    //             $ticket->background_image = url($folder . '/' . $fileName);
    //         }

    //         // Promocode Handling
    //         $validPromocodeIds = [];
    //         if ($request->filled('promocode_codes')) {
    //             $promocodeIds = explode(',', $request->promocode_codes);
    //             foreach ($promocodeIds as $promoId) {
    //                 $promo = Promocode::find(trim($promoId));
    //                 if (!$promo) {
    //                     return response()->json(['status' => false, 'message' => "Invalid promocode ID: $promoId"], 400);
    //                 }
    //                 $validPromocodeIds[] = $promo->id;
    //             }
    //             $ticket->promocode_ids = json_encode($validPromocodeIds);
    //         } else {
    //             $ticket->promocode_ids = null;
    //         }

    //         $ticket->save();
    //         $tickets = Ticket::where('event_id', $ticket->event_id)->get();

    //         // Save Ticket History
    //         $history = new TicketHistory();
    //         $history->ticket_id = $ticket->id;
    //         $history->batch_id = $ticket->batch_id;

    //         $fields = [
    //             'name' => $request->ticket_title,
    //             'currency' => $request->currency,
    //             'price' => $request->price,
    //             'ticket_quantity' => $request->ticket_quantity,
    //             'booking_per_customer' => $request->booking_per_customer,
    //             'description' => $request->ticket_description,
    //             'taxes' => $request->taxes,
    //             'sale' => $request->sale === 'true' ? 1 : 0,
    //             'sale_date' => $request->sale_date,
    //             'sale_price' => $request->sale_price,
    //             'sold_out' => $request->sold_out === 'true' ? 1 : 0,
    //             'booking_not_open' => $request->booking_not_open === 'true' ? 1 : 0,
    //             'ticket_template' => $request->ticket_template,
    //             'fast_filling' => $request->fast_filling === 'true' ? 1 : 0,
    //         ];

    //         foreach ($fields as $field => $newValue) {
    //             if ($originalTicket->$field != $newValue) {
    //                 $history->$field = $newValue;
    //             }
    //         }

    //         if (isset($ticket->background_image)) {
    //             $history->background_image = $ticket->background_image;
    //         }

    //         if (!empty($validPromocodeIds)) {
    //             $oldPromocodes = json_decode($originalTicket->promocode_ids ?? '[]', true);
    //             sort($oldPromocodes);
    //             sort($validPromocodeIds);

    //             if ($oldPromocodes !== $validPromocodeIds) {
    //                 $history->promocode_ids = json_encode($validPromocodeIds);
    //             }
    //         }

    //         $history->save();

    //         return response()->json([
    //             'status' => true,
    //             'message' => 'Ticket Updated Successfully',
    //             'tickets' => $tickets
    //         ], 200);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Failed to update Ticket: ' . $e->getMessage()
    //         ], 500);
    //     }
    // }
    public function update(Request $request, $id)
    {
        try {
            $ticket = Ticket::findOrFail($id);
            $originalTicket = $ticket->replicate();
            $hasChanges = false;
    
            $bookingIds = $request->input('access_area');
    
            if (is_null($bookingIds)) {
                $bookingIds = [];
            } elseif (is_string($bookingIds)) {
                $bookingIds = explode(',', $bookingIds);
            }
    
            $bookingIds = array_map('intval', array_filter($bookingIds, fn($id) => trim($id) !== ''));
    
            // Basic Fields Update
            $newData = [
                'name' => $request->ticket_title,
                'currency' => $request->currency,
                'price' => $request->price,
                'ticket_quantity' => $request->ticket_quantity,
                'booking_per_customer' => $request->booking_per_customer,
                // 'description' => $request->ticket_description,
                'user_booking_limit' => $request->user_booking_limit,
                'taxes' => $request->taxes,
                'sale' => $request->sale === 'true' ? 1 : 0,
                'sale_date' => $request->sale_date,
                'sale_price' => $request->sale_price,
                'sold_out' => $request->sold_out === 'true' ? 1 : 0,
                'booking_not_open' => $request->booking_not_open === 'true' ? 1 : 0,
                'ticket_template' => $request->ticket_template,
                'fast_filling' => $request->fast_filling === 'true' ? 1 : 0,
                'status' => $request->status === 'true' ? 1 : 0,
                'modify_as' => $request->modify_access_area === 'true' ? 1 : 0,
               'allow_pos' => $request->allow_pos === 'true' ? 1 : 0,
                'allow_agent' => $request->allow_agent === 'true' ? 1 : 0,
                'access_area' => $bookingIds,
            ];
    
            // Check if any basic fields have changed
            foreach ($newData as $field => $value) {
                if ($ticket->$field != $value) {
                    $hasChanges = true;
                    break;
                }
            }
    
            // Background Image Change Check
            if ($request->hasFile('background_image') && $request->file('background_image')->isValid()) {
                $hasChanges = true;
            }
    
            // Promocode Change Check
            $validPromocodeIds = [];
            if ($request->filled('promocode_codes')) {
                $promocodeIds = explode(',', $request->promocode_codes);
                foreach ($promocodeIds as $promoId) {
                    $promo = Promocode::find(trim($promoId));
                    if (!$promo) {
                        return response()->json(['status' => false, 'message' => "Invalid promocode ID: $promoId"], 400);
                    }
                    $validPromocodeIds[] = $promo->id;
                }
    
                // Compare promocodes
                $oldPromocodes = json_decode($ticket->promocode_ids ?? '[]', true);
                sort($oldPromocodes);
                sort($validPromocodeIds);
                if ($oldPromocodes !== $validPromocodeIds) {
                    $hasChanges = true;
                }
            } elseif ($ticket->promocode_ids !== null) {
                $hasChanges = true;
            }
    
            // Only proceed with updates if there are changes
            if ($hasChanges) {
                // Generate new batch ID only if there are changes
                $ticket->batch_id = 'TICKET-' . $ticket->event_id . '-' . strtoupper(uniqid());
    
                // Update basic fields
                $ticket->fill($newData);
    
                // Handle background image
                if ($request->hasFile('background_image') && $request->file('background_image')->isValid()) {
                    $file = $request->file('background_image');
                    $fileName = 'get-your-ticket-' . uniqid() . '-' . $file->getClientOriginalName();
                    $folder = 'uploads/Ticket/backgrounds';
    
                    if ($ticket->background_image) {
                        $oldImagePath = public_path(str_replace(url('/'), '', $ticket->background_image));
                        if (file_exists($oldImagePath)) {
                            unlink($oldImagePath);
                        }
                    }
    
                    $file->move(public_path($folder), $fileName);
                    $ticket->background_image = url($folder . '/' . $fileName);
                }
    
                // Update promocodes
                if (!empty($validPromocodeIds)) {
                    $ticket->promocode_ids = json_encode($validPromocodeIds);
                } else {
                    $ticket->promocode_ids = null;
                }
    
                $ticket->save();
    
                // Create history entry only if there are changes
                $history = new TicketHistory();
                $history->ticket_id = $ticket->id;
                $history->batch_id = $ticket->batch_id;
    
                foreach ($newData as $field => $newValue) {
                    if ($field !== 'access_area' && $originalTicket->$field != $newValue) {
                        $history->$field = $newValue;
                    }
                }
    
                if (isset($ticket->background_image) && $originalTicket->background_image !== $ticket->background_image) {
                    $history->background_image = $ticket->background_image;
                }
    
                if (!empty($validPromocodeIds)) {
                    $oldPromocodes = json_decode($originalTicket->promocode_ids ?? '[]', true);
                    sort($oldPromocodes);
                    sort($validPromocodeIds);
    
                    if ($oldPromocodes !== $validPromocodeIds) {
                        $history->promocode_ids = json_encode($validPromocodeIds);
                    }
                }
    
                $history->save();
            }
    
            $tickets = Ticket::where('event_id', $ticket->event_id)->get();
    
            return response()->json([
                'status' => true,
                'message' => $hasChanges ? 'Ticket Updated Successfully' : 'No changes were made to the ticket',
                'tickets' => $tickets
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to update Ticket: ' . $e->getMessage()
            ], 500);
        }
    }
    


    //store images
    private function storeFile($file, $folder, $disk = 'public')
    {
        $filename = uniqid() . '_' . $file->getClientOriginalName();
        $path = $file->storeAs('uploads/' . $folder, $filename, $disk);
        return Storage::disk($disk)->url($path);
    }



    public function destroy(string $id)
    {
        $Ticket = Ticket::where('id', $id)->firstOrFail();
        if (!$Ticket) {
            return response()->json(['status' => false, 'message' => 'Ticket not found'], 404);
        }

        $Ticket->delete();
        return response()->json(['status' => true, 'message' => 'Ticket deleted successfully'], 200);
    }
  
    public function userTicketInfo($user_id, $ticket_id)
    {
      
        $ticket = Ticket::find($ticket_id);

        if (!$ticket) {
            return response()->json([
                'status' => false,
                'message' => 'Ticket not found.'
            ], 404);
        }

        $bookingCount = Booking::where('user_id', $user_id)
            ->where('ticket_id', $ticket->id)
            ->count();

        if ($bookingCount >= $ticket->user_booking_limit) {
            return response()->json([
                'status' => false,
                'message' => 'Your booking limit has been reached.',
                'current_bookings' => $bookingCount,
                'limit' => $ticket->user_booking_limit
            ], 200); 
        }

        return response()->json([
            'status' => true,
            'message' => 'You can still book.',
            'current_bookings' => $bookingCount,
            'limit' => $ticket->user_booking_limit
        ], 200);
    }
}
