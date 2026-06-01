<?php

namespace Jeffgreco13\Wave\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Jeffgreco13\Wave\Traits\HasWaveSync;

class WaveInvoice extends Model
{
    use HasWaveSync;

    protected $table = 'wave_invoices';

    protected $fillable = [
        'wave_id',
        'wave_business_id',
        'wave_customer_id',
        'invoice_number',
        'status',
        'title',
        'subhead',
        'po_number',
        'memo',
        'footer',
        'invoice_date',
        'due_date',
        'currency_code',
        'exchange_rate',
        'amount_due',
        'amount_due_currency',
        'amount_paid',
        'tax_total',
        'total',
        'total_currency',
        'pdf_url',
        'view_url',
        'items',
        'last_sent_via',
        'last_sent_at',
        'last_viewed_at',
        'is_archived',
        'wave_created_at',
        'wave_modified_at',
    ];

    protected $casts = [
        'items'            => 'array',
        'invoice_date'     => 'date',
        'due_date'         => 'date',
        'exchange_rate'    => 'decimal:10',
        'amount_due'       => 'decimal:2',
        'amount_paid'      => 'decimal:2',
        'tax_total'        => 'decimal:2',
        'total'            => 'decimal:2',
        'is_archived'      => 'boolean',
        'last_sent_at'     => 'datetime',
        'last_viewed_at'   => 'datetime',
        'wave_created_at'  => 'datetime',
        'wave_modified_at' => 'datetime',
    ];

    public function waveAttributeMap(): array
    {
        return [
            'wave_id'            => 'id',
            'wave_business_id'   => 'businessId',
            'wave_customer_id'   => 'customer.id',
            'invoice_number'     => 'invoiceNumber',
            'status'             => 'status',
            'title'              => 'title',
            'subhead'            => 'subhead',
            'po_number'          => 'poNumber',
            'memo'               => 'memo',
            'footer'             => 'footer',
            'invoice_date'       => 'invoiceDate',
            'due_date'           => 'dueDate',
            'currency_code'      => 'currency.code',
            'exchange_rate'      => 'exchangeRate',
            'amount_due'         => 'amountDue.value',
            'amount_due_currency' => 'amountDue.currency.code',
            'amount_paid'        => 'amountPaid.value',
            'tax_total'          => 'taxTotal.value',
            'total'              => 'total.value',
            'total_currency'     => 'total.currency.code',
            'pdf_url'            => 'pdfUrl',
            'view_url'           => 'viewUrl',
            'items'              => 'items',
            'last_sent_via'      => 'lastSentVia',
            'last_sent_at'       => 'lastSentAt',
            'last_viewed_at'     => 'lastViewedAt',
            'is_archived'        => 'isArchived',
            'wave_created_at'    => 'createdAt',
            'wave_modified_at'   => 'modifiedAt',
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

    public function scopeStatus(Builder $query, string $status): void
    {
        $query->where('status', strtoupper($status));
    }

    public function scopeForCustomer(Builder $query, string $waveCustomerId): void
    {
        $query->where('wave_customer_id', $waveCustomerId);
    }
}
