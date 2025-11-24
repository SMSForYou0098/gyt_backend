<?php

use App\Http\Controllers\AccessAreaController;
use App\Http\Controllers\AccreditationBookingController;
use App\Http\Controllers\ActressController;
use App\Http\Controllers\AgentController;
use App\Http\Controllers\AmusementAgentBookingController;
use App\Http\Controllers\AmusementBookingController;
use App\Http\Controllers\AmusementPosController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\ArtistController;
use App\Http\Controllers\AttndyController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BalanceController;
use App\Http\Controllers\BannerController;
use App\Http\Controllers\BlogCommentController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\CashfreeController;
use App\Http\Controllers\CashfreePaymentController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CommissionController;
use App\Http\Controllers\ComplimentaryBookingController;
use App\Http\Controllers\ContactUsController;
use App\Http\Controllers\CorporateBookingController;
use App\Http\Controllers\CorporateUserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EasebuzzController;
use App\Http\Controllers\EmailTemplateController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\EventGetController;
use App\Http\Controllers\ExhibitionBookingController;
use App\Http\Controllers\FaqController;
use App\Http\Controllers\FooterGrouController;
use App\Http\Controllers\FooterMenuController;
use App\Http\Controllers\HighlightEventController;
use App\Http\Controllers\InstaMozoController;
use App\Http\Controllers\LiveUserController;
use App\Http\Controllers\LoginHistoryController;
use App\Http\Controllers\LRowController;
use App\Http\Controllers\LSeatController;
use App\Http\Controllers\LSectionController;
use App\Http\Controllers\LTiersController;
use App\Http\Controllers\LVenueController;
use App\Http\Controllers\LZoneController;
use App\Http\Controllers\MailController;
use App\Http\Controllers\MenuGroupController;
use App\Http\Controllers\MisController;
use App\Http\Controllers\NavigationMenuController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrganizationTypeController;
use App\Http\Controllers\PagesController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaymentGatewayController;
use App\Http\Controllers\PopUpController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\PromoCodeController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RolePermissionController;
use App\Http\Controllers\ScanController;
use App\Http\Controllers\SeatConfigController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\SmsController;
use App\Http\Controllers\SocialMediaController;
use App\Http\Controllers\SponsorBookingController;
use App\Http\Controllers\SystemVariableController;
use App\Http\Controllers\TaxController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WhatsappConfigurationsController;
use App\Http\Controllers\SuccessfulEventController;
use App\Http\Controllers\UserInfoController;
use App\Http\Controllers\UserTicketController;
use App\Http\Controllers\PhonePeController;
use App\Http\Controllers\QueryController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;


Route::get('/clear', function () {
    Artisan::call('storage:link');
    Artisan::call('cache:clear');
    Artisan::call('optimize:clear');

    return 'hello';
});

Route::post('/payment/phonepe/initiate', [PhonePeController::class, 'initiatePayment'])->name('phonepe.initiate');
Route::post('/payment/phonepe/callback', [PhonePeController::class, 'callback'])->name('phonepe.callback');

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

//,'device.info'
Route::post('/payment-webhook/{gateway}/vod', [EasebuzzController::class, 'handleWebhook']);
Route::any('/payment-response/{gateway}/{id}/{session_id}', [EasebuzzController::class, 'handlePaymentResponse'])->middleware('restrict.payment');


Route::middleware(['restrict.ip'])->group(function () {
    // auth routes
    Route::post('verify-user', [AuthController::class, 'verifyUser']);
    Route::post('login', [AuthController::class, 'verifyUserRequest']);
    // Route::post('login', [AuthController::class, 'verifyOTP']);
    Route::post('register', [AuthController::class, 'register']);
    Route::post('verify-Password', [AuthController::class, 'verifyPassword']);


    // Route::post('/verify-otp', [AuthController::class, 'verifyOTP']);

    Route::get('/settings', [SettingController::class, 'index']);

    Route::get('/getAllData', [DashboardController::class, 'getAllData']);


    // role routes
    Route::get('events', [EventController::class, 'index']);
    Route::get('events-days/{day}', [EventController::class, 'dayWiseEvents']);
    Route::get('events-whatsapp', [EventController::class, 'eventWhatsapp']);
    Route::get('feature-event', [EventController::class, 'FeatureEvent']);
    Route::post('create-user', [UserController::class, 'create']);
    Route::get('event-detail/{id}', [EventController::class, 'edit']);
    Route::get('event-detail-whatsapp/{id}', [EventController::class, 'editWhatsapp']);
    Route::post('/send-email/{id}', [EmailTemplateController::class, 'send']);
    Route::get('/banners', [SettingController::class, 'getBanners']);
    Route::get('banner-list/{type}', [BannerController::class, 'index']);
    Route::get('highlightEvent-list', [HighlightEventController::class, 'index']);
    Route::post('store-device', [UserInfoController::class, 'storeDeviceInfo']);
    Route::get('user-devices/count', [UserInfoController::class, 'countUserDevices']);
    Route::get('live-user', [UserInfoController::class, 'liveData']);
    Route::get('delete-device-info', [UserInfoController::class, 'deleteDeviceInfo']);
    Route::get('wc-mdl-list', [PopUpController::class, 'index']);
    Route::get('/past-events', [EventController::class, 'pastEvents']);
    Route::get('blog-status', [BlogController::class, 'statusData']);
    Route::get('blog-show/{id}', [BlogController::class, 'show']);
    Route::get('related-blogs/{id}', [BlogController::class, 'cetegoryData']);
    Route::get('blog-comment-show/{blog_id}', [BlogCommentController::class, 'show']);
    Route::get('gan-card/{order_id}', [AgentController::class, 'ganerateCard']);
    Route::get('generate-token/{order_id}', [AgentController::class, 'generate']);
    Route::get('attendees-chek-in/{orderId}', [ScanController::class, 'attendeesChekIn']);
    Route::post('attendees-verify/{orderId}', [ScanController::class, 'attendeesVerify']);
    Route::get('tickets/{id}', [TicketController::class, 'index']);
    Route::get('org-events/{organisation}', [EventController::class, 'landingOrgId']);

    Route::middleware(['auth:api'])->group(function () {
        Route::get('verify-user-settion', [AuthController::class, 'verifyUserSession']);
        //Dashboard routes
        Route::get('/bookingCount/{id}', [DashboardController::class, 'BookingCounts']);
        Route::get('/calculateSale/{id}', [DashboardController::class, 'calculateSale']);
        Route::get('org/dashbord', [DashboardController::class, 'organizerWeeklyReport']);
        Route::get('/getDashboardSummary/{type}', [DashboardController::class, 'getDashboardSummary']);
        Route::get('/getDashboardOrgTicket', [DashboardController::class, 'getDashboardOrgTicket']);
        Route::get('organizer/summary', [DashboardController::class, 'organizerTotals']);
        Route::get('/payment-log', [DashboardController::class, 'getPaymentLog']);
        Route::delete('/flush-payment-log', [DashboardController::class, 'PaymentLogDelet']);

        Route::get('login-history', [LoginHistoryController::class, 'index'])->middleware('permission:View Login History');
        Route::get('/gateway-wise-sales', [DashboardController::class, 'gatewayWiseSales']);
        // Route::get('/getAllData/{id}', [DashboardController::class, 'getAllData']);

        Route::post('/create-role', [RolePermissionController::class, 'createRole'])->middleware('permission:Create Role');
        Route::get('/role-list', [RolePermissionController::class, 'getRoles']);
        // Route::get('/role-list', [RolePermissionController::class, 'getRoles'])->middleware('permission:View Role');
        // ;

        Route::get('/role-edit/{id}', [RolePermissionController::class, 'EditRole'])->middleware('permission:Edit Role');
        Route::post('/role-update', [RolePermissionController::class, 'UpdateRole']);
        // permisson
        Route::post('/create-permission', [RolePermissionController::class, 'createPermission']);
        Route::get('/permission-list', [RolePermissionController::class, 'getPermissions']);
        // });
        Route::get('/permission-edit/{id}', [RolePermissionController::class, 'EditPermission']);
        Route::post('/permission-update', [RolePermissionController::class, 'UpdatePermission']);

        // role permission
        Route::get('/role-permission/{id}', [RolePermissionController::class, 'getRolePermissions'])->middleware('permission:View Permission');
        Route::post('/role-permission/{id}', [RolePermissionController::class, 'giveRolePermissions']);
        // role permission
        Route::get('/user-permission/{id}', [RolePermissionController::class, 'getUserPermissions']);
        Route::post('/user-permission/{id}', [RolePermissionController::class, 'giveUserPermissions']);


        //user route
        Route::get('users', [UserController::class, 'index'])->middleware('permission:View User');
        Route::get('users/list', [UserController::class, 'indexlist']);
        Route::get('users-by-role/{role}', [UserController::class, 'getUsersByRole']);
        Route::delete('user-delete/{id}', [UserController::class, 'destroy'])
            ->middleware('permission:Delete User');

        Route::post('chek-email', [UserController::class, 'checkEmail']);
        Route::post('chek-number-email', [UserController::class, 'checkMobile']);

        Route::get('low-credit-users/{id}', [UserController::class, 'lowBalanceUser']);
        Route::get('edit-user/{id}', [UserController::class, 'edit']);
        Route::get('chek-user/{id}', [UserController::class, 'CheckValidUser']);
        Route::post('chek-password', [UserController::class, 'checkPassword']);
        Route::post('update-security', [UserController::class, 'UpdateUserSecurity']);
        Route::post('update-user/{id}', [UserController::class, 'update']);
        Route::post('update-user-alert/{id}', [UserController::class, 'updateAlerts']);
        Route::get('scanner-token-length/{id}', [UserController::class, 'getQrLength']);

        Route::post('create-bulk-user', [UserController::class, 'createBulkUsers']);

        //org aggreement pdf
        // Route::get('org-agreement-pdf/{id}', [UserController::class, 'orgAgreementPdf']);
        // Route::get('agreement/view/{id}', [UserController::class, 'viewAgreement'])->name('agreement.view');
        Route::get('agreement/download/{id}', [UserController::class, 'downloadAgreement'])->name('agreement.download');
        //event-ticket
        Route::get('event-ticket/{event_id}', [UserController::class, 'eventTicket']);

        // passwrord change after login
        Route::post('update-password/{id}', [AuthController::class, 'changePassword']);


        Route::get('/logs', [UserController::class, 'logs']);
        Route::delete('/clear-logs', [UserController::class, 'destroyLogs']);

        //mail templates
        Route::get('/email-templates/{id}', [EmailTemplateController::class, 'index']);
        Route::post('/store-templates', [EmailTemplateController::class, 'store']);
        Route::post('/update-templates', [EmailTemplateController::class, 'update']);

        // Route::post('/send-balance-alert-email/{id}', [EmailTemplateController::class, 'send'])->middleware(['auth:api']);

        //eventroutes
        Route::get('pos-events/{id}', [EventController::class, 'eventByUser']);
        Route::get('event-list/{id}', [EventController::class, 'eventList'])->middleware('permission:View Event');
        Route::get('event-ticket-info/{id}', [EventController::class, 'info']);
        Route::post('create-event', [EventController::class, 'create']);
        Route::post('update-event/{id}', [EventController::class, 'update'])->middleware('permission:Edit Event');
        Route::delete('delete-event/{id}', [EventController::class, 'destroy'])->middleware('permission:Delete Event');
        Route::delete('junk-event/{id}', [EventController::class, 'junk']);
        Route::get('org-event/{id}', [EventController::class, 'eventData']);
        Route::get('events/attendee', [EventController::class, 'allEventData']);
        Route::get('/layout/{eventKey}', [EventController::class, 'getLayoutByEventId']);

        // Route::get('tickets/{id}', [TicketController::class, 'index']);
        Route::post('create-ticket/{id}', [TicketController::class, 'create']);
        Route::post('update-ticket/{id}', [TicketController::class, 'update']);
        Route::delete('ticket-delete/{id}', [TicketController::class, 'destroy']);

        //booking
        // Route::post('book-ticket/{id}', [BookingController::class, 'store']);
        // Route::post('master-booking/{id}', [BookingController::class, 'master']);
        // Route::post('update-ticket/{id}', [TicketController::class, 'update']);
        Route::get('ticket-info/{id}', [TicketController::class, 'info']);
        Route::get('user-ticket-info/{user_id}/{ticket_id}', [TicketController::class, 'userTicketInfo']);


        Route::get('bookings/{id}', [BookingController::class, 'index']);
        Route::get('master-bookings/{id}', [BookingController::class, 'AdminBookings'])->middleware('permission:View Total Bookings');;
        Route::post('booking-mail/{id}', [BookingController::class, 'sendBookingMail']);
        Route::get('agent-bookings/{id}', [BookingController::class, 'agentBooking']);
        Route::get('sponsor-bookings/{id}', [BookingController::class, 'sponsorBooking']);
        Route::get('accreditation-bookings/{id}', [BookingController::class, 'accreditationBooking']);

        //scan routes
        Route::post('verify-ticket/{orderId}', [ScanController::class, 'verifyTicket']);
        Route::get('chek-in/{orderId}', [ScanController::class, 'ChekIn']);
        Route::get('scan-histories', [ScanController::class, 'getScanHistories'])->middleware('permission:Scan History');


        Route::post('pendding-booking/{id}', [BookingController::class, 'penddingBooking']);

        //amusement
        Route::get('amusement-bookings/{id}', [AmusementBookingController::class, 'onlineBookings']);

        //amusement agent booking
        // Route::get('amusement-agents-bookings/{id}', [AmusementAgentBookingController::class, 'agentAmusementBooking']);
        Route::post('amusement-agent-book-ticket/{id}', [AmusementAgentBookingController::class, 'store']);
        Route::post('amusement-agent-master-booking/{id}', [AmusementAgentBookingController::class, 'agentAmusementMaster']);
        Route::get('amusement-agents-bookings/{id}', [AmusementAgentBookingController::class, 'list']);
        Route::get('amusement-user-form-number/{id}', [AmusementAgentBookingController::class, 'userFormNumberAmusement']);

        //Agent booking
        Route::post('agent-book-ticket/{id}', [AgentController::class, 'store']);
        Route::post('agent-master-booking/{id}', [AgentController::class, 'agentMaster']);
        Route::get('/agents/list/{id}', [AgentController::class, 'list'])->middleware('permission:View Agent Bookings');
        Route::get('user-form-number/{id}', [AgentController::class, 'userFormNumber']);
        Route::get('agent-restore-booking/{token}', [AgentController::class, 'restoreBooking']);
        Route::delete('agent-delete-booking/{token}', [AgentController::class, 'destroy']);

        //sponsor booking
        Route::post('sponsor-book-ticket/{id}', [SponsorBookingController::class, 'store']);
        Route::post('sponsor-master-booking/{id}', [SponsorBookingController::class, 'sponsorMaster']);
        Route::get('/sponsor/list/{id}', [SponsorBookingController::class, 'list'])->middleware('permission:View Sponsor Bookings');
        // Route::get('user-form-number/{id}', [SponsorBookingController::class, 'userFormNumber']);
        Route::get('sponsor-restore-booking/{token}', [SponsorBookingController::class, 'restoreBooking']);
        Route::delete('sponsor-delete-booking/{token}', [SponsorBookingController::class, 'destroy']);

        //Accreditation booking
        Route::post('accreditation-book-ticket/{id}', [AccreditationBookingController::class, 'store']);
        Route::post('accreditation-master-booking/{id}', [AccreditationBookingController::class, 'AccreditationMaster']);
        Route::get('/accreditation/list/{id}', [AccreditationBookingController::class, 'list'])->middleware('permission:View Accreditation Bookings');
        // Route::get('accreditation-user-form-number/{id}', [AccreditationBookingController::class, 'userFormNumber']);
        Route::get('accreditation-restore-booking/{token}', [AccreditationBookingController::class, 'restoreBooking']);
        Route::delete('accreditation-delete-booking/{token}', [AccreditationBookingController::class, 'destroy']);

        //pos
        Route::post('book-pos/{id}', [PosController::class, 'create']);
        Route::get('pos-bookings/{id}', [PosController::class, 'index'])->middleware('permission:View POS Bookings');
        Route::delete('delete-pos-booking/{id}', [PosController::class, 'destroy']);
        Route::get('restore-pos-booking/{id}', [PosController::class, 'restoreBooking']);
        Route::get('pos/ex-user/{number}', [PosController::class, 'posDataByNumber']);

        //Amusement Pos
        Route::post('amusementBook-pos/{id}', [AmusementPosController::class, 'create']);
        Route::get('amusement-pos-bookings/{id}', [AmusementPosController::class, 'index']);
        Route::delete('amusement-delete-pos-booking/{id}', [AmusementPosController::class, 'destroy']);
        Route::get('amusement-restore-pos-booking/{id}', [AmusementPosController::class, 'restoreBooking']);

        //ExhibitionBooking
        Route::post('book-exhibition/{id}', [ExhibitionBookingController::class, 'create']);
        Route::get('exhibition-bookings/{id}', [ExhibitionBookingController::class, 'index'])->middleware('permission:View Exhibition Bookings');
        Route::delete('exihibition/delete-booking/{token}', [ExhibitionBookingController::class, 'destroy']);
        Route::get('exihibition/restore-booking/{token}', [ExhibitionBookingController::class, 'restoreBooking']);

        //balance routes
        Route::get('balance-history/{id}', [BalanceController::class, 'index']);
        Route::post('add-balance', [BalanceController::class, 'create']);
        Route::get('chek-user/{id}', [BalanceController::class, 'CheckValidUser']);
        Route::post('wallet-user/{id}', [BalanceController::class, 'walletUser']);
        Route::post('debit-wallet', [BalanceController::class, 'processTransaction']);
        Route::get('user-transactions/{id}', [BalanceController::class, 'allBalance']);
        Route::get('transactions-summary/{id}', [BalanceController::class, 'transactionsOverView']);
        Route::get('shopKeeper-dashbord/{id}', [BalanceController::class, 'shopKeeperDashbord']);
        Route::get('transactions-data/{id}', [BalanceController::class, 'walletData']);


        Route::delete('delete-booking/{id}/{token}', [BookingController::class, 'destroy']);
        Route::get('restore-booking/{id}/{token}', [BookingController::class, 'restoreBooking']);
        Route::get('user-bookings/{userId}', [BookingController::class, 'getUserBookings']);

        //regect booking list
        Route::get('pendding-booking/list/{id}', [BookingController::class, 'penddingBookingList']);
        Route::post('booking-confirm/{id}', [BookingController::class, 'penddingBookingConform']);

        // alerts route
        Route::get('send-mail', [MailController::class, 'send']);
        Route::get('email-config', [MailController::class, 'index'])->middleware('permission:View Mail Config Setting');
        Route::post('email-config', [MailController::class, 'store']);


        //setting
        Route::post('/setting', [SettingController::class, 'store']);
        Route::post('/banners', [SettingController::class, 'storeBanner']);
        Route::put('settings/live-user/{id}', [SettingController::class, 'updateLiveUser']);
        Route::post('/sponsorsImages', [SettingController::class, 'sponsorsImages']);
        Route::post('/pcSponsorsImages', [SettingController::class, 'pcSponsorsImages']);
        // Route::get('/getSponsorsImages', [SettingController::class, 'getSponsorsImages']);
        // Route::get('/getPcSponsorsImages', [SettingController::class, 'getPcSponsorsImages']);

        //banner
        Route::post('banner-store/{type}', [BannerController::class, 'store']);
        Route::post('banner-update/{type}/{id}', [BannerController::class, 'update']);
        Route::get('banner-show/{id}', [BannerController::class, 'show']);
        Route::delete('banner-destroy/{id}', [BannerController::class, 'destroy']);
        Route::post('/rearrange-banner/{type}', [BannerController::class, 'rearrangeBanner']);

        //highlight event
        Route::post('highlightEvent-store', [HighlightEventController::class, 'store']);
        Route::post('highlightEvent-update/{id}', [HighlightEventController::class, 'update']);
        Route::get('highlightEvent-show/{id}', [HighlightEventController::class, 'show']);
        Route::delete('highlightEvent-destroy/{id}', [HighlightEventController::class, 'destroy']);
        Route::post('/rearrange-highlightEvent', [HighlightEventController::class, 'rearrangeHighlightEvent']);

        //SMS
        Route::get('/sms-api/{id}', [SmsController::class, 'index'])->middleware('permission:View SMS Config Setting');
        Route::post('/store-api', [SmsController::class, 'DefaultApi']);
        Route::post('/store-custom-api/{id}', [SmsController::class, 'CustomApi']);
        Route::post('/sms-template/{id}', [SmsController::class, 'store']);
        Route::post('/sms-template-update/{id}', [SmsController::class, 'update']);
        Route::delete('/sms-template-delete/{id}', [SmsController::class, 'destroy']);
        Route::post('/send-sms', [SmsController::class, 'sendSms']);

        //gateways
        Route::get('/payment-gateways/{user_id}', [PaymentGatewayController::class, 'getPaymentGateways'])->middleware('permission:View Payment Config Setting');
        Route::post('/store-razorpay', [PaymentGatewayController::class, 'storeRazorpay']);
        Route::post('/store-instamojo', [PaymentGatewayController::class, 'storeInstamojo']);
        Route::post('/store-easebuzz', [PaymentGatewayController::class, 'storeEasebuzz']);
        Route::post('/store-paytm', [PaymentGatewayController::class, 'storePaytm']);
        Route::post('/store-stripe', [PaymentGatewayController::class, 'storeStripe']);
        Route::post('/store-paypal', [PaymentGatewayController::class, 'storePayPal']);
        Route::post('/store-phonepe', [PaymentGatewayController::class, 'storePhonePe']);
        Route::post('/store-cashfree', [PaymentGatewayController::class, 'storeCashfree']);
        Route::post('/test', [PaymentGatewayController::class, 'initiatePayment']);

        //complimentary
        // Route to store new complimentary bookings
        Route::get('/complimentary-bookings/{id}', [ComplimentaryBookingController::class, 'index'])->middleware('permission:View Complimentary Booking');
        Route::post('/complimentary-booking-store', [ComplimentaryBookingController::class, 'storeData']);
        Route::post('/complimentary-booking', [ComplimentaryBookingController::class, 'store']);
        Route::post('/fetch-batch-cb/{id}', [ComplimentaryBookingController::class, 'getTokensByBatchId']);
        Route::get('/complimatory/restore-booking/{id}', [ComplimentaryBookingController::class, 'restoreComplimentaryBooking']);
        Route::delete('/complimatory/delete-booking/{id}', [ComplimentaryBookingController::class, 'destroy']);

        // Route for storing tax records
        Route::post('/taxes', [TaxController::class, 'store']);
        Route::get('/taxes/{id}', [TaxController::class, 'index']);

        Route::post('/commissions-store', [CommissionController::class, 'store']);
        Route::get('/commissions/{id}', [CommissionController::class, 'index']);


        // reports route
        Route::get('/agent-report', [ReportController::class, 'AgentReport'])->middleware('permission:View Agent Reports');
        Route::get('/sponsor-report', [ReportController::class, 'SponsorReport']);
        Route::get('/accreditation-report', [ReportController::class, 'AccreditationReport']);
        Route::get('/event-reports/{id}', [ReportController::class, 'EventReport'])->middleware('permission:View Event Reports');
        Route::get('/pos-report', [ReportController::class, 'PosReport'])->middleware('permission:View POS Reports');
        // Route::get('/organizer-report', [ReportController::class, 'OrganizerReport']);

        //comman org report
        Route::get('/org-list-report', [ReportController::class, 'orgListReport']);
        Route::get('/organizer-events-report', [ReportController::class, 'organizerEventsReport']);


        //promocode
        Route::get('promo-list/{id}', [PromoCodeController::class, 'list']);
        Route::post('promo-store', [PromoCodeController::class, 'store']);
        Route::get('promo-show/{id}', [PromoCodeController::class, 'show']);
        Route::put('promo-update', [PromoCodeController::class, 'update']);
        Route::delete('promo-destroy/{id}', [PromoCodeController::class, 'destroy']);
        Route::post('check-promo-code/{id}', [PromoCodeController::class, 'checkPromoCode']);

        //pages
        Route::get('pages-list', [PagesController::class, 'index']);
        Route::post('pages-store', [PagesController::class, 'store']);
        Route::post('pages-update/{id}', [PagesController::class, 'update']);
        Route::delete('pages-destroy/{id}', [PagesController::class, 'destroy']);
        Route::get('pages-show/{id}', [PagesController::class, 'show']);

        //blog
        Route::get('blog-list', [BlogController::class, 'index']);
        Route::get('blog-dashbord', [BlogController::class, 'deshbordData']);
        Route::post('blog-store', [BlogController::class, 'store']);
        Route::post('blog-update/{id}', [BlogController::class, 'update']);
        Route::delete('blog-destroy/{id}', [BlogController::class, 'destroy']);
        Route::get('top-viewed-blogs', [BlogController::class, 'topViewedBlogs']);
        Route::get('dashboard/chart-data', [BlogController::class, 'chartStats']);
        Route::get('most-used-category', [BlogController::class, 'getMostUsedCategory']);


        //BlogComment
        Route::get('blog-comment-list', [BlogCommentController::class, 'index']);
        Route::post('blog-comment-store/{blog_id}', [BlogCommentController::class, 'store']);
        Route::post('blog-comment-update/{id}', [BlogCommentController::class, 'update']);
        Route::delete('blog-comment-destroy/{id}', [BlogCommentController::class, 'destroy']);
        Route::post('blog-comments/{id}/like', [BlogCommentController::class, 'toggleLike']);
        Route::get('most-liked-comment-blog', [BlogCommentController::class, 'mostLikedCommentWithBlog']);

        //payment
        Route::post('/initiate-payment', [PaymentController::class, 'processPayment']);
        // Route::post('/initiate-payment', [EasebuzzController::class, 'initiatePayment']);
        Route::post('/verify-booking', [EasebuzzController::class, 'verifyBooking']);
        Route::post('/verify-amusement-booking', [EasebuzzController::class, 'verifyAmusementBooking']);

        //seatConfig
        Route::get('seat-config/{id}', [SeatConfigController::class, 'index']);
        Route::post('seat-config-store', [SeatConfigController::class, 'store']);
        Route::post('event-seat-store', [SeatConfigController::class, 'storeEventSeat']);

        //Actress
        Route::get('artist-list', [ArtistController::class, 'index']);
        Route::post('artist-store', [ArtistController::class, 'store']);
        Route::post('artist-update/{id}', [ArtistController::class, 'update']);
        Route::get('artist-show/{id}', [ArtistController::class, 'show']);
        Route::delete('artist-destroy/{id}', [ArtistController::class, 'destroy']);

        //Shop
        Route::get('shop-list', [ShopController::class, 'index']);
        Route::post('shop-store', [ShopController::class, 'store']);
        Route::post('shop-update/{id}', [ShopController::class, 'update']);
        Route::get('shop-show/{id}', [ShopController::class, 'show']);
        Route::delete('shop-destroy/{id}', [ShopController::class, 'destroy']);

        // system veriable
        Route::get('system-variables', [SystemVariableController::class, 'index']);
        Route::post('system-variables-store', [SystemVariableController::class, 'store']);
        Route::post('system-variables-update/{id}', [SystemVariableController::class, 'update']);
        Route::delete('system-variables-destroy/{id}', [SystemVariableController::class, 'destroy']);

        //popup
        Route::post('wc-mdl-store', [PopUpController::class, 'store']);
        Route::post('wc-mdl-update/{id}', [PopUpController::class, 'update']);
        Route::get('wc-mdl-show/{id}', [PopUpController::class, 'show']);
        Route::delete('wc-mdl-destroy/{id}', [PopUpController::class, 'destroy']);

        //event gets
        Route::get('event-gate-list/{event_id}', [EventGetController::class, 'index']);
        Route::post('event-gate-store', [EventGetController::class, 'store']);
        Route::post('event-gate-update/{id}', [EventGetController::class, 'update']);
        Route::get('event-gate-show/{id}', [EventGetController::class, 'show']);
        Route::delete('event-gate-destroy/{id}', [EventGetController::class, 'destroy']);

        //event gets
        Route::get('accessarea-list/{event_id}', [AccessAreaController::class, 'index']);
        Route::post('accessarea-store', [AccessAreaController::class, 'store']);
        Route::post('accessarea-update/{id}', [AccessAreaController::class, 'update']);
        Route::get('accessarea-show/{id}', [AccessAreaController::class, 'show']);
        Route::delete('accessarea-destroy/{id}', [AccessAreaController::class, 'destroy']);

        //user ticket
        Route::get('user-ticket-list/{user_id}', [UserTicketController::class, 'index']);
        Route::post('ticket-transfer', [UserTicketController::class, 'ticketTransfer']);

        //notifictino
        Route::post('/send-to-token', [NotificationController::class, 'sendToToken']);

        //layout venues
        Route::get('venue-list', [LVenueController::class, 'index']);
        Route::post('venue-store', [LVenueController::class, 'store']);
        Route::post('venue-update/{id}', [LVenueController::class, 'update']);
        Route::get('venue-show/{id}', [LVenueController::class, 'show']);
        Route::get('venue-byUser', [LVenueController::class, 'venueByUser']);
        Route::delete('venue-destroy/{id}', [LVenueController::class, 'destroy']);

        Route::get('zone-list', [LZoneController::class, 'index']);
        Route::post('zone-store', [LZoneController::class, 'store']);
        Route::post('zone-update/{id}', [LZoneController::class, 'update']);
        Route::get('zone-show/{id}', [LZoneController::class, 'show']);
        Route::delete('zone-destroy/{id}', [LZoneController::class, 'destroy']);

        Route::get('tier-list', [LTiersController::class, 'index']);
        Route::post('tier-store', [LTiersController::class, 'store']);
        Route::post('tier-update/{id}', [LTiersController::class, 'update']);
        Route::get('tier-show/{id}', [LTiersController::class, 'show']);
        Route::delete('tier-destroy/{id}', [LTiersController::class, 'destroy']);

        Route::get('section-list', [LSectionController::class, 'index']);
        Route::post('section-store', [LSectionController::class, 'store']);
        Route::post('section-update/{id}', [LSectionController::class, 'update']);
        Route::get('section-show/{id}', [LSectionController::class, 'show']);
        Route::delete('section-destroy/{id}', [LSectionController::class, 'destroy']);

        Route::get('row-list', [LRowController::class, 'index']);
        Route::post('row-store', [LRowController::class, 'store']);
        Route::post('row-update/{id}', [LRowController::class, 'update']);
        Route::get('row-show/{id}', [LRowController::class, 'show']);
        Route::delete('row-destroy/{id}', [LRowController::class, 'destroy']);

        Route::get('seat-list', [LSeatController::class, 'index']);
        Route::post('seat-store', [LSeatController::class, 'store']);
        Route::post('seat-update/{id}', [LSeatController::class, 'update']);
        Route::get('seat-show/{id}', [LSeatController::class, 'show']);
        Route::delete('seat-destroy/{id}', [LSeatController::class, 'destroy']);


        //corporate  
        Route::post('/corporate-user-store', [CorporateUserController::class, 'corporateUserStore']);
        Route::post('/corporateUser/update/{id}', [CorporateUserController::class, 'corporateUserUpdate']);
        Route::get('/corporate-attendee/{userId}/{category_id}', [CorporateUserController::class, 'corporateUserAttendy']);

        Route::post('corporate-pos/{id}', [CorporateBookingController::class, 'create']);
        Route::get('corporate-bookings/{id}', [CorporateBookingController::class, 'index'])->middleware('permission:View Corporate Bookings');
        Route::delete('delete-corporate-booking/{id}', [CorporateBookingController::class, 'destroy']);
        Route::get('restore-corporate-booking/{id}', [CorporateBookingController::class, 'restoreBooking']);
        Route::get('corporate/ex-user/{number}', [CorporateBookingController::class, 'corporateDataByNumber']);

        // org list
        Route::get('organizers', [UserController::class, 'organizerList'])->middleware('permission:View Organizers');
        Route::post('impersonate', [UserController::class, 'oneClickLogin']);
        Route::post('revert-impersonation', [UserController::class, 'revertImpersonation']);

        // event notification
        Route::post('send-notifications', [NotificationController::class, 'sendNotification']);

        //export
        Route::post('/export-users', [UserController::class, 'export'])->middleware('permission:Export Users');
        Route::post('/export-events', [EventController::class, 'export'])->middleware('permission:Export Events');
        Route::get('/export-promocode', [PromoCodeController::class, 'export']);
        Route::post('/export-onlineBooking', [BookingController::class, 'export'])->middleware('permission:Export Online Bookings');
        Route::post('/export-attndy/{event_id}', [AttndyController::class, 'export'])->middleware('permission:Export Attendees');
        Route::post('/export-agentBooking', [AgentController::class, 'export'])->middleware('permission:Export Agent Bookings');
        Route::post('/export-accreditationBooking', [AccreditationBookingController::class, 'export']);
        Route::post('/export-sponsorBooking', [SponsorBookingController::class, 'export'])->middleware('permission:Export Sponsor Bookings');
        Route::post('/export-posBooking', [PosController::class, 'export'])->middleware('permission:Export POS Bookings');
        Route::post('/export-corporateBooking', [CorporateBookingController::class, 'export']);
        Route::post('/export-complimentaryBooking', [ComplimentaryBookingController::class, 'export']);
        Route::get('/export-eventReport', [ReportController::class, 'exportEventReport']);
        Route::get('/export-AgentReport', [ReportController::class, 'exportAgentReport']);
        Route::get('/export-PosReport', [ReportController::class, 'exportPosReport']);
    });

    //import zip file
    Route::post('/import-zip', [ImportController::class, 'importZip']);
    Route::get('/merge-profile-photos', [ImportController::class, 'mergeProfilePhotos']);
    Route::get('/copy-original-profile-photos', [ImportController::class, 'copyOriginalProfilePhotos']);

    //ContactUs and  query
    Route::get('contac-list', [ContactUsController::class, 'index']);
    Route::post('contac-store', [ContactUsController::class, 'store']);
    Route::post('contac-update/{id}', [ContactUsController::class, 'update']);
    Route::get('contac-show/{id}', [ContactUsController::class, 'show']);
    Route::delete('contac-destroy/{id}', [ContactUsController::class, 'destroy']);

    //query
    Route::get('query-list', [QueryController::class, 'index']);
    Route::post('query-store', [QueryController::class, 'store']);
    Route::post('query-update/{id}', [QueryController::class, 'update']);
    Route::get('query-show/{id}', [QueryController::class, 'show']);
    Route::delete('query-destroy/{id}', [QueryController::class, 'destroy']);

    //faq
    Route::get('faq-list', [FaqController::class, 'index']);
    Route::post('faq-store', [FaqController::class, 'store']);
    Route::post('faq-update/{id}', [FaqController::class, 'update']);
    Route::get('faq-show/{id}', [FaqController::class, 'show']);
    Route::delete('faq-destroy/{id}', [FaqController::class, 'destroy']);

    //mis report
    Route::get('/mis-report', [MisController::class, 'misData']);
    Route::get('box-office-bookings/{number}', [BookingController::class, 'boxOfficeBooking']);

    // Route::post('wallet-user-transaction', [BalanceController::class, 'processTransaction']);
    // Route::post('verify-ticket/{orderId}', [BookingController::class, 'verifyTicket']);
    //SuccessfulEvent
    Route::GET('successfulEvent', [SuccessfulEventController::class, 'index']);
    Route::post('successfulEvent-store', [SuccessfulEventController::class, 'store']);
    Route::delete('successfulEvent-destroy/{id}', [SuccessfulEventController::class, 'destroy']);
    Route::get('expired-events', [SuccessfulEventController::class, 'getExpiredEvents']);

    // whatsapp configuration dynamic
    Route::get('whatsapp-config-show/{id}', [WhatsappConfigurationsController::class, 'show']);
    Route::post('whatsapp-config-store/{id}', [WhatsappConfigurationsController::class, 'store']);
    Route::delete('whatsapp-config-destroy/{id}', [WhatsappConfigurationsController::class, 'destroy']);
    Route::get('whatsapp-api-show', [WhatsappConfigurationsController::class, 'listData']);
    Route::get('whatsapp-api-show/{id}', [WhatsappConfigurationsController::class, 'list']);
    Route::post('whatsapp-api-store', [WhatsappConfigurationsController::class, 'storeApi']);
    Route::post('whatsapp-api-update/{id}', [WhatsappConfigurationsController::class, 'updateApi']);
    Route::delete('whatsapp-api-destroy/{id}', [WhatsappConfigurationsController::class, 'deleteApi']);
    Route::get('whatsapp-api/{id}/{title}', [WhatsappConfigurationsController::class, 'whatsappData']);
    Route::get('whatsapp-apiData/{title}', [WhatsappConfigurationsController::class, 'whatsappTitleData']);

    //
    Route::post('/complimentary-booking/check/users', [ComplimentaryBookingController::class, 'checkUsers']);

    //live user count
    // Route::get('/live-user-count', [LiveUserController::class, 'getLiveUserCount']);
    // Route::post('/live-user-count-store', [LiveUserController::class, 'store']);
    // Route::delete('/live-user-count-destroy', [LiveUserController::class, 'destroy']);

    //retrav iamges path
    Route::post('get-image/retrive', [BookingController::class, 'imagesRetrive']);
    Route::post('get-user-image/retrive', [BookingController::class, 'userImagesRetrive']);




    //attendy
    Route::get('fields-list', [AttndyController::class, 'fieldsList']);
    Route::get('fields-name', [AttndyController::class, 'fieldsListName']);
    Route::post('field-store', [AttndyController::class, 'store']);
    Route::post('field-update/{id}', [AttndyController::class, 'update']);
    Route::delete('field-delete/{id}', [AttndyController::class, 'destroy']);
    Route::get('catrgoty-fields-list', [AttndyController::class, 'catrgotyFieldsList']);
    Route::get('catrgoty-fields-list/{title}', [AttndyController::class, 'catrgotyFieldsListId']);
    Route::post('catrgoty-fields-store', [AttndyController::class, 'catrgotyFields']);
    Route::post('catrgoty-fields-update/{id}', [AttndyController::class, 'catrgotyFieldsUpdate']);
    Route::delete('catrgoty-fields-delelte/{id}', [AttndyController::class, 'catrgotyFieldsdestroy']);
    Route::post('/rearrange-CustomField', [AttndyController::class, 'rearrangeCustomField']);
    Route::post('/attndy-store', [AttndyController::class, 'attndyStore']);
    Route::get('/user-attendee/{userId}/{category_id}', [AttndyController::class, 'userAttendy']);
    Route::post('/attendees/update/{id}', [AttndyController::class, 'attndyUpdate']);
    Route::get('/attendee-list/{userId}/{event_id}', [AttndyController::class, 'attendyList']);


    //eazebuzz
    Route::get('/getSponsorsImages', [SettingController::class, 'getSponsorsImages']);
    Route::get('/getPcSponsorsImages', [SettingController::class, 'getPcSponsorsImages']);
    Route::get('pages-get-title', [PagesController::class, 'getTitle']);
    Route::get('pages-title/{title}', [PagesController::class, 'pageTitle']);
    Route::post('/footer-data', [SettingController::class, 'footerData']);
    Route::get('/footer-data-get', [SettingController::class, 'footerDataGet']);

    Route::get('footer-group', [FooterGrouController::class, 'index']);
    Route::post('/footer-group-store', [FooterGrouController::class, 'store']);
    Route::post('/footer-group-update/{id}', [FooterGrouController::class, 'update']);
    Route::get('footer-group-show/{id}', [FooterGrouController::class, 'show']);
    Route::delete('footer-group-destroy/{id}', [FooterGrouController::class, 'destroy']);

    Route::get('footer-menu/{id}', [FooterMenuController::class, 'index']);
    Route::post('/footer-menu-store', [FooterMenuController::class, 'store']);
    Route::post('/footer-menu-update/{id}', [FooterMenuController::class, 'update']);
    Route::get('footer-menu-show/{id}', [FooterMenuController::class, 'show']);
    Route::delete('footer-menu-destroy/{id}', [FooterMenuController::class, 'destroy']);

    Route::get('nav-menu', [NavigationMenuController::class, 'index']);
    Route::post('/nav-menu-store', [NavigationMenuController::class, 'store']);
    Route::post('/nav-menu-update/{id}', [NavigationMenuController::class, 'update']);
    Route::get('nav-menu-show/{id}', [NavigationMenuController::class, 'show']);
    Route::delete('nav-menu-destroy/{id}', [NavigationMenuController::class, 'destroy']);
    Route::post('/rearrange-menu', [NavigationMenuController::class, 'rearrangeMenu']);

    Route::get('menu-group', [MenuGroupController::class, 'index']);
    Route::post('/menu-group-store', [MenuGroupController::class, 'store']);
    Route::post('/menu-group-update/{id}', [MenuGroupController::class, 'update']);
    Route::get('menu-group-show/{id}', [MenuGroupController::class, 'show']);
    Route::delete('menu-group-destroy/{id}', [MenuGroupController::class, 'destroy']);
    Route::post('/update-status', [MenuGroupController::class, 'updateStatus']);
    Route::get('/active-menu', [MenuGroupController::class, 'activeStatus']);
    Route::get('/menu-title/{title}', [MenuGroupController::class, 'menuTitle']);

    Route::get('category', [CategoryController::class, 'index']);
    Route::post('/category-store', [CategoryController::class, 'store']);
    Route::post('/category-update/{id}', [CategoryController::class, 'update']);
    Route::get('/category-show/{id}', [CategoryController::class, 'show']);
    Route::delete('/category-destroy/{id}', [CategoryController::class, 'destroy']);
    Route::get('/category-data/{id}', [CategoryController::class, 'categoryTitle']);
    Route::get('/category-title', [CategoryController::class, 'allCategoryTitle']);
    Route::get('/category-images', [CategoryController::class, 'allCategoryImages']);
    Route::post('/payment-log', [CategoryController::class, 'allData']);
    Route::get('get-layout/{user_id}', [CategoryController::class, 'layoutList']);

    Route::get('socialMedia', [SocialMediaController::class, 'index']);
    Route::post('/socialMedia-store', [SocialMediaController::class, 'store']);
    Route::post('/socialMedia-update/{id}', [SocialMediaController::class, 'update']);
    Route::get('/socialMedia-show/{id}', [SocialMediaController::class, 'show']);
    Route::delete('/socialMedia-destroy/{id}', [SocialMediaController::class, 'destroy']);

    //dairect data base mathi data export
    Route::post('/import-tickets', [SocialMediaController::class, 'importExcel']);

    // Route::post('/attndy-store-whatsapp', [AttndyController::class, 'attndyStorewhatsapp']);

    // prem
    Route::get('/attendee_images/{event_id}', [AttndyController::class, 'attendeeImages']);
    Route::get('/attendee_jsone', [AttndyController::class, 'attendeeJsone']);
    Route::post('/flow/flow-data', [AttndyController::class, 'flowData']);
});



// Route::any('/payment-response/{gateway}/{id}/{session_id}', [EasebuzzController::class, 'handlePaymentResponse'])->middleware('restrict.payment');
// Route::post('/payment-webhook/{gateway}/vod', [EasebuzzController::class, 'handleWebhook']);
Route::post('/payment-webhook/tov', [EasebuzzController::class, 'handleWebhookTov']);
Route::post('/payment-success', [InstaMozoController::class, 'success']);

//cashfree-payment
Route::get('cashfree/payments/create', [CashfreeController::class, 'create'])->name('callback');
Route::post('cashfree/payments/store', [CashfreeController::class, 'store'])->name('store');
Route::any('cashfree/payments/success', [CashfreeController::class, 'success'])->name('success');

Route::post('/notifications/save-token', [NotificationController::class, 'storeToken']);
Route::post('/sendToAll', [NotificationController::class, 'sendToAll']);

Route::get('/run-command', function () {
    Artisan::call('optimize:clear');
    // Artisan::call('storage:link');
    $output = Artisan::output();

    return $output;
});
