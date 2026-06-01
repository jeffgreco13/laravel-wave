<?php

namespace Jeffgreco13\Wave\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Jeffgreco13\Wave\Traits\HasWaveSync;

class WaveSalesTax extends Model
{
    use HasWaveSync;

    protected $table = 'wave_sales_taxes';

    protected $fillable = [
        'wave_id',
        'wave_business_id',
        'name',
        'abbreviation',
        'description',
        'tax_number',
        'rate',
        'rates',
        'is_compound',
        'is_recoverable',
        'is_archived',
        'show_tax_number_on_invoices',
        'wave_created_at',
        'wave_modified_at',
    ];

    protected $casts = [
        'rate'                        => 'decimal:4',
        'rates'                       => 'array',
        'is_compound'                 => 'boolean',
        'is_recoverable'              => 'boolean',
        'is_archived'                 => 'boolean',
        'show_tax_number_on_invoices' => 'boolean',
        'wave_created_at'             => 'datetime',
        'wave_modified_at'            => 'datetime',
    ];

    public function waveAttributeMap(): array
    {
        return [
            'wave_id'                      => 'id',
            'wave_business_id'             => 'businessId',
            'name'                         => 'name',
            'abbreviation'                 => 'abbreviation',
            'description'                  => 'description',
            'tax_number'                   => 'taxNumber',
            'rate'                         => 'rate',
            'rates'                        => 'rates',
            'is_compound'                  => 'isCompound',
            'is_recoverable'               => 'isRecoverable',
            'is_archived'                  => 'isArchived',
            'show_tax_number_on_invoices'  => 'showTaxNumberOnInvoices',
            'wave_created_at'              => 'createdAt',
            'wave_modified_at'             => 'modifiedAt',
        ];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_archived', false);
    }
}
