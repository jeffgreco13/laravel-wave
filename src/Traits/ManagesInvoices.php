<?php

namespace Jeffgreco13\Wave\Traits;

use Jeffgreco13\Wave\QueryObject;
use Illuminate\Support\Collection;
use Jeffgreco13\Wave\Data\InvoiceSort;

trait ManagesInvoices
{
    public function getInvoices(): Collection
    {
        // PAGINATION
        // need to modify the query to support pagination

        $businessId = $id ?? $this->getBusinessId();
        $pageInfoNode = QueryObject::pageInfo();
        $invoiceNode = QueryObject::invoice();

        $this->cachedQuery = <<<GQL
            query(\$page: Int, \$pageSize: Int, \$sort: [InvoiceSort!]!, \$modifiedAtAfter: DateTime, \$modifiedAtBefore: DateTime) {
                business(id: "{$businessId}") {
                    invoices(page: \$page, pageSize: \$pageSize, sort: \$sort, modifiedAtAfter: \$modifiedAtAfter,modifiedAtBefore: \$modifiedAtBefore) {
                        pageInfo {
                            $pageInfoNode
                        }
                        edges {
                            node {
                                $invoiceNode
                            }
                        }
                    }
                }
            }
            GQL;
        $this->cachedResponse = $this->query([
            'page' => 1,
            'pageSize' => 2,
            'sort' => InvoiceSort::INVOICE_DATE_DESC
        ]);
        return $this->getNodes();
    }
}
