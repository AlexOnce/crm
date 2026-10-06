<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PushSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'endpoint',
        'endpoint_hash',
        'public_key',
        'auth_token',
        'content_encoding',
        'user_agent',
    ];

    /**
     * Boot the model to auto-calculate endpoint_hash if not set.
     */
    protected static function booted(): void
    {
        static::saving(function (PushSubscription $sub) {
            if ($sub->endpoint && empty($sub->endpoint_hash)) {
                $sub->endpoint_hash = hash('sha256', $sub->endpoint);
            }
        });
    }

    /**
     * Usuario propietario de la suscripción
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
