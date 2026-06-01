<?php

namespace Jeffgreco13\Wave\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Jeffgreco13\Wave\Traits\HasWaveSync;

class WaveAccount extends Model
{
    use HasWaveSync;

    protected $table = 'wave_accounts';

    protected $fillable = [
        'wave_id',
        'wave_business_id',
        'name',
        'description',
        'display_id',
        'currency_code',
        'account_type',
        'account_type_normal_balance',
        'account_subtype',
        'normal_balance_type',
        'balance',
        'balance_in_business_currency',
        'sequence',
        'is_archived',
    ];

    protected $casts = [
        'balance'                      => 'decimal:2',
        'balance_in_business_currency' => 'decimal:2',
        'sequence'                     => 'integer',
        'is_archived'                  => 'boolean',
    ];

    public function waveAttributeMap(): array
    {
        return [
            'wave_id'                      => 'id',
            'wave_business_id'             => 'business.id',
            'name'                         => 'name',
            'description'                  => 'description',
            'display_id'                   => 'displayId',
            'currency_code'                => 'currency.code',
            'account_type'                 => 'type.value',
            'account_type_normal_balance'  => 'type.normalBalanceType',
            'account_subtype'              => 'subtype.value',
            'normal_balance_type'          => 'normalBalanceType',
            'balance'                      => 'balance',
            'balance_in_business_currency' => 'balanceInBusinessCurrency',
            'sequence'                     => 'sequence',
            'is_archived'                  => 'isArchived',
        ];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_archived', false);
    }

    public function scopeType(Builder $query, string $type): void
    {
        $query->where('account_type', strtoupper($type));
    }

    public function scopeSubtype(Builder $query, string $subtype): void
    {
        $query->where('account_subtype', strtoupper($subtype));
    }
}
