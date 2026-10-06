<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AgentManagementController extends Controller
{
    /**
     * Listado de todos los agentes y personal de soporte
     */
    public function index(): JsonResponse
    {
        $agents = User::withCount('assignedConversations')
            ->orderBy('id', 'desc')
            ->get(['id', 'name', 'email', 'role', 'is_active', 'created_at']);

        return response()->json([
            'success' => true,
            'agents' => $agents,
        ]);
    }

    /**
     * Registrar un nuevo agente
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'role' => 'nullable|in:agent,admin',
        ]);

        $agent = User::create([
            'name' => $validated['name'],
            'email' => strtolower(trim($validated['email'])),
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'] ?? 'agent',
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Agente registrado exitosamente.',
            'agent' => $agent,
        ], 201);
    }

    /**
     * Actualizar datos o contraseña de un agente existente
     */
    public function update(Request $request, User $agent): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:100',
            'email' => ['sometimes', 'required', 'email', Rule::unique('users')->ignore($agent->id)],
            'password' => 'nullable|string|min:6',
            'role' => 'sometimes|in:agent,admin',
            'is_active' => 'sometimes|boolean',
        ]);

        if (! empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $agent->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Agente actualizado correctamente.',
            'agent' => $agent->fresh(),
        ]);
    }

    /**
     * Activar o suspender cuenta de agente
     */
    public function toggleStatus(User $agent): JsonResponse
    {
        $agent->is_active = ! $agent->is_active;
        $agent->save();

        if (! $agent->is_active) {
            // Revocar tokens activos si la cuenta se suspende
            $agent->tokens()->delete();
        }

        $statusText = $agent->is_active ? 'activada' : 'suspendida';

        return response()->json([
            'success' => true,
            'message' => "Cuenta del agente {$statusText} correctamente.",
            'is_active' => $agent->is_active,
        ]);
    }

    /**
     * Eliminar permanentemente la cuenta de un agente
     */
    public function destroy(Request $request, User $agent): JsonResponse
    {
        // 1. Evitar auto-eliminación
        if ($request->user() && $request->user()->id === $agent->id) {
            return response()->json([
                'success' => false,
                'message' => 'No puedes eliminar tu propia cuenta de usuario.',
            ], 422);
        }

        // 2. Evitar eliminar el único administrador
        if ($agent->role === 'admin' && User::where('role', 'admin')->where('id', '!=', $agent->id)->count() === 0) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar al único administrador del sistema.',
            ], 422);
        }

        // 3. Desvincular conversaciones asignadas
        // Si estaban en atención humana con este agente, regresarlas a espera de agente
        $agent->assignedConversations()
            ->where('status', 'human_live')
            ->update([
                'status' => 'waiting_agent',
                'assigned_user_id' => null,
            ]);

        $agent->assignedConversations()
            ->update(['assigned_user_id' => null]);

        // 4. Revocar tokens de autenticación
        $agent->tokens()->delete();

        // 5. Eliminar el registro
        $agentName = $agent->name;
        $agent->delete();

        return response()->json([
            'success' => true,
            'message' => "El agente {$agentName} ha sido eliminado correctamente del sistema.",
        ]);
    }
}
