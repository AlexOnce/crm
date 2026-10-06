import { defineStore } from 'pinia';
import api from '@/api/client';
import { disconnectEcho, updateEchoToken } from '@/echo';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: JSON.parse(localStorage.getItem('agent_user') || 'null'),
        token: localStorage.getItem('agent_token') || null,
        loading: false,
        error: null,
        rateLimitedUntil: null,
    }),

    getters: {
        isAuthenticated: (state) => !!state.token,
        isAdmin: (state) => state.user?.role === 'admin',
    },

    actions: {
        clearError() {
            this.error = null;
        },

        async login(email, password) {
            this.loading = true;
            this.error = null;
            try {
                const response = await api.post('/auth/login', {
                    email: (email || '').trim().toLowerCase(),
                    password: password || '',
                });

                if (response.data.success) {
                    this.token = response.data.token;
                    this.user = response.data.user;
                    this.rateLimitedUntil = null;
                    localStorage.setItem('agent_token', this.token);
                    localStorage.setItem('agent_user', JSON.stringify(this.user));
                    updateEchoToken(this.token);
                    return true;
                }
            } catch (err) {
                if (err.response?.status === 429) {
                    this.error = 'Demasiados intentos fallidos. Por seguridad, tu acceso se ha pausado temporalmente (1 minuto).';
                    this.rateLimitedUntil = Date.now() + 60000;
                } else if (err.response?.status === 422) {
                    const validationErrors = err.response.data?.errors;
                    if (validationErrors) {
                        const firstErrorKey = Object.keys(validationErrors)[0];
                        this.error = validationErrors[firstErrorKey][0] || 'Datos de inicio de sesión inválidos.';
                    } else {
                        this.error = err.response.data?.message || 'Las credenciales proporcionadas son incorrectas.';
                    }
                } else if (err.response?.status === 403) {
                    this.error = err.response?.data?.message || 'Tu cuenta está inactiva o suspendida.';
                } else {
                    this.error = err.response?.data?.message || 'Error de conexión con el servidor. Intenta de nuevo.';
                }
                return false;
            } finally {
                this.loading = false;
            }
        },

        async logout() {
            try {
                if (this.token) {
                    await api.post('/auth/logout');
                }
            } catch (_) {
                // ignore
            } finally {
                this.token = null;
                this.user = null;
                localStorage.removeItem('agent_token');
                localStorage.removeItem('agent_user');
                disconnectEcho();
            }
        },

        async updatePassword({ current_password, password, password_confirmation }) {
            this.loading = true;
            this.error = null;
            try {
                const response = await api.put('/auth/password', {
                    current_password,
                    password,
                    password_confirmation,
                });
                return response.data;
            } catch (err) {
                const errorData = err.response?.data;
                let message = 'Error al actualizar la contraseña.';

                if (err.response?.status === 422 && errorData?.errors) {
                    const firstKey = Object.keys(errorData.errors)[0];
                    message = errorData.errors[firstKey][0];
                } else if (err.response?.status === 429) {
                    message = 'Demasiados intentos. Por favor espera un momento antes de volver a intentar.';
                } else if (errorData?.message) {
                    message = errorData.message;
                }

                throw new Error(message);
            } finally {
                this.loading = false;
            }
        },
    },
});
