<?php

namespace Jeffgreco13\Wave\Tests;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Jeffgreco13\Wave\Jobs\SyncCustomersFromWave;
use Jeffgreco13\Wave\Jobs\SyncProductsFromWave;
use Jeffgreco13\Wave\Models\WaveCustomer;
use Jeffgreco13\Wave\Models\WaveProduct;
use Jeffgreco13\Wave\WaveService;
use PHPUnit\Framework\Attributes\Test;

class CommandsTest extends TestCase
{
    private function customersResponse(array $edges): array
    {
        return [
            'data' => [
                'business' => [
                    'customers' => [
                        'pageInfo' => ['currentPage' => 1, 'totalPages' => 1, 'totalCount' => count($edges)],
                        'edges'    => $edges,
                    ],
                ],
            ],
        ];
    }

    private function edge(string $id, string $name): array
    {
        return ['node' => [
            'id'         => $id,
            'name'       => $name,
            'isArchived' => false,
            'createdAt'  => '2024-01-01T00:00:00.000Z',
            'modifiedAt' => '2024-06-01T00:00:00.000Z',
        ]];
    }

    #[Test]
    public function sync_customers_command_dispatches_to_queue_by_default(): void
    {
        Queue::fake();

        $this->artisan('wave:sync-customers')
            ->expectsOutputToContain('dispatched')
            ->assertExitCode(0);

        Queue::assertPushed(SyncCustomersFromWave::class);
    }

    #[Test]
    public function sync_customers_command_runs_synchronously_with_sync_flag(): void
    {
        Http::fake([
            '*' => Http::response($this->customersResponse([$this->edge('c1', 'Sync Customer')])),
        ]);

        $this->artisan('wave:sync-customers --sync')
            ->assertExitCode(0);

        $this->assertSame(1, WaveCustomer::count());
    }

    #[Test]
    public function fresh_flag_truncates_table_before_full_sync(): void
    {
        WaveCustomer::create(['wave_id' => 'old', 'name' => 'Old']);

        Http::fake([
            '*' => Http::response($this->customersResponse([$this->edge('c1', 'Fresh Customer')])),
        ]);

        $this->artisan('wave:sync-customers --sync --fresh')
            ->assertExitCode(0);

        $this->assertNull(WaveCustomer::where('wave_id', 'old')->first());
        $this->assertNotNull(WaveCustomer::where('wave_id', 'c1')->first());
    }

    #[Test]
    public function since_last_resolves_from_most_recent_local_record(): void
    {
        WaveCustomer::create([
            'wave_id'          => 'existing',
            'name'             => 'Existing',
            'wave_modified_at' => '2024-05-01T00:00:00.000Z',
        ]);

        Http::fake([
            '*' => Http::response($this->customersResponse([])),
        ]);

        $this->artisan('wave:sync-customers --sync --since=last')
            ->assertExitCode(0);

        // Incremental run must send modifiedAtAfter derived from the local record,
        // and must NOT delete the existing record.
        Http::assertSent(fn ($request) => isset($request->data()['variables']['modifiedAtAfter']));
        $this->assertNotNull(WaveCustomer::where('wave_id', 'existing')->first());
    }

    #[Test]
    public function sync_products_job_persists_and_prunes(): void
    {
        WaveProduct::create(['wave_id' => 'stale-product', 'name' => 'Stale']);

        Http::fake([
            '*' => Http::response([
                'data' => [
                    'business' => [
                        'products' => [
                            'edges' => [
                                ['node' => [
                                    'id'         => 'p1',
                                    'name'       => 'Active Product',
                                    'unitPrice'  => '19.99',
                                    'isSold'     => true,
                                    'isBought'   => false,
                                    'isArchived' => false,
                                    'createdAt'  => '2024-01-01T00:00:00.000Z',
                                    'modifiedAt' => '2024-01-01T00:00:00.000Z',
                                ]],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        (new SyncProductsFromWave)->handle(app(WaveService::class));

        $this->assertNull(WaveProduct::where('wave_id', 'stale-product')->first());
        $product = WaveProduct::where('wave_id', 'p1')->first();
        $this->assertNotNull($product);
        $this->assertTrue($product->is_sold);
        $this->assertFalse($product->is_bought);
        $this->assertEquals('19.99', $product->unit_price);
    }

    #[Test]
    public function sync_all_command_queues_all_jobs(): void
    {
        Queue::fake();

        $this->artisan('wave:sync-all')->assertExitCode(0);

        Queue::assertPushed(SyncCustomersFromWave::class);
        Queue::assertPushed(SyncProductsFromWave::class);
    }
}
