<?php

namespace Jeffgreco13\Wave\Tests;

use Jeffgreco13\Wave\Models\WaveCustomer;
use Jeffgreco13\Wave\Models\WaveInvoice;
use PHPUnit\Framework\Attributes\Test;

class HasWaveSyncTest extends TestCase
{
    private function customerPayload(array $overrides = []): array
    {
        return array_merge([
            'id'            => 'customer-1',
            'businessId'    => 'biz-1',
            'name'          => 'Acme Corp',
            'firstName'     => 'Jane',
            'lastName'      => 'Doe',
            'displayId'     => 'ACME',
            'email'         => 'billing@acme.test',
            'phone'         => '555-1000',
            'mobile'        => '555-2000',
            'internalNotes' => 'VIP',
            'currency'      => ['code' => 'USD'],
            'address'       => [
                'addressLine1' => '123 Main St',
                'addressLine2' => 'Suite 4',
                'city'         => 'Springfield',
                'province'     => ['code' => 'US-IL', 'name' => 'Illinois'],
                'country'      => ['code' => 'US', 'name' => 'United States'],
                'postalCode'   => '62704',
            ],
            'shippingDetails' => [
                'name'         => 'Warehouse',
                'phone'        => '555-3000',
                'instructions' => 'Leave at dock',
                'address'      => [
                    'addressLine1' => '500 Depot Rd',
                    'city'         => 'Springfield',
                    'province'     => ['code' => 'US-IL'],
                    'country'      => ['code' => 'US'],
                    'postalCode'   => '62705',
                ],
            ],
            'outstandingAmount' => ['value' => '150.00', 'currency' => ['code' => 'USD']],
            'overdueAmount'     => ['value' => '25.50', 'currency' => ['code' => 'USD']],
            'isArchived'        => false,
            'createdAt'         => '2024-01-01T00:00:00.000Z',
            'modifiedAt'        => '2024-06-01T12:00:00.000Z',
        ], $overrides);
    }

    #[Test]
    public function it_maps_nested_wave_data_to_flat_columns(): void
    {
        $customer = WaveCustomer::fromWaveData($this->customerPayload());

        $this->assertSame('customer-1', $customer->wave_id);
        $this->assertSame('biz-1', $customer->wave_business_id);
        $this->assertSame('Acme Corp', $customer->name);
        $this->assertSame('Jane', $customer->first_name);
        $this->assertSame('USD', $customer->currency_code);
        $this->assertSame('123 Main St', $customer->address_line1);
        $this->assertSame('US-IL', $customer->province_code);
        $this->assertSame('Illinois', $customer->province_name);
        $this->assertSame('US', $customer->country_code);
        $this->assertSame('62704', $customer->postal_code);
        $this->assertSame('Warehouse', $customer->shipping_name);
        $this->assertSame('500 Depot Rd', $customer->shipping_address_line1);
        $this->assertSame('Leave at dock', $customer->shipping_instructions);
    }

    #[Test]
    public function it_casts_attributes_correctly(): void
    {
        $customer = WaveCustomer::syncFromWave($this->customerPayload());

        $this->assertIsBool($customer->is_archived);
        $this->assertFalse($customer->is_archived);
        $this->assertEquals('150.00', $customer->outstanding_amount);
        $this->assertEquals('25.50', $customer->overdue_amount);
        $this->assertNotNull($customer->wave_created_at);
        $this->assertSame('2024-06-01', $customer->wave_modified_at->format('Y-m-d'));
    }

    #[Test]
    public function sync_from_wave_upserts_on_wave_id(): void
    {
        WaveCustomer::syncFromWave($this->customerPayload());
        WaveCustomer::syncFromWave($this->customerPayload(['name' => 'Acme Corp Ltd']));

        $this->assertSame(1, WaveCustomer::count());
        $this->assertSame('Acme Corp Ltd', WaveCustomer::first()->name);
    }

    #[Test]
    public function missing_nested_keys_resolve_to_null_without_error(): void
    {
        $customer = WaveCustomer::fromWaveData([
            'id'   => 'customer-2',
            'name' => 'Minimal',
        ]);

        $this->assertSame('customer-2', $customer->wave_id);
        $this->assertSame('Minimal', $customer->name);
        $this->assertNull($customer->currency_code);
        $this->assertNull($customer->province_code);
        $this->assertNull($customer->shipping_address_line1);
    }

    #[Test]
    public function invoice_stores_line_items_as_array_cast(): void
    {
        $invoice = WaveInvoice::syncFromWave([
            'id'            => 'invoice-1',
            'invoiceNumber' => 'INV-001',
            'status'        => 'UNPAID',
            'customer'      => ['id' => 'customer-1'],
            'currency'      => ['code' => 'USD'],
            'total'         => ['value' => '99.99', 'currency' => ['code' => 'USD']],
            'amountDue'     => ['value' => '99.99', 'currency' => ['code' => 'USD']],
            'items'         => [
                ['description' => 'Widget', 'quantity' => 2, 'unitPrice' => '49.99'],
            ],
            'createdAt'  => '2024-01-01T00:00:00.000Z',
            'modifiedAt' => '2024-01-01T00:00:00.000Z',
        ]);

        $this->assertSame('customer-1', $invoice->wave_customer_id);
        $this->assertIsArray($invoice->items);
        $this->assertSame('Widget', $invoice->items[0]['description']);
        $this->assertEquals('99.99', $invoice->total);
        $this->assertSame('USD', $invoice->total_currency);
    }

    #[Test]
    public function scopes_filter_archived_state(): void
    {
        WaveCustomer::syncFromWave($this->customerPayload(['id' => 'a', 'isArchived' => false]));
        WaveCustomer::syncFromWave($this->customerPayload(['id' => 'b', 'isArchived' => true]));

        $this->assertSame(1, WaveCustomer::active()->count());
        $this->assertSame(1, WaveCustomer::archived()->count());
    }
}
