<?php

namespace Jeffgreco13\Wave\Traits;

use Illuminate\Support\Collection;
use Jeffgreco13\Wave\Node;
use Jeffgreco13\Wave\QueryObject;

trait ManagesAccounts
{
    public function getAccounts(?array $variables = []): Collection
    {
        $variables = array_merge($this->cachedVariables, $variables);
        $this->cachedVariables = $variables;

        $businessId = $this->getBusinessId();
        $pageInfoNode = QueryObject::pageInfo();
        $accountNode = QueryObject::account();

        $this->cachedQuery = <<<GQL
            query(\$page: Int, \$pageSize: Int) {
                business(id: "{$businessId}") {
                    accounts(page: \$page, pageSize: \$pageSize) {
                        pageInfo {
                            $pageInfoNode
                        }
                        edges {
                            node {
                                $accountNode
                            }
                        }
                    }
                }
            }
            GQL;
        $this->cachedResponse = $this->query($variables);

        return $this->getNodes();
    }

    public function getAllAccounts(?array $variables = []): Collection
    {
        $variables['page'] = 1;
        $variables['pageSize'] = 200;
        $allRecords = collect();
        do {
            $records = $this->getAccounts($variables);
            $allRecords = $allRecords->merge($records);
            $variables['page']++;
        } while ($this->hasNextPage());

        return $allRecords;
    }

    public function getAccount(string $accountId): Node
    {
        $businessId = $this->getBusinessId();
        $accountNode = QueryObject::account();

        $this->cachedQuery = <<<GQL
            query {
                business(id: "{$businessId}") {
                    account(id: "{$accountId}") {
                        $accountNode
                    }
                }
            }
            GQL;
        $this->cachedResponse = $this->query();

        return new Node(data_get($this->cachedResponse, 'data.business.account', []));
    }
}
