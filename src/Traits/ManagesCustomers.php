<?php

namespace Jeffgreco13\Wave\Traits;

use Jeffgreco13\Wave\QueryObject;
use Illuminate\Support\Collection;
use Jeffgreco13\Wave\Data\CustomerSort;

trait ManagesCustomers
{
    public function getCustomers(): Collection
    {
        // PAGINATION
        // need to modify the query to support pagination

        $businessId = $id ?? $this->getBusinessId();
        $pageInfoNode = QueryObject::pageInfo();
        $customerNode = QueryObject::customer();

        $this->cachedQuery = <<<GQL
            query(\$email: String, \$page: Int, \$pageSize: Int, \$sort: 	[CustomerSort!]!, \$modifiedAtAfter: DateTime, \$modifiedAtBefore: DateTime) {
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
        $this->cachedResponse = $this->query([
            'page' => 1,
            'pageSize' => 2,
            // 'email' => 'Thesugarwood@outlook.com',
            'sort' => CustomerSort::NAME_ASC
        ]);
        return $this->getNodes();
    }
}
