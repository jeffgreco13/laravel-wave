<?php

namespace Jeffgreco13\Wave;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Jeffgreco13\Wave\Traits\ManagesBusinesses;
use Jeffgreco13\Wave\Traits\ManagesCurrencies;
use Jeffgreco13\Wave\Traits\ManagesCustomers;
use Jeffgreco13\Wave\Traits\ManagesInvoices;
use Jeffgreco13\Wave\Traits\ManagesProducts;
use Jeffgreco13\Wave\Traits\ManagesTaxes;
use RecursiveArrayIterator;
use RecursiveIteratorIterator;

class WaveService
{
    use ManagesBusinesses;
    use ManagesCurrencies;
    use ManagesCustomers;
    use ManagesInvoices;
    use ManagesProducts;
    use ManagesTaxes;

    protected $client = null;

    protected $url = null;

    protected $token = null;

    protected $businessId = null;

    protected $cachedMethod = null;

    protected $cachedVariables;

    protected $cachedQuery = null;

    protected $cachedResponse;

    protected $pageInfo = [];

    public function __construct()
    {
        $this->token = config('laravel-wave.access_token');
        if (is_null($this->token)) {
            throw new Exceptions\AuthenticationException;
        }

        $this->url = config('laravel-wave.graphql_uri');
        if (is_null($this->url)) {
            throw new Exceptions\InvalidDataException('Wave GraphQL URI is required.');
        }

        $this->businessId = config('laravel-wave.business_id');

        $this->client = Http::withToken($this->token)->asJson();

        // Set default variables at construct.
        $this->resetPagination();
    }

    public function resetPagination(): void
    {
        $this->cachedVariables = [
            'page' => 1,
            'pageSize' => 20,
        ];
    }

    // public function __call($method, $params)
    // {
    //     $this->cachedMethod = $method;
    //     $this->cachedVariables = $params;
    //     return self::$method();
    // }

    public function setBusinessId(string $businessId): void
    {
        $this->businessId = $businessId;
    }

    public function getBusinessId(): ?string
    {
        return $this->businessId;
    }

    public function getOAuthApplication(): Node
    {
        $oauthAppNode = QueryObject::OAuthApplication();
        $this->cachedQuery = <<<GQL
            query {
                oAuthApplication {
                    $oauthAppNode
                }
            }
            GQL;
        $this->cachedResponse = $this->query();

        return new Node(data_get($this->cachedResponse, 'data.oAuthApplication', []));
    }

    public function getUser(): Node
    {
        $userNode = QueryObject::user();
        $this->cachedQuery = <<<GQL
            query {
                user {
                    $userNode
                }
            }
            GQL;
        $this->cachedResponse = $this->query();

        return new Node(data_get($this->cachedResponse, 'data.user', []));
    }

    public function getPageInfo()
    {
        return $this->pageInfo;
    }

    public function isLastPage()
    {
        return data_get($this->pageInfo, 'currentPage', 1) == data_get($this->pageInfo, 'totalPages', 1);
    }

    public function hasNextPage()
    {
        return data_get($this->pageInfo, 'currentPage', 1) < data_get($this->pageInfo, 'totalPages', 1);
    }

    public function nextPage(): void
    {
        $this->cachedVariables['page']++;
    }

    public function prevPage(): void
    {
        $this->cachedVariables['page'] = $this->cachedVariables['page'] == 1 ? 1 : $this->cachedVariables['page'] - 1;
    }

    public function didSucceed()
    {
        return $this->recursiveFindByKey($this->cachedResponse, 'didSucceed');
    }

    public function getCachedResponse()
    {
        return $this->cachedResponse;
    }

    public function getNodes(): Collection
    {
        $edges = collect($this->recursiveFindByKey($this->cachedResponse, 'edges') ?? []);

        return $edges->pluck('node')->map(function ($item) {
            return new Node($item);
        });
    }

    public function rawQuery(string $query, ?array $variables = [])
    {
        $this->cachedQuery = $query;

        return $this->query($variables);
    }

    protected function query(array $variables = []): mixed
    {
        $payload = ['query' => $this->cachedQuery];

        if (! empty($variables)) {
            $payload['variables'] = $variables;
        }

        $response = $this->client->post($this->url, $payload);

        if ($response->failed()) {
            $errors = collect(data_get($response->json(), 'errors', []));
            $error = $errors->first();
            $code = data_get($error, 'extensions.code', null);
            $message = data_get($error, 'message', null);
            switch ($code) {
                case 'GRAPHQL_VALIDATION_FAILED':
                    throw new Exceptions\MalformedQueryException("Malformed GraphQL query: {$message}");
                    break;
                case 'NOT_FOUND':
                    throw new Exceptions\ResourceNotFoundException("Resource not found: {$message}");
                    break;
                case 'UNAUTHENTICATED':
                    throw new Exceptions\AuthenticationException("Authentication failed: {$message}");
                    break;
                case 'INTERNAL_SERVER_ERROR':
                    throw new Exceptions\ExecutionException("Execution error: {$message}");
                    break;
                case 'VARIABLE_VALUE':
                    throw new \Exception("Incorrect values provided: {$message}");
                default:
                    throw new \Exception('Wave GraphQL request failed with an unknown error.');
            }
        }

        $this->cachedResponse = $response->json();
        $this->pageInfo = $this->recursiveFindByKey(haystack: $this->cachedResponse, needle: 'pageInfo');

        return $this->cachedResponse;
    }

    private function recursiveFindByKey(array $haystack, $needle)
    {
        $iterator = new RecursiveArrayIterator($haystack);
        $recursive = new RecursiveIteratorIterator(
            $iterator,
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($recursive as $key => $value) {
            if ($key === $needle) {
                return $value;
            }
        }

        return null;
    }
}
