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
        'status',
        'current_state',
        'session_data',
        'assigned_user_id',
        'last_interaction_at',
    ];

    protected function casts(): array
    {
        return [
            'session_data' => 'array',
            'last_interaction_at' => 'datetime',
        ];
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
