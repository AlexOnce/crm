<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemVerificationCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'service',
        'sender_phone',
        'full_message',
        'metadata',
        'status',
        'consumed_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'consumed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Scope para códigos que no han sido consumidos ni expirados.
     */
    public function scopeValid(Builder $query): Builder
    {
        return $query->where('status', 'pending')
            ->where(function (Builder $q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    /**
     * Scope para ordenar por los códigos más recientes primero.
     */
    public function scopeLatestFirst(Builder $query): Builder
    {
        return $query->orderBy('created_at', 'desc');
    }

    /**
     * Determina si el código ya expiró.
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Marca el código como utilizado por el administrador.
     */
    public function markAsConsumed(): bool
    {
        return $this->update([
            'status' => 'consumed',
            'consumed_at' => now(),
        ]);
    }
}
