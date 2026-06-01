<?php

namespace Jeffgreco13\Wave\Traits;

trait HasWaveSync
{
    /**
     * Map Wave API fields (camelCase, nested) to local column names (snake_case, flat).
     * Dot-notation resolves nested values: 'currency.code' → $data['currency']['code'].
     * Override this method in your model to customize field mapping.
     */
    public function waveAttributeMap(): array
    {
        return [];
    }

    /**
     * Build a flat attribute array from a Wave API response using this model's waveAttributeMap.
     */
    public static function fromWaveData(array $data): static
    {
        $instance = new static;
        $map = $instance->waveAttributeMap();

        $attributes = [];
        foreach ($map as $column => $wavePath) {
            $attributes[$column] = data_get($data, $wavePath);
        }

        return static::make($attributes);
    }

    /**
     * Upsert a local record from Wave API data. Matches on wave_id.
     */
    public static function syncFromWave(array $data): static
    {
        $instance = new static;
        $map = $instance->waveAttributeMap();

        $waveIdPath = $map['wave_id'] ?? 'id';
        $waveId = data_get($data, $waveIdPath);

        $attributes = [];
        foreach ($map as $column => $wavePath) {
            $attributes[$column] = data_get($data, $wavePath);
        }

        return static::updateOrCreate(
            ['wave_id' => $waveId],
            $attributes
        );
    }
}
