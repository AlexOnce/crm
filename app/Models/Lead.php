<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lead extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'phone',
        'type',
        'passenger_name',
        'passport_number',
        'origin',
        'destination',
        'flight_date',
        'flight_number',
        'passengers_count',
        'total_price',
        'status',
        'booking_reference',
        'ticket_pdf_path',
        'details',
        'bot_payload',
    ];

    protected function casts(): array
    {
        return [
            'flight_date' => 'date:Y-m-d',
            'passengers_count' => 'integer',
            'total_price' => 'decimal:2',
            'bot_payload' => 'array',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }
}
