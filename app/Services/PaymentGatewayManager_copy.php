<?php
namespace App\Services;

use App\Http\Controllers\CashfreeController;
use App\Http\Controllers\EasebuzzController;
use App\Http\Controllers\InstaMozoController;
use App\Http\Controllers\PhonePeController;
use App\Http\Controllers\RazorPayController;
use App\Models\CashfreeConfig;
use App\Models\EasebuzzConfig;
use App\Models\Instamojo;
use App\Models\PhonePe;
use App\Models\Razorpay;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentGatewayManager
{
    protected $gateways = [
        EasebuzzController::class => EasebuzzConfig::class,
        InstaMozoController::class => Instamojo::class,
        RazorPayController::class => Razorpay::class,
        PhonePeController::class => PhonePe::class,
        //CashfreeController::class => CashfreeConfig::class,
        // Add more as needed...
    ];

    public function getNextGateway(int $organizerId)
    {
        $controllerClasses = array_keys($this->gateways);
        $startIndex = Cache::get("current_gateway_index_{$organizerId}", 0);
        $total = count($controllerClasses);

        for ($i = 0; $i < $total; $i++) {
            $currentIndex = ($startIndex + $i) % $total;
            $controller = $controllerClasses[$currentIndex];
            $model = $this->gateways[$controller];

            DB::enableQueryLog();
            $config = $model::where('user_id', $organizerId)->where('status', 1)->first();
            $queries = DB::getQueryLog();
            $lastQuery = end($queries);
    
            // Log::info('getNextGateway:', ['data' =>$model, $config]);
            if ($config && $config->status) {
                // Found a valid gateway
                Cache::put("current_gateway_index_{$organizerId}", ($currentIndex + 1) % $total, now()->addMinutes(10));
                return $controller;
            }
        }

       

        return null; // No active gateways for this organizer
    }
}

