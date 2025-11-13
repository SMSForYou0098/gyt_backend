<?php

use App\Events\ReportUpdated;
use App\Events\TestEvent;
use App\Http\Controllers\CashfreePaymentController;
use App\Models\Report;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::any('{any}', function () {
    // return redirect()->away('https://ssgarba.com');
    return redirect()->away('https://getyourticket.in');
})->where('any', '.*');

