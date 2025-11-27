<?php

namespace Jeffgreco13\Wave\Traits;

use Jeffgreco13\Wave\Node;
use Jeffgreco13\Wave\QueryObject;
use Illuminate\Support\Collection;

trait ManagesProducts
{
    public function getAllProducts(): Collection
    {
        // Returns 200 products
        $businessId = $id ?? $this->getBusinessId();
        $productNode = QueryObject::product();

        $this->cachedQuery = <<<GQL
            query {
                business(id: "{$businessId}") {
                    products(pageSize: 200) {
                        edges {
                            node {
                                $productNode
                            }
                        }
                    }
                }
            }
            GQL;
        $this->cachedResponse = $this->query();
        return $this->getNodes();

    }
}
