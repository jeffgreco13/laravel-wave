<?php

namespace Jeffgreco13\Wave\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Jeffgreco13\Wave\Traits\HasWaveSync;

class WaveVendor extends Model
{
    use HasWaveSync;

    protected $table = 'wave_vendors';

    protected $fillable = [
        'wave_id',
        'wave_business_id',
        'name',
        'first_name',
        'last_name',
        'display_id',
        'email',
        'phone',
        'mobile',
        'fax',
        'toll_free',
        'website',
        'internal_notes',
        'currency_code',
        'address_line1',
        'address_line2',
        'city',
        'province_code',
        'province_name',
        'country_code',
        'country_name',
        'postal_code',
        'is_archived',
        'wave_created_at',
        'wave_modified_at',
    ];

    protected $casts = [
        'is_archived'      => 'boolean',
        'wave_created_at'  => 'datetime',
        'wave_modified_at' => 'datetime',
    ];

    public function waveAttributeMap(): array
    {
        return [
            'wave_id'          => 'id',
            'wave_business_id' => 'business.id',
            'name'             => 'name',
            'first_name'       => 'firstName',
            'last_name'        => 'lastName',
            'display_id'       => 'displayId',
            'email'            => 'email',
            'phone'            => 'phone',
            'mobile'           => 'mobile',
            'fax'              => 'fax',
            'toll_free'        => 'tollFree',
            'website'          => 'website',
            'internal_notes'   => 'internalNotes',
            'currency_code'    => 'currency.code',
            'address_line1'    => 'address.addressLine1',
            'address_line2'    => 'address.addressLine2',
            'city'             => 'address.city',
            'province_code'    => 'address.province.code',
            'province_name'    => 'address.province.name',
            'country_code'     => 'address.country.code',
            'country_name'     => 'address.country.name',
            'postal_code'      => 'address.postalCode',
            'is_archived'      => 'isArchived',
            'wave_created_at'  => 'createdAt',
            'wave_modified_at' => 'modifiedAt',
        ];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_archived', false);
    }

    public function scopeArchived(Builder $query): void
    {
        $query->where('is_archived', true);
    }
}
