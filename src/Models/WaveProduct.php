<?php

namespace Jeffgreco13\Wave\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Jeffgreco13\Wave\Traits\HasWaveSync;

class WaveProduct extends Model
{
    use HasWaveSync;

    protected $table = 'wave_products';

    protected $fillable = [
        'wave_id',
        'wave_business_id',
        'name',
        'description',
        'unit_price',
        'is_sold',
        'is_bought',
        'income_account_id',
        'income_account_name',
        'expense_account_id',
        'expense_account_name',
        'default_sales_taxes',
        'is_archived',
        'wave_created_at',
        'wave_modified_at',
    ];

    protected $casts = [
        'unit_price'          => 'decimal:2',
        'is_sold'             => 'boolean',
        'is_bought'           => 'boolean',
        'is_archived'         => 'boolean',
        'default_sales_taxes' => 'array',
        'wave_created_at'     => 'datetime',
        'wave_modified_at'    => 'datetime',
    ];

    public function waveAttributeMap(): array
    {
        return [
            'wave_id'              => 'id',
            'wave_business_id'     => 'businessId',
            'name'                 => 'name',
            'description'          => 'description',
            'unit_price'           => 'unitPrice',
            'is_sold'              => 'isSold',
            'is_bought'            => 'isBought',
            'income_account_id'    => 'incomeAccount.id',
            'income_account_name'  => 'incomeAccount.name',
            'expense_account_id'   => 'expenseAccount.id',
            'expense_account_name' => 'expenseAccount.name',
            'default_sales_taxes'  => 'defaultSalesTaxes',
            'is_archived'          => 'isArchived',
            'wave_created_at'      => 'createdAt',
            'wave_modified_at'     => 'modifiedAt',
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

    public function scopeSold(Builder $query): void
    {
        $query->where('is_sold', true);
    }

    public function scopeBought(Builder $query): void
    {
        $query->where('is_bought', true);
    }
}
