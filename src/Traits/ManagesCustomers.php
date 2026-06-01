<?php

namespace Jeffgreco13\Wave\Traits;

use Illuminate\Support\Collection;
use Jeffgreco13\Wave\Data\CustomerSort;
use Jeffgreco13\Wave\Node;
use Jeffgreco13\Wave\QueryObject;

trait ManagesCustomers
{
    public function getCustomers(?array $variables = []): Collection
    {
        if (! isset($variables['sort'])) {
            $variables['sort'] = CustomerSort::NAME_ASC;
        }
        // Merge with cached variables.
        $variables = array_merge($this->cachedVariables, $variables);
        // Save new cached variables.
        $this->cachedVariables = $variables;

        $businessId = $this->getBusinessId();
        $pageInfoNode = QueryObject::pageInfo();
        $customerNode = QueryObject::customer();

        $this->cachedQuery = <<<GQL
            query(\$email: String, \$page: Int, \$pageSize: Int, \$sort: [CustomerSort!]!, \$modifiedAtAfter: DateTime, \$modifiedAtBefore: DateTime) {
                business(id: "{$businessId}") {
                    customers(email: \$email, page: \$page, pageSize: \$pageSize, sort: \$sort, modifiedAtAfter: \$modifiedAtAfter,modifiedAtBefore: \$modifiedAtBefore) {
                        pageInfo {
                            $pageInfoNode
                        }
                        edges {
                            node {
                                $customerNode
                            }
                        }
                    }
                }
            }
            GQL;
        $this->cachedResponse = $this->query($variables);

        return $this->getNodes();
    }

    public function getAllCustomers(?array $variables = []): Collection
    {
        $variables['page'] = 1;
        $variables['pageSize'] = 150;
        $allRecords = collect();
        do {
            $records = $this->getCustomers($variables);
            $allRecords = $allRecords->merge($records);
            $variables['page']++;
        } while ($this->hasNextPage());

        return $allRecords;
    }

    public function createCustomer(array $input): Node
    {
        $customer = QueryObject::customer();

        $input['businessId'] = $this->getBusinessId();

        $this->cachedQuery = <<<GQL
            mutation CustomerCreateInput(\$input: CustomerCreateInput!) {
                customerCreate(input: \$input) {
                    customer {
                        $customer
                    }
                    didSucceed
                    inputErrors {
                        path
                        message
                        code
                    }
                }
            }
            GQL;
        $this->cachedResponse = $this->query(['input' => $input]);

        return new Node(data_get($this->cachedResponse, 'data.customerCreate.customer', []));
    }

    public function deleteCustomer(string $customerId): bool
    {
        $this->cachedQuery = <<<'GQL'
            mutation CustomerDeleteInput($input: CustomerDeleteInput!) {
                customerDelete(input: $input) {
                    didSucceed
                    inputErrors {
                        path
                        message
                        code
                    }
                }
            }
            GQL;
        $this->cachedResponse = $this->query([
            'input' => [
                'businessId' => $this->getBusinessId(),
                'customerId' => $customerId,
            ],
        ]);

        return (bool) data_get($this->cachedResponse, 'data.customerDelete.didSucceed', false);
    }

    public function patchCustomer(array $input): Node
    {
        $customer = QueryObject::customer();

        $this->cachedQuery = <<<GQL
            mutation CustomerPatchInput(\$input: CustomerPatchInput!) {
                customerPatch(input: \$input) {
                    customer {
                        $customer
                    }
                    didSucceed
                    inputErrors {
                        path
                        message
                        code
                    }
                }
            }
            GQL;
        $this->cachedResponse = $this->query(['input' => $input]);

        return new Node(data_get($this->cachedResponse, 'data.customerPatch.customer', []));
    }
}
