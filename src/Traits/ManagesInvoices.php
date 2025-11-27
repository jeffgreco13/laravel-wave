<?php

namespace Jeffgreco13\Wave\Traits;

use Illuminate\Support\Collection;
use Jeffgreco13\Wave\Data\InvoiceSort;
use Jeffgreco13\Wave\Node;
use Jeffgreco13\Wave\QueryObject;

trait ManagesInvoices
{
    public function getInvoices(?array $variables = []): Collection
    {
        if (! isset($variables['sort'])) {
            $variables['sort'] = InvoiceSort::INVOICE_DATE_ASC;
        }
        // Merge with cached variables.
        $variables = array_merge($this->cachedVariables, $variables);
        // Save new cached variables.
        $this->cachedVariables = $variables;

        $businessId = $this->getBusinessId();
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
        $this->cachedResponse = $this->query($variables);

        return $this->getNodes();
    }

    public function getAllInvoices(?array $variables = []): Collection
    {
        $variables['page'] = 1;
        $variables['pageSize'] = 150;
        $allRecords = collect();
        do {
            $records = $this->getInvoices($variables);
            $allRecords = $allRecords->merge($records);
            $variables['page']++;
        } while ($this->hasNextPage());

        return $allRecords;
    }

    public function createInvoice(array $input): Node
    {
        $invoiceNode = QueryObject::invoice();

        $input['businessId'] = $this->getBusinessId();

        $this->cachedQuery = <<<GQL
            mutation InvoiceCreateInput(\$input: InvoiceCreateInput!) {
                invoiceCreate(input: \$input) {
                    invoice {
                        $invoiceNode
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

        return new Node(data_get($this->cachedResponse, 'data.invoiceCreate.invoice', []));
    }

    public function approveInvoice(string $invoiceId): bool
    {
        $input['invoiceId'] = $invoiceId;
        $this->cachedQuery = <<<'GQL'
            mutation InvoiceApproveInput($input: InvoiceApproveInput!) {
                invoiceApprove(input: $input) {
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

        return $this->didSucceed();
    }

    public function sendInvoice(array $input): bool
    {
        $this->cachedQuery = <<<'GQL'
            mutation InvoiceSendInput($input: InvoiceSendInput!) {
                invoiceSend(input: $input) {
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

        return $this->didSucceed();
    }
}
