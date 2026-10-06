<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BotMessage extends Model
{
    protected $fillable = [
        'key',
        'title',
        'category',
        'description',
        'message',
        'default_message',
        'variables',
        'updated_by',
    ];

    protected $casts = [
        'variables' => 'array',
    ];

    protected static function booted(): void
    {
        static::saved(function (self $model) {
            Cache::forget("bot_msg_{$model->key}");
            Cache::forget('bot_messages_all');
        });

        static::deleted(function (self $model) {
            Cache::forget("bot_msg_{$model->key}");
            Cache::forget('bot_messages_all');
        });
    }

    /**
     * Obtiene el texto de un mensaje configurado por el área comercial.
     * Si no existe o la BD no responde, recurre al texto predeterminado sin fallar jamás.
     */
    public static function getText(string $key, ?string $fallback = null, array $replace = []): string
    {
        $rawMessage = Cache::remember("bot_msg_{$key}", 86400, function () use ($key, $fallback) {
            try {
                $record = static::where('key', $key)->first(['message', 'default_message']);
                if ($record && ! empty(trim($record->message))) {
                    return $record->message;
                }
                if ($record && ! empty(trim($record->default_message))) {
                    return $record->default_message;
                }
            } catch (\Throwable $e) {
                Log::warning("[BotMessage] Error consultando mensaje '{$key}': ".$e->getMessage());
            }

            return $fallback ?? '';
        });

        $text = $rawMessage ?: ($fallback ?? '');

        // Reemplazar variables dinámicas si fueron suministradas (ej: {nombre}, {vuelo})
        if (! empty($replace)) {
            foreach ($replace as $variable => $value) {
                $text = str_replace(
                    [
                        '{'.$variable.'}',
                        '{{'.$variable.'}}',
                        '{{ '.$variable.' }}',
                        ':'.$variable,
                    ],
                    (string) $value,
                    $text
                );
            }
        }

        return $text;
    }
}
