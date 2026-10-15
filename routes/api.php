<?php

use App\Http\Controllers\Api\AdminDashboardController;
use App\Http\Controllers\Api\AgentManagementController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CrmChatController;
use App\Http\Controllers\Api\FlightBookingController;
use App\Http\Controllers\Api\MassBroadcastController;
use App\Http\Controllers\Api\PushSubscriptionController;
use App\Http\Controllers\Webhooks\MetaWebhookController;
use App\Services\FlightMicroservice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Webhooks Oficiales de Meta Cloud API (WhatsApp Business)
|--------------------------------------------------------------------------
*/
// Verificación del Token para Meta Developers Console (compatible singular y plural)
Route::get('/webhooks/whatsapp', [MetaWebhookController::class, 'verify']);
Route::get('/webhook/whatsapp', [MetaWebhookController::class, 'verify']);

// Recepción de mensajes en tiempo real desde Meta Cloud API
Route::post('/webhooks/whatsapp', [MetaWebhookController::class, 'handle']);
Route::post('/webhook/whatsapp', [MetaWebhookController::class, 'handle']);

/*
|--------------------------------------------------------------------------
| Autenticación de Agentes (Público con Protección contra Fuerza Bruta)
|--------------------------------------------------------------------------
*/
Route::post('/auth/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1');

/*
|--------------------------------------------------------------------------
| Rutas Protegidas del Panel CRM (Laravel Sanctum + Verificación de Cuenta Activa)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'active'])->group(function () {
    // Sesión de Agente
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::put('/auth/password', [AuthController::class, 'updatePassword'])
        ->middleware('throttle:6,1');

    // Gestión de Conversaciones
    Route::get('/conversations', [CrmChatController::class, 'conversations']);
    Route::get('/conversations/{conversation}', [CrmChatController::class, 'show']);
    Route::post('/conversations/{conversation}/take', [CrmChatController::class, 'take']);
    Route::post('/conversations/{conversation}/assign', [CrmChatController::class, 'assign']);
    Route::post('/conversations/{conversation}/reply', [CrmChatController::class, 'replyToCustomer']);
    Route::post('/conversations/{conversation}/send-flow', [CrmChatController::class, 'sendFlowToCustomer']);
    Route::post('/conversations/{conversation}/release', [CrmChatController::class, 'releaseToBot']);

    // Notificaciones Web Push Nativas (VAPID)
    Route::get('/push/vapid-public-key', [PushSubscriptionController::class, 'vapidPublicKey']);
    Route::post('/push/subscribe', [PushSubscriptionController::class, 'subscribe']);
    Route::post('/push/unsubscribe', [PushSubscriptionController::class, 'unsubscribe']);
    Route::post('/push/test', [PushSubscriptionController::class, 'test']);

    // Emisión Transaccional y Gestión de Boletos / Leads
    Route::post('/leads/{lead}/issue-ticket', [FlightBookingController::class, 'issueTicket']);
    Route::post('/conversations/{conversation}/lead', [FlightBookingController::class, 'storeManualLead']);
    Route::put('/leads/{lead}', [FlightBookingController::class, 'updateLead']);

    // Consulta de Itinerarios en Tiempo Real
    Route::get('/flights/search', function (Request $request, FlightMicroservice $flightService) {
        $origin = $request->query('origin', 'VVI');
        $destination = $request->query('destination', 'LPB');
        $date = $request->query('date');

        return response()->json([
            'success' => true,
            'flights' => $flightService->searchFlights($origin, $destination, $date),
        ]);
    });

    // Directorio de Agentes (Lectura permitida para agentes para derivar chats)
    Route::get('/agents', [AgentManagementController::class, 'index']);

    // Gestión Administrativa del Equipo (Exclusivo Rol Admin)
    Route::middleware('admin')->group(function () {
        Route::post('/agents', [AgentManagementController::class, 'store']);
        Route::put('/agents/{agent}', [AgentManagementController::class, 'update']);
        Route::delete('/agents/{agent}', [AgentManagementController::class, 'destroy']);
        Route::post('/agents/{agent}/toggle', [AgentManagementController::class, 'toggleStatus']);
    });

    // Panel de Administración Gerencial (Exclusivo Rol Admin)
    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('/metrics', [AdminDashboardController::class, 'metrics']);
        Route::get('/tickets', [AdminDashboardController::class, 'tickets']);
        Route::post('/passengers', [AdminDashboardController::class, 'storePassenger']);
        Route::patch('/passengers/{lead}/status', [AdminDashboardController::class, 'updatePassengerStatus']);
        Route::get('/conversations/monitor', [AdminDashboardController::class, 'conversationsMonitor']);
        Route::get('/meta-status', [AdminDashboardController::class, 'metaStatus']);
        Route::get('/itinerary', [AdminDashboardController::class, 'getItinerary']);
        Route::post('/itinerary', [AdminDashboardController::class, 'updateItinerary']);

        // Textos y Plantillas Personalizables del Bot de WhatsApp
        Route::get('/bot-messages', [AdminDashboardController::class, 'getBotMessages']);
        Route::put('/bot-messages/{botMessage}', [AdminDashboardController::class, 'updateBotMessage']);
        Route::post('/bot-messages/{botMessage}/restore', [AdminDashboardController::class, 'restoreBotMessage']);

        // Difusión y Mensajería Masiva de Vuelos (Exclusivo SMS GoIP - Entel)
        Route::post('/broadcasts/preview', [MassBroadcastController::class, 'preview']);
        Route::post('/broadcasts/import-pdf', [MassBroadcastController::class, 'importPdfManifest']);
        Route::post('/broadcasts/check-flight', [MassBroadcastController::class, 'checkFlightStatus']);
        Route::post('/broadcasts/process-queue', [MassBroadcastController::class, 'processQueueNow']);
        Route::get('/broadcasts', [MassBroadcastController::class, 'index']);
        Route::post('/broadcasts', [MassBroadcastController::class, 'store']);
        Route::get('/broadcasts/{campaign}', [MassBroadcastController::class, 'show']);
        Route::post('/broadcasts/{campaign}/retry-failed', [MassBroadcastController::class, 'retryFailed']);
    });
});
