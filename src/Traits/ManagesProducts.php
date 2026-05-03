<?php

namespace Jeffgreco13\Wave\Traits;

use Illuminate\Support\Collection;
use Jeffgreco13\Wave\QueryObject;

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
