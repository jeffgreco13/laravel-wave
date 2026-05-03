<?php

namespace Jeffgreco13\Wave\Traits;

use Illuminate\Support\Collection;
use Jeffgreco13\Wave\Node;
use Jeffgreco13\Wave\QueryObject;

trait ManagesBusinesses
{
    public function getAllBusinesses(): Collection
    {
        // We will rarely or never see a user with more than 200 businesses in Wave. This returns all of them.
        $pageInfoNode = QueryObject::pageInfo();
        $businessNode = QueryObject::business();
        $this->cachedQuery = <<<GQL
            query {
                businesses(pageSize: 200) {
                    pageInfo {
                        $pageInfoNode
                    }
                    edges {
                        node {
                            $businessNode
                        }
                    }
                }
            }
            GQL;

        $this->cachedResponse = $this->query();

        return $this->getNodes();

    }

    public function getBusiness(?string $id = null)
    {
        $businessId = $id ?? $this->getBusinessId();
        $business = QueryObject::business();

        $this->cachedQuery = <<<GQL
            query {
                business(id: "{$businessId}") {
                    $business
                }
            }
            GQL;
        $this->cachedResponse = $this->query();

        return new Node(data_get($this->cachedResponse, 'data.business', []));
    }
}
