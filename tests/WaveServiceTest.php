<?php

namespace Jeffgreco13\Wave\Tests;

use Illuminate\Support\Facades\Http;
use Jeffgreco13\Wave\Exceptions\AuthenticationException;
use Jeffgreco13\Wave\Node;
use Jeffgreco13\Wave\WaveService;
use PHPUnit\Framework\Attributes\Test;

class WaveServiceTest extends TestCase
{
    #[Test]
    public function service_resolves_from_container_as_singleton(): void
    {
        $a = app(WaveService::class);
        $b = app(WaveService::class);

        $this->assertInstanceOf(WaveService::class, $a);
        $this->assertSame($a, $b);
    }

    #[Test]
    public function alias_resolves_to_the_same_service(): void
    {
        $this->assertInstanceOf(WaveService::class, app('laravel-wave'));
    }

    #[Test]
    public function it_throws_when_access_token_is_missing(): void
    {
        config()->set('wave.access_token', null);

        $this->expectException(AuthenticationException::class);

        new WaveService;
    }

    #[Test]
    public function it_reads_business_id_from_config(): void
    {
        $this->assertSame('test-business-id', app(WaveService::class)->getBusinessId());
    }

    #[Test]
    public function get_customers_returns_collection_of_nodes(): void
    {
        Http::fake([
            '*' => Http::response([
                'data' => [
                    'business' => [
                        'customers' => [
                            'pageInfo' => ['currentPage' => 1, 'totalPages' => 1, 'totalCount' => 1],
                            'edges'    => [
                                ['node' => ['id' => 'c1', 'name' => 'Test Customer']],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $customers = app(WaveService::class)->getCustomers();

        $this->assertCount(1, $customers);
        $this->assertInstanceOf(Node::class, $customers->first());
        $this->assertSame('c1', $customers->first()['id']);
        $this->assertSame('Test Customer', $customers->first()->name);
    }

    #[Test]
    public function delete_customer_returns_true_on_success(): void
    {
        Http::fake([
            '*' => Http::response([
                'data' => ['customerDelete' => ['didSucceed' => true, 'inputErrors' => null]],
            ]),
        ]);

        $result = app(WaveService::class)->deleteCustomer('c1');

        $this->assertTrue($result);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return str_contains($body['query'], 'customerDelete')
                && $body['variables']['input']['customerId'] === 'c1'
                && $body['variables']['input']['businessId'] === 'test-business-id';
        });
    }

    #[Test]
    public function delete_customer_returns_false_on_failure(): void
    {
        Http::fake([
            '*' => Http::response([
                'data' => ['customerDelete' => ['didSucceed' => false, 'inputErrors' => []]],
            ]),
        ]);

        $this->assertFalse(app(WaveService::class)->deleteCustomer('c1'));
    }

    #[Test]
    public function it_sends_bearer_token_on_requests(): void
    {
        Http::fake([
            '*' => Http::response(['data' => ['user' => ['id' => 'u1']]]),
        ]);

        app(WaveService::class)->getUser();

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer test-token'));
    }
}
