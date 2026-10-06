<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    /**
     * Obtiene el valor de una configuración por su clave con caché en memoria.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $val = Cache::remember("setting_{$key}", 3600, function () use ($key) {
            $record = static::where('key', $key)->first();

            return $record ? $record->value : null;
        });

        if ($val === null) {
            return $default;
        }

        // Si es JSON, decodificarlo automáticamente a array
        $decoded = json_decode($val, true);

        return (json_last_error() === JSON_ERROR_NONE) ? $decoded : $val;
    }

    /**
     * Guarda o actualiza una configuración e invalida el caché.
     */
    public static function set(string $key, mixed $value): void
    {
        $encoded = is_array($value) || is_object($value) ? json_encode($value) : (string) $value;
        static::updateOrCreate(['key' => $key], ['value' => $encoded]);
        Cache::forget("setting_{$key}");
    }
}
