<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

// Ruta de autenticación para canales privados con Bearer Token (Sanctum)
Broadcast::routes(['middleware' => ['auth:sanctum', 'active'], 'prefix' => 'api']);

// Canal privado para un chat específico
Broadcast::channel('chat.{conversationId}', function (User $user, $conversationId) {
    return (bool) $user->is_active;
});

// Canal privado global para la lista de conversaciones y contadores
Broadcast::channel('conversations', function (User $user) {
    return (bool) $user->is_active;
});

// Canal privado para códigos OTP y verificaciones del sistema
Broadcast::channel('system.verifications', function (User $user) {
    return (bool) $user->is_active;
});
