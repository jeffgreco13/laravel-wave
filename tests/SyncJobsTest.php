<?php

namespace Jeffgreco13\Wave\Tests;

use Illuminate\Support\Facades\Http;
use Jeffgreco13\Wave\Jobs\CreateWaveCustomer;
use Jeffgreco13\Wave\Jobs\DeleteWaveCustomer;
use Jeffgreco13\Wave\Jobs\SyncCustomersFromWave;
use Jeffgreco13\Wave\Jobs\UpdateWaveCustomer;
use Jeffgreco13\Wave\Models\WaveCustomer;
use Jeffgreco13\Wave\WaveService;
use PHPUnit\Framework\Attributes\Test;

class SyncJobsTest extends TestCase
{
    private function customerNode(string $id, string $name, string $modifiedAt = '2024-06-01T00:00:00.000Z'): array
    {
        return [
            'node' => [
                'id'         => $id,
                'name'       => $name,
                'email'      => "{$id}@test.dev",
                'isArchived' => false,
                'createdAt'  => '2024-01-01T00:00:00.000Z',
                'modifiedAt' => $modifiedAt,
            ],
        ];
    }

    private function customersResponse(array $edges, int $currentPage = 1, int $totalPages = 1): array
    {
        return [
            'data' => [
                'business' => [
                    'customers' => [
                        'pageInfo' => [
                            'currentPage' => $currentPage,
                            'totalPages'  => $totalPages,
                            'totalCount'  => count($edges),
                        ],
                        'edges' => $edges,
                    ],
                ],
            ],
        ];
    }

    #[Test]
    public function full_sync_upserts_all_customers(): void
    {
        Http::fake([
            '*' => Http::response($this->customersResponse([
                $this->customerNode('c1', 'Customer One'),
                $this->customerNode('c2', 'Customer Two'),
            ])),
        ]);

        (new SyncCustomersFromWave)->handle(app(WaveService::class));

        $this->assertSame(2, WaveCustomer::count());
        $this->assertNotNull(WaveCustomer::where('wave_id', 'c1')->first());
        $this->assertSame('Customer Two', WaveCustomer::where('wave_id', 'c2')->first()->name);
    }

    #[Test]
    public function full_sync_deletes_orphaned_local_records(): void
    {
        // Seed a local record that will NOT be returned by Wave
        WaveCustomer::create(['wave_id' => 'stale', 'name' => 'Stale Customer']);

        Http::fake([
            '*' => Http::response($this->customersResponse([
                $this->customerNode('c1', 'Customer One'),
            ])),
        ]);

        (new SyncCustomersFromWave)->handle(app(WaveService::class));

        $this->assertNull(WaveCustomer::where('wave_id', 'stale')->first());
        $this->assertNotNull(WaveCustomer::where('wave_id', 'c1')->first());
        $this->assertSame(1, WaveCustomer::count());
    }

    #[Test]
    public function incremental_sync_does_not_delete_local_records(): void
    {
        WaveCustomer::create(['wave_id' => 'untouched', 'name' => 'Untouched Customer']);

        Http::fake([
            '*' => Http::response($this->customersResponse([
                $this->customerNode('c1', 'Recently Modified'),
            ])),
        ]);

        (new SyncCustomersFromWave(since: now()->subDay()))->handle(app(WaveService::class));

        // Both the untouched seed and the new record must remain
        $this->assertNotNull(WaveCustomer::where('wave_id', 'untouched')->first());
        $this->assertNotNull(WaveCustomer::where('wave_id', 'c1')->first());
        $this->assertSame(2, WaveCustomer::count());
    }

    #[Test]
    public function incremental_sync_sends_modified_at_after_variable(): void
    {
        Http::fake([
            '*' => Http::response($this->customersResponse([])),
        ]);

        $since = now()->subDays(3);
        (new SyncCustomersFromWave(since: $since))->handle(app(WaveService::class));

        Http::assertSent(function ($request) use ($since) {
            $body = $request->data();

            return isset($body['variables']['modifiedAtAfter'])
                && $body['variables']['modifiedAtAfter'] === $since->toIso8601String();
        });
    }

    #[Test]
    public function full_sync_paginates_through_all_pages(): void
    {
        Http::fakeSequence()
            ->push($this->customersResponse([$this->customerNode('c1', 'Page1')], currentPage: 1, totalPages: 2))
            ->push($this->customersResponse([$this->customerNode('c2', 'Page2')], currentPage: 2, totalPages: 2));

        (new SyncCustomersFromWave)->handle(app(WaveService::class));

        $this->assertSame(2, WaveCustomer::count());
    }

    #[Test]
    public function create_job_calls_api_and_persists_locally(): void
    {
        Http::fake([
            '*' => Http::response([
                'data' => [
                    'customerCreate' => [
                        'customer' => [
                            'id'         => 'new-1',
                            'name'       => 'Brand New',
                            'email'      => 'new@test.dev',
                            'isArchived' => false,
                            'createdAt'  => '2024-06-01T00:00:00.000Z',
                            'modifiedAt' => '2024-06-01T00:00:00.000Z',
                        ],
                        'didSucceed'  => true,
                        'inputErrors' => null,
                    ],
                ],
            ]),
        ]);

        (new CreateWaveCustomer(['name' => 'Brand New', 'email' => 'new@test.dev']))
            ->handle(app(WaveService::class));

        $this->assertSame(1, WaveCustomer::count());
        $this->assertSame('Brand New', WaveCustomer::first()->name);
    }

    #[Test]
    public function update_job_calls_api_and_updates_locally(): void
    {
        WaveCustomer::create(['wave_id' => 'upd-1', 'name' => 'Old Name']);

        Http::fake([
            '*' => Http::response([
                'data' => [
                    'customerPatch' => [
                        'customer' => [
                            'id'         => 'upd-1',
                            'name'       => 'New Name',
                            'isArchived' => false,
                            'createdAt'  => '2024-06-01T00:00:00.000Z',
                            'modifiedAt' => '2024-06-02T00:00:00.000Z',
                        ],
                        'didSucceed'  => true,
                        'inputErrors' => null,
                    ],
                ],
            ]),
        ]);

        (new UpdateWaveCustomer(['id' => 'upd-1', 'name' => 'New Name']))
            ->handle(app(WaveService::class));

        $this->assertSame(1, WaveCustomer::count());
        $this->assertSame('New Name', WaveCustomer::first()->name);
    }

    #[Test]
    public function delete_job_calls_api_and_removes_local_record(): void
    {
        WaveCustomer::create(['wave_id' => 'del-1', 'name' => 'To Delete']);

        Http::fake([
            '*' => Http::response([
                'data' => [
                    'customerDelete' => [
                        'didSucceed'  => true,
                        'inputErrors' => null,
                    ],
                ],
            ]),
        ]);

        (new DeleteWaveCustomer('del-1'))->handle(app(WaveService::class));

        $this->assertSame(0, WaveCustomer::count());
    }

    #[Test]
    public function delete_job_keeps_local_record_when_api_fails(): void
    {
        WaveCustomer::create(['wave_id' => 'keep-1', 'name' => 'Keep']);

        Http::fake([
            '*' => Http::response([
                'data' => [
                    'customerDelete' => [
                        'didSucceed'  => false,
                        'inputErrors' => [['path' => 'customerId', 'message' => 'nope', 'code' => 'X']],
                    ],
                ],
            ]),
        ]);

        (new DeleteWaveCustomer('keep-1'))->handle(app(WaveService::class));

        $this->assertSame(1, WaveCustomer::count());
    }
}
