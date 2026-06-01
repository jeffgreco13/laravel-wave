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
     * Resolve a flat attribute array from a Wave API response using this model's
     * waveAttributeMap. Values missing from the payload resolve to null, except
     * for attributes the model casts as boolean, which are coerced to false so
     * they satisfy NOT NULL columns.
     */
    protected function mapWaveAttributes(array $data): array
    {
        $attributes = [];

        foreach ($this->waveAttributeMap() as $column => $wavePath) {
            $value = data_get($data, $wavePath);

            if (is_null($value) && $this->hasCast($column, ['bool', 'boolean'])) {
                $value = false;
            }

            $attributes[$column] = $value;
        }

        return $attributes;
    }

    /**
     * Build an unsaved model instance from a Wave API response.
     */
    public static function fromWaveData(array $data): static
    {
        $instance = new static;

        return static::make($instance->mapWaveAttributes($data));
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

        return static::updateOrCreate(
            ['wave_id' => $waveId],
            $instance->mapWaveAttributes($data)
        );
    }
}
