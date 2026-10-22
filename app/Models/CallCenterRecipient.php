<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallCenterRecipient extends Model
{
    use HasFactory;

    protected $fillable = [
        'broadcast_id',
        'extension',
        'extension_name',
        'status',
        'response_message',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(CallCenterBroadcast::class, 'broadcast_id');
    }
}
