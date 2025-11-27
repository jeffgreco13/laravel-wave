<?php

namespace Jeffgreco13\Wave\Traits;

use Illuminate\Support\Collection;
use Jeffgreco13\Wave\QueryObject;

trait ManagesCurrencies
{
    public function getAllCurrencies(): Collection
    {
        $currencyNode = QueryObject::currency();

        $this->cachedQuery = <<<GQL
            query {
                currencies {
                    $currencyNode
                }
            }
            GQL;
        $response = $this->query();

        return collect(data_get($this->cachedResponse, 'data.currencies', []) ?? []);

    }

    public function getAllCountries(): Collection
    {
        $countryNode = QueryObject::country();

        $this->cachedQuery = <<<GQL
            query {
                countries {
                    $countryNode
                }
            }
            GQL;
        $response = $this->query();

        return collect(data_get($this->cachedResponse, 'data.countries', []) ?? []);

    }
}
