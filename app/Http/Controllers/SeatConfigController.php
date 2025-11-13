<?php

namespace App\Http\Controllers;

use App\Models\EventSeat;
use App\Models\SeatConfig;
use Illuminate\Http\Request;

class SeatConfigController extends Controller
{

    public function index($id)
    {
        $Details = SeatConfig::where('event_id', $id)->with('EventSeat')->first();

        if (!$Details) {
            return response()->json(['status' => false, 'message' => 'seatConfig not found'], 200);
        }
        return response()->json([
            'status' => true,
            'message' => 'seat config successfully',
            'configData' => $Details,
            'seats' => $Details->EventSeat,
        ], 200);
    }

    public function store(Request $request)
    {
        try {
            $configData = SeatConfig::updateOrCreate(
                ['event_id' => $request->input('event_id')], // Search condition
                [
                    'event_type' => $request->input('event_type'),
                    'ground_type' => $request->input('ground_type'),
                    'config' => json_encode($request->input('config')),
                ]
            );

            return response()->json([
                'status' => true,
                'message' => 'Seat config successfully updated or created',
                'configData' => $configData,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    public function storeEventSeat(Request $request)
    {
        try {
            $seatData = $request->seats;

            if (!is_array($seatData)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid seat data format. It should be an array.',
                ], 400);
            }

            if (!$request->has('event_id')) {
                return response()->json([
                    'status' => false,
                    'message' => 'event_id is required.',
                ], 400);
            }

            $storedSeats = [];

            foreach ($seatData as $seat) {
                if (!isset($seat['seat_id'], $seat['category'], $seat['disabled'])) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Missing required seat fields.',
                    ], 400);
                }

                $storedSeats[] = EventSeat::create([
                    'seat_id' => $seat['seat_id'],
                    'category' => $seat['category'],
                    'event_id' => $request->event_id,
                    'config_id' => $request->config_id,
                    'disabled' => $seat['disabled'],
                    'status' => 0,
                ]);
            }

            return response()->json([
                'status' => true,
                'message' => 'EventSeat successfully created',
                'eventSeat' => $storedSeats,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }


}
