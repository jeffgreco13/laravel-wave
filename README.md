# Laravel Wave

A wrapper to use the [Wave][wave-app]'s graphql api in your laravel apps. This package was originally forked from [subbe/waveapp][subbe-waveapp] and adds some QOL improvements for Laravel devs.

Wave API documentation can be located at:

- [Wave - Developer Portal][wave-documentation-url]
- [API Reference][wave-api-reference]

## Application Setup

To use Laravel Wave, you will need to [create an app][wave-create-an-app] on the developer portal.

After you have created a new app, click in to edit its settings. Create a new Full Access token and copy this to a save place. You will need this in your .env

OAuth flow is not supported by this package. Consider using the [Socialite Wave Provider][socialite-wave] then pass the Access Token to the Wave class at runtime.

## Installation

Require the package using composer:

```bash
composer require jeffgreco13/laravel-wave
```

Update your .env file to include:

```
WAVE_ACCESS_TOKEN= *your full access token*
WAVE_BUSINESS_ID= *ID for the business you wish to interact with*
WAVE_GRAPHQL_URI= *defaults to https://gql.waveapps.com/graphql/public*
```

If you do not know the ID for your business, you can use the following tinker command:

```bash
php artisan tinker
> (new \Jeffgreco13\Wave\WaveService())->getBusinesses()
```

## Usage

### Query

```php
use Jeffgreco13\Wave\WaveService;

$wave = new WaveService();

$businesses = $wave->getBusinesses();
```

or, with parameters...

```php
use Jeffgreco13\Wave\WaveService;

$wave = new WaveService();

$invoices = $wave->getInvoices([
    "page" => 5,
    "pageSize" => 20
    "sort" => InvoiceSort::MODIFIED_AT_DESC,
    "modifiedAtStart" => now()->copy()->subHours(24)->toIso8601String()
]);
```

### Pagination

Queries like `businesses` and `customers` may require pagination. Some shortcut methods exist:

```php
use Jeffgreco13\Wave\WaveService;

$wave = new WaveService();

$allInvoices = $wave->getAllInvoices([
  "modifiedAtStart" => now()
    ->subHours(5)
    ->toISOString()
]);
```

Or, you can create your own loop:

```php
use Jeffgreco13\Wave\WaveService;

$wave = new WaveService();

$allRecords = collect();
$parameters = [
    "page" => 1,
    "pageSize" => 100
];
do {
    $records = $wave->getInvoices($parameters);
    $allRecords = $allRecords->merge($records);
    $parameters["page"]++;
} while ($wave->hasNextPage());
```

### Mutation

Refer to the Wave [API Reference](https://developer.waveapps.com/hc/en-us/articles/360019968212-API-Reference#invoicecreateinput) for input formats and required fields.

```php
use Jeffgreco13\Wave\WaveService;

$wave = new WaveService();

$invoice = $wave->createInvoice([
    // 'businessId' => 'Qwxyz...'
    "customerId" => "Qwxyz...",
    "invoiceDate" => "yyyy-MM-dd",
    "items" => [
        [
            "productId" => "Qwxyz...",
            //
            "quantity" => 2.5, // Supports decimal. Default: 1
            "unitPrice" => 115.50, // Default: price set in Wave for this product.
            "taxes" => ["QwSalesTaxId..."] // Default: tax(es) set in Wave for this product.
        ],

        // More line items can go here...

    ]
]);

// Approve the invoice
$wave->approveInvoice($invoice->id);

// Send the invoice
$wave->sendInvoice([
    "invoiceId" => $invoice->id,
    "to" => [
        "customer@email.com"
    ]
])
```

### Raw Query

You can perform any raw query or mutation like so:

```php
use Jeffgreco13\Wave\WaveService;

$wave = new WaveService();

$graphQl = <<<GQL
    mutation CustomerCreateInput(\$input: CustomerCreateInput!) {
        customerCreate(input: \$input) {
            customer {
                id
                name
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
$variables = [
    "input" => [
        "name" => "Business name"
    ]
];
$response = $wave->rawQuery($graphQl,$variables);

```

### Available methods

- rawQuery(string $query, ?array $variables)

- getUser()
- getAllCountries()
- getAllCurrencies()

- getAllBusinesses()
- getBusiness(?string $id)

- getAllProducts()

- getAllTaxes()

- getCustomers()
- getAllCustomers()
- createCustomer(array $input)
- patchCustomer(array $input)

- getInvoices()
- getAllInvoices()
- createInvoice(array $input)
- approveInvoice(string $invoiceId)
- sendInvoice(array $input)

### Currency

A simple way to download Wave's currencies and cache them for use in your app:

First run the artisan command. This downloads the static currencies to a json file and saves them in your storage path:

```
php artisan wave:pull-currencies
```
You may now use the Currency class like so:

```php
use Jeffgreco13\Wave\WaveCurrency;

$currencies = WaveCurrency::all(); // returns a Collection of Currency objects

$currency = WaveCurrency::firstWhere("code","ARS"); // returns a single Currency object if found, or null
echo $currency->name; // output: Argentinian peso

// Currency array attributes
array:5 [
  "code" => "ARS"
  "symbol" => "$"
  "name" => "Argentinian peso"
  "plural" => "Argentinian pesos"
  "exponent" => 2
]

```

## Contributing

Pull requests are welcome. For major changes, please open an issue first to discuss what you would like to change.

## License

[MIT](./LICENSE.md)

[wave-app]: https://www.waveapps.com/

[wave-documentation-url]: https://developer.waveapps.com/hc/en-us/categories/360001114072

[wave-api-reference]: https://developer.waveapps.com/hc/en-us/articles/360019968212-API-Reference

[wave-create-an-app]: https://developer.waveapps.com/hc/en-us/articles/360019762711

[socialite-wave]: https://github.com/SocialiteProviders/Providers/tree/master/src/Wave

[subbe-waveapp]: https://github.com/subbe/waveapp
