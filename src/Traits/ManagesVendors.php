<?php

namespace Jeffgreco13\Wave\Traits;

use Illuminate\Support\Collection;
use Jeffgreco13\Wave\QueryObject;

trait ManagesVendors
{
    public function getVendors(?array $variables = []): Collection
    {
        $variables = array_merge($this->cachedVariables, $variables);
        $this->cachedVariables = $variables;

        $businessId = $this->getBusinessId();
        $pageInfoNode = QueryObject::pageInfo();
        $vendorNode = QueryObject::vendor();

        $this->cachedQuery = <<<GQL
            query(\$page: Int, \$pageSize: Int) {
                business(id: "{$businessId}") {
                    vendors(page: \$page, pageSize: \$pageSize) {
                        pageInfo {
                            $pageInfoNode
                        }
                        edges {
                            node {
                                $vendorNode
                            }
                        }
                    }
                }
            }
            GQL;
        $this->cachedResponse = $this->query($variables);

        return $this->getNodes();
    }

    public function getAllVendors(?array $variables = []): Collection
    {
        $variables['page'] = 1;
        $variables['pageSize'] = 150;
        $allRecords = collect();
        do {
            $records = $this->getVendors($variables);
            $allRecords = $allRecords->merge($records);
            $variables['page']++;
        } while ($this->hasNextPage());

        return $allRecords;
    }
}
