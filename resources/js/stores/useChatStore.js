import { defineStore } from 'pinia';
import api from '@/api/client';
import { getEcho } from '@/echo';
import { showSystemNotification, setConversationOpenHandler } from '@/services/pwaService';

/**
 * Reproduce un tono sutil sintético cuando entra un mensaje del cliente
 */
export function playChime() {
    try {
        const AudioContextClass = window.AudioContext || window.webkitAudioContext;
        if (!AudioContextClass) return;
        const ctx = new AudioContextClass();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(587.33, ctx.currentTime); // D5
        osc.frequency.setValueAtTime(880, ctx.currentTime + 0.08); // A5
        gain.gain.setValueAtTime(0.12, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.3);
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start();
        osc.stop(ctx.currentTime + 0.3);
    } catch (_) {}
}

export const useChatStore = defineStore('chat', {
    state: () => ({
        conversations: [],
        counts: {
            waiting_agent: 0,
            human_live: 0,
            bot_active: 0,
        },
        activeConversation: null,
        messages: [],
        activeLead: null,
        leads: [],
        filterStatus: 'waiting_agent', // Default to priority queue (handover)
        searchQuery: '',
        loadingList: false,
        loadingMessages: false,
        sendingMessage: false,
        issuingTicket: false,
        lastIssuedTicket: null,
        
        // Estado de WebSockets y sincronización
        wsConnected: false,
        currentChatChannelId: null,
    }),

    actions: {
        /**
         * Inicializa las suscripciones de Laravel Echo (WebSockets)
         */
        initWebSockets() {
            const echo = getEcho();
            if (!echo) {
                this.wsConnected = false;
                return;
            }

            try {
                this.wsConnected = true;

                // Canal privado de todas las conversaciones (para actualizar contadores y lista en vivo)
                echo.private('conversations')
                    .listen('.ChatMessageSent', (e) => {
                        this.handleGlobalMessageEvent(e);
                    })
                    .listen('.ConversationUpdated', (e) => {
                        this.handleConversationUpdated(e);
                    });

                // Registrar callback para abrir chat cuando el usuario hace clic en una notificación PWA
                setConversationOpenHandler((conversationId) => {
                    const conv = this.conversations.find((c) => parseInt(c.id) === parseInt(conversationId));
                    if (conv) {
                        this.selectConversation(conv);
                    } else {
                        this.selectConversation({ id: conversationId });
                    }
                });

                // Si ya hay un chat abierto, suscribirse a su canal privado
                if (this.activeConversation) {
                    this.subscribeToChatChannel(this.activeConversation.id);
                }
            } catch (err) {
                console.warn('[ChatStore] Error conectando a WebSockets:', err);
                this.wsConnected = false;
            }
        },

        /**
         * Se suscribe al canal privado del chat activo
         */
        subscribeToChatChannel(conversationId) {
            const echo = getEcho();
            if (!echo || !conversationId) return;

            // Desuscribirse del canal anterior si era distinto
            if (this.currentChatChannelId && this.currentChatChannelId !== conversationId) {
                try {
                    echo.leave(`chat.${this.currentChatChannelId}`);
                } catch (_) {}
            }

            this.currentChatChannelId = conversationId;

            echo.private(`chat.${conversationId}`)
                .listen('.ChatMessageSent', (e) => {
                    this.handleActiveChatMessage(e);
                })
                .listen('.ConversationUpdated', (e) => {
                    if (e.conversation_id === this.activeConversation?.id) {
                        this.activeConversation = { ...this.activeConversation, ...e.conversation };
                    }
                });
        },

        /**
         * Procesa un mensaje recibido en el chat activo
         */
        handleActiveChatMessage(e) {
            if (!e.message || !this.activeConversation) return;
            if (parseInt(e.conversation_id) !== parseInt(this.activeConversation.id)) return;

            // Evitar duplicados si ya existe el ID
            const exists = this.messages.some((m) => m.id === e.message.id);
            if (!exists) {
                this.messages.push(e.message);

                // Si el mensaje es del cliente o del bot, sonar campana sutil
                if (e.message.sender === 'user') {
                    playChime();

                    // Si la ventana está en segundo plano o el usuario está en otra app, lanzar notificación PWA
                    if (typeof document !== 'undefined' && (document.hidden || !document.hasFocus())) {
                        const contact = this.activeConversation?.contact_name || this.activeConversation?.phone || 'Pasajero';
                        const text = e.message.message || (e.message.media_type === 'audio' ? '🎤 Nota de voz recibida' : 'Nuevo mensaje');
                        showSystemNotification({
                            title: `💬 ${contact}`,
                            body: text,
                            tag: `chat-${this.activeConversation.id}`,
                            data: { conversationId: this.activeConversation.id }
                        });
                    }
                }
            }

            // Actualizar timestamp de interacción
            if (e.conversation) {
                this.activeConversation.status = e.conversation.status || this.activeConversation.status;
                this.activeConversation.last_interaction_at = e.conversation.last_interaction_at || this.activeConversation.last_interaction_at;
            }
        },

        /**
         * Procesa eventos globales de mensajes (actualiza contadores o añade vista previa)
         */
        handleGlobalMessageEvent(e) {
            if (!e.message) return;

            // Refrescar lista silenciosamente para reordenar chats por actividad reciente
            this.fetchConversations(true);

            // Si el mensaje recibido corresponde al chat abierto actualmente, procesarlo
            if (this.activeConversation && parseInt(e.conversation_id) === parseInt(this.activeConversation.id)) {
                this.handleActiveChatMessage(e);
            } else if (e.message.sender === 'user') {
                // Mensaje en un chat que no está visible actualmente
                playChime();
                const senderName = e.conversation?.contact_name || e.conversation?.phone || 'Pasajero de WhatsApp';
                const text = e.message.message || (e.message.media_type === 'audio' ? '🎤 Nota de voz recibida' : 'Nuevo mensaje recibido');
                showSystemNotification({
                    title: `💬 Nuevo mensaje de ${senderName}`,
                    body: text,
                    tag: `chat-${e.conversation_id}`,
                    data: { conversationId: e.conversation_id }
                });
            }
        },

        /**
         * Procesa actualización de estado de una conversación (asignada, tomada, bot)
         */
        handleConversationUpdated(e) {
            if (!e.conversation) return;
            this.fetchConversations(true);

            if (this.activeConversation && parseInt(e.conversation_id) === parseInt(this.activeConversation.id)) {
                this.activeConversation = { ...this.activeConversation, ...e.conversation };
            }

            // Alerta urgente si un pasajero solicita atención de agente (Handover a 'waiting_agent')
            if (e.conversation.status === 'waiting_agent') {
                const clientName = e.conversation.contact_name || e.conversation.phone || 'Pasajero';
                showSystemNotification({
                    title: `🛎️ Pasajero en Espera: ${clientName}`,
                    body: 'Solicita atención con un asesor de ventas de TAMep.',
                    tag: `waiting-${e.conversation.id}`,
                    data: { conversationId: e.conversation.id }
                });
            }
        },

        /**
         * Sincronización silenciosa periódica (Smart Polling como respaldo garantizado)
         */
        async syncActiveChat() {
            if (!this.activeConversation) return;
            try {
                const res = await api.get(`/conversations/${this.activeConversation.id}`);
                if (res.data.success && res.data.messages) {
                    const newMessages = res.data.messages;
                    
                    // Comprobar si hay mensajes nuevos
                    if (newMessages.length > this.messages.length) {
                        const lastOldMsg = this.messages[this.messages.length - 1];
                        const lastNewMsg = newMessages[newMessages.length - 1];

                        if (lastOldMsg?.id !== lastNewMsg?.id && lastNewMsg?.sender === 'user') {
                            playChime();
                            const contact = this.activeConversation?.contact_name || this.activeConversation?.phone || 'Pasajero';
                            showSystemNotification({
                                title: `💬 ${contact}`,
                                body: lastNewMsg.message || (lastNewMsg.media_type === 'audio' ? '🎤 Nota de voz recibida' : 'Nuevo mensaje recibido'),
                                tag: `chat-${this.activeConversation.id}`,
                                data: { conversationId: this.activeConversation.id }
                            });
                        }
                        this.messages = newMessages;
                    }
                    
                    if (res.data.conversation) {
                        this.activeConversation = res.data.conversation;
                    }
                    if (res.data.lead) {
                        if (!this.activeLead || this.activeLead.id === res.data.lead.id) {
                            this.activeLead = res.data.lead;
                        }
                    }
                    if (res.data.leads) {
                        this.leads = res.data.leads;
                    }
                }
            } catch (err) {
                // Silencioso para no ensuciar logs en desconexiones menores
            }
        },

        async fetchConversations(silent = false) {
            if (!silent) this.loadingList = true;
            try {
                const params = {
                    status: this.filterStatus,
                    search: this.searchQuery || undefined,
                };
                const res = await api.get('/conversations', { params });
                if (res.data.success) {
                    const newConvs = res.data.conversations.data || [];
                    const newCounts = res.data.counts || {};

                    // Si entra un nuevo pasajero a espera mientras la app está abierta/minimizada:
                    if (silent && newCounts.waiting_agent > this.counts.waiting_agent) {
                        const firstWaiting = newConvs.find((c) => c.status === 'waiting_agent');
                        const clientName = firstWaiting?.contact_name || firstWaiting?.phone || 'Pasajero';
                        showSystemNotification({
                            title: `🛎️ Pasajero en Espera: ${clientName}`,
                            body: `Solicita atención de un asesor (Hay ${newCounts.waiting_agent} en espera).`,
                            tag: `waiting-${firstWaiting?.id || Date.now()}`,
                            data: { conversationId: firstWaiting?.id }
                        });
                    }

                    // Si llega un nuevo mensaje en cualquier chat que no esté abierto en pantalla:
                    if (silent && this.conversations.length > 0) {
                        for (const newC of newConvs) {
                            const oldC = this.conversations.find((c) => c.id === newC.id);
                            if (oldC && newC.latest_message && oldC.latest_message?.id !== newC.latest_message?.id) {
                                if (newC.latest_message.sender === 'user' && (!this.activeConversation || this.activeConversation.id !== newC.id)) {
                                    showSystemNotification({
                                        title: `💬 Mensaje de ${newC.contact_name || newC.phone}`,
                                        body: newC.latest_message.message || (newC.latest_message.media_type === 'audio' ? '🎤 Nota de voz recibida' : 'Nuevo mensaje'),
                                        tag: `chat-${newC.id}`,
                                        data: { conversationId: newC.id }
                                    });
                                }
                            }
                        }
                    }

                    this.conversations = newConvs;
                    this.counts = newCounts;
                }
            } catch (err) {
                console.error('Error fetching conversations:', err);
            } finally {
                if (!silent) this.loadingList = false;
            }
        },

        async selectConversation(conv) {
            this.activeConversation = conv;
            this.lastIssuedTicket = null;
            this.loadingMessages = true;

            // Suscribir el canal WebSocket a esta conversación específica
            this.subscribeToChatChannel(conv.id);

            try {
                const res = await api.get(`/conversations/${conv.id}`);
                if (res.data.success) {
                    this.messages = res.data.messages;
                    this.activeLead = res.data.lead;
                    this.leads = res.data.leads || (res.data.lead ? [res.data.lead] : []);
                    this.activeConversation = res.data.conversation;
                }
            } catch (err) {
                console.error('Error loading chat details:', err);
            } finally {
                this.loadingMessages = false;
            }
        },

        setActiveLead(lead) {
            this.activeLead = lead;
        },

        async sendMessage(payload) {
            if (!this.activeConversation) return false;
            this.sendingMessage = true;
            try {
                let data;
                let headers = {};
                if (payload instanceof FormData) {
                    data = payload;
                    headers = { 'Content-Type': 'multipart/form-data' };
                } else if (typeof payload === 'object' && payload !== null) {
                    const formData = new FormData();
                    if (payload.message) formData.append('message', payload.message);
                    if (payload.image) formData.append('image', payload.image);
                    data = formData;
                    headers = { 'Content-Type': 'multipart/form-data' };
                } else {
                    if (!String(payload).trim()) return false;
                    data = { message: payload };
                }

                const res = await api.post(`/conversations/${this.activeConversation.id}/reply`, data, { headers });
                if (res.data.success) {
                    // Evitar duplicar si el websocket ya lo insertó
                    const exists = this.messages.some((m) => m.id === res.data.message?.id);
                    if (!exists && res.data.message) {
                        this.messages.push(res.data.message);
                    }

                    this.activeConversation.status = 'human_live';
                    this.fetchConversations(true);
                    return true;
                }
                return false;
            } catch (err) {
                console.error('Error sending message:', err);
                return false;
            } finally {
                this.sendingMessage = false;
            }
        },

        async sendFlow(flowType = 'passenger') {
            if (!this.activeConversation) return { success: false, message: 'No hay conversación activa' };
            this.sendingMessage = true;
            try {
                const res = await api.post(`/conversations/${this.activeConversation.id}/send-flow`, {
                    flow_type: flowType,
                });
                if (res.data.success) {
                    if (res.data.chat_message) {
                        const exists = this.messages.some((m) => m.id === res.data.chat_message.id);
                        if (!exists) {
                            this.messages.push(res.data.chat_message);
                        }
                    }
                    this.fetchConversations(true);
                    return { success: true, message: res.data.message };
                }
                return { success: false, message: res.data.message || 'Error al enviar Formulario' };
            } catch (err) {
                console.error('Error sending flow:', err);
                const msg = err.response?.data?.message || 'Error al enviar el formulario.';
                return { success: false, message: msg };
            } finally {
                this.sendingMessage = false;
            }
        },

        async sendPassengerFlow() {
            return this.sendFlow('passenger');
        },

        async takeActiveConversation() {
            if (!this.activeConversation) return;
            try {
                const res = await api.post(`/conversations/${this.activeConversation.id}/take`);
                if (res.data.success) {
                    this.activeConversation = res.data.conversation;
                    this.fetchConversations(true);
                }
            } catch (err) {
                console.error('Error taking conversation:', err);
            }
        },

        async assignConversation(targetAgentId) {
            if (!this.activeConversation || !targetAgentId) return false;
            try {
                const res = await api.post(`/conversations/${this.activeConversation.id}/assign`, {
                    agent_id: targetAgentId,
                });
                if (res.data.success) {
                    this.activeConversation = res.data.conversation;
                    await this.fetchConversations(true);
                    await this.selectConversation(this.activeConversation);
                    return true;
                }
                return false;
            } catch (err) {
                console.error('Error reassigning conversation:', err);
                return false;
            }
        },

        async releaseToBot() {
            if (!this.activeConversation) return false;
            try {
                const res = await api.post(`/conversations/${this.activeConversation.id}/release`);
                if (res.data.success) {
                    this.activeConversation.status = 'bot_active';
                    this.fetchConversations(true);
                    return true;
                }
            } catch (err) {
                console.error('Error releasing to bot:', err);
                return false;
            }
        },

        async issueTicket(formData) {
            if (!this.activeLead) return { success: false, message: 'No hay lead activo para este chat' };
            this.issuingTicket = true;
            try {
                const res = await api.post(`/leads/${this.activeLead.id}/issue-ticket`, formData);
                if (res.data.success) {
                    this.activeLead = res.data.lead;
                    if (Array.isArray(this.leads) && res.data.lead) {
                        const idx = this.leads.findIndex((l) => l.id === res.data.lead.id);
                        if (idx !== -1) {
                            this.leads[idx] = res.data.lead;
                        } else {
                            this.leads.unshift(res.data.lead);
                        }
                    }
                    this.lastIssuedTicket = {
                        pnr: res.data.pnr,
                        passenger: formData.passenger_name,
                    };
                    if (res.data.conversation) {
                        this.activeConversation = res.data.conversation;
                    }
                    this.fetchConversations(true);
                    return { success: true, pnr: res.data.pnr };
                }
                return { success: false, message: res.data.message };
            } catch (err) {
                const msg = err.response?.data?.message || 'Error al orquestar la emisión del pasaje';
                return { success: false, message: msg };
            } finally {
                this.issuingTicket = false;
            }
        },

        async saveManualLead(formData) {
            if (!this.activeConversation) return { success: false, message: 'No hay conversación activa' };
            this.issuingTicket = true;
            try {
                let res;
                if (this.activeLead && this.activeLead.id) {
                    res = await api.put(`/leads/${this.activeLead.id}`, formData);
                } else {
                    res = await api.post(`/conversations/${this.activeConversation.id}/lead`, formData);
                }

                if (res.data.success && res.data.lead) {
                    this.activeLead = res.data.lead;
                    if (!Array.isArray(this.leads)) {
                        this.leads = [];
                    }
                    const idx = this.leads.findIndex((l) => l.id === res.data.lead.id);
                    if (idx !== -1) {
                        this.leads[idx] = res.data.lead;
                    } else {
                        this.leads.unshift(res.data.lead);
                    }
                    // Actualizar nombre de contacto en activeConversation si estaba vacío
                    if (this.activeConversation && !this.activeConversation.contact_name && formData.passenger_name) {
                        this.activeConversation.contact_name = formData.passenger_name;
                    }
                    this.fetchConversations(true);
                    return { success: true, lead: res.data.lead };
                }
                return { success: false, message: res.data.message || 'Error al guardar reserva' };
            } catch (err) {
                const msg = err.response?.data?.message || 'Error al guardar la reserva';
                return { success: false, message: msg };
            } finally {
                this.issuingTicket = false;
            }
        },
    },
});
