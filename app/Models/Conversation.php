<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'phone',
        'contact_name',
        'channel',
        'visitor_token',
        'visitor_ip',
        'visitor_user_agent',
        'status',
        'current_state',
        'session_data',
        'assigned_user_id',
        'last_interaction_at',
        'source_type',
        'source_id',
        'source_url',
        'headline',
        'body',
        'referral_data',
        'free_window_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'session_data' => 'array',
            'referral_data' => 'array',
            'last_interaction_at' => 'datetime',
            'free_window_expires_at' => 'datetime',
        ];
    }

    protected $appends = [
        'remaining_free_window_hours',
        'is_free_window_active',
    ];

    /**
     * Determina si la ventana de 72 horas gratuitas de atención Meta está activa.
     */
    public function isFreeWindowActive(): bool
    {
        return $this->free_window_expires_at !== null && $this->free_window_expires_at->isFuture();
    }

    /**
     * Accessor reactivo para el frontend Vue.js.
     */
    public function getIsFreeWindowActiveAttribute(): bool
    {
        return $this->isFreeWindowActive();
    }

    /**
     * Obtiene las horas restantes de la ventana gratuita de 72 horas.
     */
    public function getRemainingFreeWindowHoursAttribute(): int
    {
        if (! $this->isFreeWindowActive()) {
            return 0;
        }

        return max(0, (int) now()->diffInHours($this->free_window_expires_at, false));
    }

    /**
     * Indica si el cliente inició la conversación mediante un anuncio o botón de Facebook.
     */
    public function getHasReferralAttribute(): bool
    {
        return ! empty($this->source_type) || ! empty($this->referral_data);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class)->orderBy('created_at', 'asc');
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(ChatMessage::class)->latestOfMany();
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class)->orderBy('created_at', 'desc');
    }

    public function latestLead(): HasOne
    {
        return $this->hasOne(Lead::class)->latestOfMany();
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    public function scopeWaitingAgent(Builder $query): Builder
    {
        return $query->where('status', 'waiting_agent');
    }

    public function scopeHumanLive(Builder $query): Builder
    {
        return $query->where('status', 'human_live');
    }

    public function scopeBotActive(Builder $query): Builder
    {
        return $query->where('status', 'bot_active');
    }
}
