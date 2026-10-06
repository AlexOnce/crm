<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Dummy bcrypt hash para mitigar ataques de temporización (timing attacks)
     * y prevenir la enumeración de correos existentes.
     */
    private const DUMMY_HASH = '$2y$10$abcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNO';

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|string|email|max:255',
            'password' => 'required|string|max:128',
        ]);

        // Sanitización y normalización de correo electrónico
        $email = strtolower(trim($validated['email']));

        $user = User::where('email', $email)->first();

        // Verificación de credenciales con protección contra timing attacks
        $isPasswordValid = false;
        if ($user) {
            $isPasswordValid = Hash::check($validated['password'], $user->password);
        } else {
            // Se computa el hash para igualar el tiempo de cómputo bcrypt
            Hash::check($validated['password'], self::DUMMY_HASH);
        }

        if (! $user || ! $isPasswordValid) {
            Log::warning('Auth: Intento de inicio de sesión fallido', [
                'email' => $email,
                'ip' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 200),
            ]);

            throw ValidationException::withMessages([
                'email' => ['Las credenciales proporcionadas son incorrectas.'],
            ]);
        }

        if (! $user->is_active) {
            Log::warning('Auth: Intento de acceso bloqueado a usuario suspendido/inactivo', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Tu cuenta de agente está suspendida o inactiva.',
            ], 403);
        }

        // Revocar tokens anteriores de sesión del agente para evitar acumulación y sesiones huérfanas
        $user->tokens()->where('name', 'agent_panel_session')->delete();

        // Crear token de Sanctum con expiración controlada
        $token = $user->createToken('agent_panel_session')->plainTextToken;

        Log::info('Auth: Inicio de sesión exitoso', [
            'user_id' => $user->id,
            'email' => $user->email,
            'role' => $user->role,
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'user' => $request->user(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Sesión cerrada correctamente.',
        ]);
    }

    /**
     * Actualizar la contraseña del agente o usuario autenticado
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:6', 'confirmed', 'different:current_password'],
        ], [
            'current_password.required' => 'Debes ingresar tu contraseña actual.',
            'password.required' => 'Debes ingresar una nueva contraseña.',
            'password.min' => 'La nueva contraseña debe tener al menos 6 caracteres.',
            'password.confirmed' => 'La confirmación de la nueva contraseña no coincide.',
            'password.different' => 'La nueva contraseña debe ser diferente a tu contraseña actual.',
        ]);

        $user = $request->user();

        // Verificar que la contraseña actual ingresada coincida con el hash almacenado
        if (! Hash::check($validated['current_password'], $user->password)) {
            Log::warning('Auth: Intento fallido de cambio de contraseña (contraseña actual incorrecta)', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip' => $request->ip(),
            ]);

            throw ValidationException::withMessages([
                'current_password' => ['La contraseña actual ingresada es incorrecta.'],
            ]);
        }

        // Actualizar contraseña del usuario
        $user->password = Hash::make($validated['password']);
        $user->save();

        // Por seguridad, revocar las demás sesiones activas en otros dispositivos/navegadores
        $currentToken = $user->currentAccessToken();
        if ($currentToken) {
            $user->tokens()->where('id', '!=', $currentToken->id)->delete();
        }

        Log::info('Auth: Contraseña de usuario actualizada exitosamente', [
            'user_id' => $user->id,
            'email' => $user->email,
            'role' => $user->role,
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Tu contraseña ha sido actualizada con éxito.',
        ]);
    }
}
