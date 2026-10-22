<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CallCenterBroadcast extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'source_extension',
        'message',
        'image_path',
        'image_url',
        'total_extensions',
        'sent_count',
        'failed_count',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'total_extensions' => 'integer',
            'sent_count' => 'integer',
            'failed_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(CallCenterRecipient::class, 'broadcast_id');
    }
}
