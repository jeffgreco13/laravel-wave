<?php

namespace Jeffgreco13\Wave\Traits;

use Jeffgreco13\Wave\Node;
use Jeffgreco13\Wave\QueryObject;
use Illuminate\Support\Collection;

trait ManagesTaxes
{
    public function getAllTaxes(): Collection
    {
        // Returns 200 taxes
        $businessId = $id ?? $this->getBusinessId();
        $salesTax = QueryObject::salesTax();

        $this->cachedQuery = <<<GQL
            query {
                business(id: "{$businessId}") {
                    salesTaxes(pageSize: 200) {
                        edges {
                            node {
                                $salesTax
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
