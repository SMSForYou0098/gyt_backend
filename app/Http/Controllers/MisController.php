<?php

namespace App\Http\Controllers;
use App\Models\Booking;
use App\Models\Promocode;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\MISReportExport;
use App\Models\Agent;
use Carbon\Carbon;
use Illuminate\Http\Request;

class MisController extends Controller
{
    // public function misData(Request $request)
    // {
    //     $date = $request->query('date'); // Format: Y-m-d

    //     if (!$date) {
    //         return response()->json(['error' => 'Date parameter is required.'], 422);
    //     }

    //     $bookings = Booking::whereDate('created_at', $date)
    //         ->whereNotNull('promocode_id')
    //         ->with(['ticket.event', 'promocode'])
    //         ->get();

    //     $report = [];

    //     foreach ($bookings as $booking) {
    //         $key = $booking->promocode_id . '-' . $booking->ticket->event->id;

    //         if (!isset($report[$key])) {
    //             $report[$key] = [
    //                 'Date' => $date,
    //                 'Event Name' => $booking->ticket->event->name,
    //                 'Promocode' => $booking->promocode_id ?? '',
    //                 'Ticket Name' => $booking->ticket->name,
    //                 'Total Bookings' => 0,
    //                 'Total Discount' => 0,
    //             ];
    //         }

    //         $report[$key]['Total Bookings'] += 1;
    //         $report[$key]['Total Discount'] += $booking->discount ?? 0;
    //     }

    //     $reportData = array_values($report);

    //     // For API response
    //     // if ($request->query('export') !== '1') {
    //         return response()->json(['data' => $reportData]);
    //     // }

    //     // For Excel export
    //     // return Excel::download(new MISReportExport($reportData), 'mis-report-' . $date . '.xlsx');
    // }

    // public function misData(Request $request)
    // {
    //     $date = $request->query('date'); // Format: Y-m-d
    
    //     if (!$date) {
    //         return response()->json(['error' => 'Date parameter is required.'], 422);
    //     }
    
    //     $query = Booking::whereDate('created_at', $date)->with(['ticket.event', 'promocode']);
    
    //     // Optional promocode filter — if only want bookings that used promocodes
    //     if ($request->query('only_promocode') == '1') {
    //         $query->whereNotNull('promocode_id');
    //     }
    
    //     $bookings = $query->get();
    
    //     $report = [];
    
    //     foreach ($bookings as $booking) {
    //         $key = ($booking->promocode_id ?? 'NA') . '-' . $booking->ticket->event->id;
    
    //         if (!isset($report[$key])) {
    //             $report[$key] = [
    //                 'Date' => $date,
    //                 'Event Name' => $booking->ticket->event->name,
    //                 'Promocode' => $booking->promocode_id ?? 'N/A',
    //                 'Ticket Name' => $booking->ticket->name,
    //                 'Total Bookings' => 0,
    //                 'Total Discount' => 0,
    //             ];
    //         }
    
    //         $report[$key]['Total Bookings'] += 1;
    //         $report[$key]['Total Discount'] += $booking->discount ?? 0;
    //     }
    
    //     $reportData = array_values($report);
    
    //     return response()->json(['data' => $reportData]);
    // }
    
    // public function misData(Request $request)
    // {
    //     $date = $request->query('date'); // Format: Y-m-d
    
    //     if (!$date) {
    //         return response()->json(['error' => 'Date parameter is required.'], 422);
    //     }
    
    //     $query = Booking::whereDate('created_at', $date)
    //         ->with(['ticket.event', 'promocode', 'user']); // Load user relation too
    
    //     // Optional promocode filter — if only want bookings that used promocodes
    //     if ($request->query('only_promocode') == '1') {
    //         $query->whereNotNull('promocode_id');
    //     }
    
    //     $bookings = $query->get();
    
    //     $report = [];
    
    //     foreach ($bookings as $booking) {
    //         $key = ($booking->promocode_id ?? 'NA') . '-' . $booking->ticket->event->id;
    
    //         if (!isset($report[$key])) {
    //             $report[$key] = [
    //                 'Date' => $date,
    //                 'Event Name' => $booking->ticket->event->name,
    //                 'Promocode' => $booking->promocode_id ?? 'N/A',
    //                 'Ticket Name' => $booking->ticket->name,
    //                 'Total Bookings' => 0,
    //                 'Total Discount' => 0,
    //                 'Users' => [], // New: user-wise data
    //             ];
    //         }
    
    //         $report[$key]['Total Bookings'] += 1;
    //         $report[$key]['Total Discount'] += $booking->discount ?? 0;
    
    //         // Add individual user booking details
    //         $report[$key]['Users'][] = [
    //             'User Name' => $booking->user->name ?? 'N/A',
    //             'User Email' => $booking->user->email ?? 'N/A',
    //             'Booking ID' => $booking->id,
    //             'Promocode' => $booking->promocode_id ?? 'N/A',
    //             'Discount' => $booking->discount ?? 0,
    //         ];
    //     }
    
    //     $reportData = array_values($report);
    //     if ($request->query('export') == '1') {
    //         return Excel::download(new MISReportExport($reportData), 'mis-report-' . $date . '.xlsx');
    //     }
    //     return response()->json(['data' => $reportData]);
    // }
    
    public function misData(Request $request)
    {
        $date = $request->query('date'); // Format: Y-m-d
    
        if (!$date) {
            return response()->json(['error' => 'Date parameter is required.'], 422);
        }
    
        $report = [];
    
        // ------------------------------------------
        // 1. ONLINE BOOKINGS
        // ------------------------------------------
        $onlineBookings = Booking::whereDate('created_at', $date)
            ->with(['ticket.event', 'promocode', 'user']);
    
        if ($request->query('only_promocode') == '1') {
            $onlineBookings->whereNotNull('promocode_id');
        }
    
        foreach ($onlineBookings->get() as $booking) {
            $key = 'Online-' . ($booking->promocode_id ?? 'NA') . '-' . $booking->ticket->event->id;
    
            if (!isset($report[$key])) {
                $report[$key] = [
                    'Type' => 'Online',
                    'Date' => $date,
                    'Event Name' => $booking->ticket->event->name,
                    'Ticket Name' => $booking->ticket->name,
                    'Ticket Price' => $booking->ticket->price,
                    'Promocode' => $booking->promocode_id ?? 'N/A',
                    'Agent Name' => 'N/A',
                    'Total Bookings' => 0,
                    'Cash' => 0,
                    'UPI' => 0,
                    'Net Banking' => 0,
                    'Total Discount' => 0,
                    'Users' => [],
                ];
            }
    
            $report[$key]['Total Bookings'] += 1;
            $report[$key]['Total Discount'] += $booking->discount ?? 0;
    
            $report[$key]['Users'][] = [
                'User Name' => $booking->user->name ?? 'N/A',
                'User Email' => $booking->user->email ?? 'N/A',
                'Booking ID' => $booking->id,
                'Discount' => $booking->discount ?? 0,
            ];
        }
    
        // ------------------------------------------
        // 2. OFFLINE BOOKINGS
        // ------------------------------------------
        $offlineBookings = Agent::whereDate('created_at', $date)
            ->with(['ticket.event', 'agent', 'user']);
    
        foreach ($offlineBookings->get() as $booking) {
            $key = 'Offline-' . $booking->ticket->event->id . '-' . $booking->agent->id;
    
            if (!isset($report[$key])) {
                $report[$key] = [
                    'Type' => 'Offline',
                    'Date' => $date,
                    'Event Name' => $booking->ticket->event->name,
                    'Ticket Name' => $booking->ticket->name,
                    'Ticket Price' => $booking->ticket->price,
                    'Promocode' => 'N/A',
                    'Agent Name' => $booking->agent->name ?? 'N/A',
                    'Total Bookings' => 0,
                    'Cash' => 0,
                    'UPI' => 0,
                    'Net Banking' => 0,
                    'Total Discount' => 0,
                    'Users' => [],
                   
                ];
            }
    
            $report[$key]['Total Bookings'] += 1;
            $report[$key]['Cash'] += $booking->cash_amount ?? 0;
            $report[$key]['UPI'] += $booking->upi_amount ?? 0;
            $report[$key]['Net Banking'] += $booking->netbanking_amount ?? 0;
            $report[$key]['Total Discount'] += $booking->discount ?? 0;
    
            $report[$key]['Users'][] = [
                'User Name' => $booking->user->name ?? 'N/A',
                'User Email' => $booking->user->email ?? 'N/A',
                'Booking ID' => $booking->id,
                'Discount' => $booking->discount ?? 0,
            ];
        }
    
        $reportData = array_values($report);
    
        // Export as Excel if requested
        if ($request->query('export') == '1') {
            return Excel::download(new MISReportExport($reportData), 'mis-report-' . $date . '.xlsx');
        }
    
        return response()->json(['data' => $reportData]);
    }
    
}


