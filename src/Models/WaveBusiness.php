<?php

namespace Jeffgreco13\Wave\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Jeffgreco13\Wave\Traits\HasWaveSync;

class WaveBusiness extends Model
{
    use HasWaveSync;

    protected $table = 'wave_businesses';

    protected $fillable = [
        'wave_id',
        'name',
        'is_personal',
        'organizational_type',
        'business_type',
        'business_subtype',
        'currency_code',
        'timezone',
        'phone',
        'fax',
        'mobile',
        'toll_free',
        'website',
        'address_line1',
        'address_line2',
        'city',
        'province_code',
        'province_name',
        'country_code',
        'country_name',
        'postal_code',
        'is_classic_accounting',
        'is_classic_invoicing',
        'is_archived',
        'wave_created_at',
        'wave_modified_at',
    ];

    protected $casts = [
        'is_personal'           => 'boolean',
        'is_classic_accounting' => 'boolean',
        'is_classic_invoicing'  => 'boolean',
        'is_archived'           => 'boolean',
        'wave_created_at'       => 'datetime',
        'wave_modified_at'      => 'datetime',
    ];

    public function waveAttributeMap(): array
    {
        return [
            'wave_id'               => 'id',
            'name'                  => 'name',
            'is_personal'           => 'isPersonal',
            'organizational_type'   => 'organizationalType',
            'business_type'         => 'type.value',
            'business_subtype'      => 'subtype.value',
            'currency_code'         => 'currency.code',
            'timezone'              => 'timezone',
            'phone'                 => 'phone',
            'fax'                   => 'fax',
            'mobile'                => 'mobile',
            'toll_free'             => 'tollFree',
            'website'               => 'website',
            'address_line1'         => 'address.addressLine1',
            'address_line2'         => 'address.addressLine2',
            'city'                  => 'address.city',
            'province_code'         => 'address.province.code',
            'province_name'         => 'address.province.name',
            'country_code'          => 'address.country.code',
            'country_name'          => 'address.country.name',
            'postal_code'           => 'address.postalCode',
            'is_classic_accounting' => 'isClassicAccounting',
            'is_classic_invoicing'  => 'isClassicInvoicing',
            'is_archived'           => 'isArchived',
            'wave_created_at'       => 'createdAt',
            'wave_modified_at'      => 'modifiedAt',
        ];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_archived', false);
    }
}
