<?php

namespace Jeffgreco13\Wave\Traits;

use Jeffgreco13\Wave\Node;
use Jeffgreco13\Wave\QueryObject;
use Illuminate\Support\Collection;

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
        return collect(data_get($this->cachedResponse,'data.currencies',[]) ?? []);

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
        return collect(data_get($this->cachedResponse,'data.countries',[]) ?? []);

    }
}
