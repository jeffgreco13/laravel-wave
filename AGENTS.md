# Laravel Wave — Agent Reference

Laravel Wave is a Laravel package that wraps the [Wave](https://www.waveapps.com/) accounting app's GraphQL API. It provides a typed PHP interface for querying businesses, customers, invoices, products, taxes, and currencies, and for performing mutations such as creating and sending invoices.

## Package structure

```
src/
  WaveService.php           # Main entry point — all public methods live here via traits
  WaveServiceProvider.php   # Registers the service and publishes config
  WaveCurrency.php          # Static currency helper (reads from cached local JSON)
  Node.php                  # Lightweight wrapper returned by single-resource queries
  QueryObject.php           # GraphQL field selection strings (internal)
  Traits/
    ManagesBusinesses.php
    ManagesCurrencies.php
    ManagesCustomers.php
    ManagesInvoices.php
    ManagesProducts.php
    ManagesTaxes.php
  Data/                     # Enum-style constant classes for filter/sort values
    CustomerSort.php
    InvoiceSort.php
    InvoiceStatus.php
    InvoiceSendMethod.php
    InvoiceCreateStatus.php
    ProductSort.php
    BalanceType.php
    TransactionDirection.php
    TransactionOrigin.php
    BusinessType.php
    BusinessSubType.php
    OrganizationalType.php
    AccountNormalBalanceType.php
    AccountSubtypeValue.php
    AccountTypeValue.php
  Exceptions/
    AuthenticationException.php
    ExecutionException.php
    InvalidDataException.php
    MalformedQueryException.php
    ResourceNotFoundException.php
  Commands/
    PullWaveCurrencies.php  # Artisan command: wave:pull-currencies
```

## Configuration

Publish the config file once after installation:

```bash
php artisan vendor:publish --tag=config
```

Required `.env` keys:

| Key | Required | Default | Description |
|---|---|---|---|
| `WAVE_ACCESS_TOKEN` | Yes | — | Full Access token from the Wave Developer Portal |
| `WAVE_BUSINESS_ID` | Conditional | — | The Wave business ID to operate against; can be set at runtime instead |
| `WAVE_GRAPHQL_URI` | No | `https://gql.waveapps.com/graphql/public` | Wave GraphQL endpoint |

The constructor throws `AuthenticationException` if `WAVE_ACCESS_TOKEN` is missing and `InvalidDataException` if `WAVE_GRAPHQL_URI` is missing.

## Instantiation

```php
use Jeffgreco13\Wave\WaveService;

$wave = new WaveService();
```

The constructor reads config automatically. No facade is registered, so always instantiate directly.

## Return types

**`Node`** — returned by single-resource queries (e.g. `getBusiness`, `createInvoice`). Implements `ArrayAccess`. Access fields via property syntax or array syntax:

```php
$invoice = $wave->createInvoice([...]);
$invoice->id;           // property access
$invoice['status'];     // array access
$invoice->toArray();    // full array
```

**`Illuminate\Support\Collection`** — returned by list queries (e.g. `getInvoices`, `getAllCustomers`). Each item in the collection is a `Node`.

---

## Method reference

### WaveService — core / utility

#### `setBusinessId(string $businessId): void`
Overrides the business ID from config at runtime. Useful when serving multiple Wave businesses from a single app.

```php
$wave->setBusinessId('QnVzaW5lc3M6...');
```

#### `getBusinessId(): ?string`
Returns the currently active business ID (from config or last `setBusinessId` call).

#### `getUser(): Node`
Returns the authenticated Wave user.

Fields: `id`, `defaultEmail`, `firstName`, `lastName`, `createdAt`, `modifiedAt`.

#### `getOAuthApplication(): Node`
Returns the OAuth application associated with the access token.

Fields: `id`, `name`, `description`, `clientId`, `logoUrl`, `extraData`, `createdAt`, `modifiedAt`.

#### `rawQuery(string $query, ?array $variables = []): mixed`
Executes an arbitrary GraphQL query or mutation. Returns the raw decoded JSON array. Use this for any Wave API operation not covered by a built-in method.

```php
$result = $wave->rawQuery(<<<GQL
    query {
        user { id defaultEmail }
    }
    GQL
);
```

With variables:

```php
$result = $wave->rawQuery(
    'mutation CustomerCreateInput($input: CustomerCreateInput!) {
        customerCreate(input: $input) { didSucceed }
    }',
    ['input' => ['businessId' => '...', 'name' => 'Acme']]
);
```

#### `getCachedResponse(): mixed`
Returns the raw JSON array from the most recent API call. Useful after calling methods that return a `Node` or `bool` when you need the full response.

#### `didSucceed(): bool`
Searches the last cached response recursively for the `didSucceed` field. Use after mutations that return a boolean (`approveInvoice`, `sendInvoice`) to check for failures without calling `getCachedResponse`.

---

### Pagination helpers

All paginated list methods (`getInvoices`, `getInvoicesByCustomer`, `getCustomers`) respect the following helpers. Pagination state is maintained internally between calls.

#### `getPageInfo(): array`
Returns `currentPage`, `totalPages`, `totalCount` from the last paginated response.

#### `hasNextPage(): bool`
Returns `true` if there are more pages after the current one.

#### `isLastPage(): bool`
Returns `true` if the current page is the last one.

#### `nextPage(): void`
Increments the internal page counter by 1.

#### `prevPage(): void`
Decrements the internal page counter by 1 (minimum 1).

#### `resetPagination(): void`
Resets internal pagination state to `page=1, pageSize=20`.

**Manual pagination loop pattern:**

```php
$allRecords = collect();
$params = ['page' => 1, 'pageSize' => 100];
do {
    $records = $wave->getInvoices($params);
    $allRecords = $allRecords->merge($records);
    $params['page']++;
} while ($wave->hasNextPage());
```

---

### Businesses

#### `getAllBusinesses(): Collection<Node>`
Returns up to 200 businesses accessible to the authenticated user. No parameters.

Each `Node` has: `id`, `name`, `isPersonal`, `organizationalType`, `type` (name, value), `subtype` (name, value), `currency`, `timezone`, `address`, `phone`, `fax`, `mobile`, `tollFree`, `website`, `isClassicAccounting`, `isClassicInvoicing`, `isArchived`, `createdAt`, `modifiedAt`.

```php
$businesses = $wave->getAllBusinesses();
$wave->setBusinessId($businesses->first()->id);
```

#### `getBusiness(?string $id = null): Node`
Returns a single business. Falls back to the configured business ID when `$id` is omitted.

```php
$business = $wave->getBusiness();         // uses WAVE_BUSINESS_ID
$business = $wave->getBusiness('QnVz...'); // explicit ID
```

---

### Customers

#### `getCustomers(?array $variables = []): Collection<Node>`
Returns a paginated page of customers for the active business.

| Parameter | Type | Default | Description |
|---|---|---|---|
| `page` | int | 1 | Page number |
| `pageSize` | int | 20 | Results per page |
| `sort` | `CustomerSort::*` | `NAME_ASC` | Sort order |
| `email` | string | — | Filter by exact email address |
| `modifiedAtAfter` | DateTime string | — | Filter to records modified after this timestamp |
| `modifiedAtBefore` | DateTime string | — | Filter to records modified before this timestamp |

```php
use Jeffgreco13\Wave\Data\CustomerSort;

$customers = $wave->getCustomers([
    'pageSize' => 50,
    'sort'     => CustomerSort::MODIFIED_AT_DESC,
    'email'    => 'jane@example.com',
]);
```

Each `Node` has: `id`, `name`, `firstName`, `lastName`, `displayId`, `email`, `mobile`, `phone`, `fax`, `tollFree`, `website`, `internalNotes`, `address`, `currency`, `shippingDetails`, `outstandingAmount`, `overdueAmount`, `isArchived`, `createdAt`, `modifiedAt`.

#### `getAllCustomers(?array $variables = []): Collection<Node>`
Automatically pages through all customers (150 per page) and returns a flat merged Collection. Accepts the same filter parameters as `getCustomers` except `page` and `pageSize` (those are managed internally).

```php
$allCustomers = $wave->getAllCustomers([
    'modifiedAtAfter' => now()->subDays(7)->toIso8601String(),
]);
```

#### `createCustomer(array $input): Node`
Creates a new customer. `businessId` is injected automatically.

Required Wave API fields: `name`. See the [Wave API reference](https://developer.waveapps.com/hc/en-us/articles/360019968212-API-Reference) for optional fields (`email`, `address`, `currency`, etc.).

```php
$customer = $wave->createCustomer([
    'name'  => 'Acme Corp',
    'email' => 'billing@acme.com',
]);
echo $customer->id;
```

#### `patchCustomer(array $input): Node`
Updates an existing customer. `id` must be included in `$input`. Only fields provided are updated (patch semantics).

```php
$customer = $wave->patchCustomer([
    'id'    => 'Q3VzdG9tZXI6...',
    'email' => 'new@acme.com',
]);
```

---

### Invoices

#### `getInvoices(?array $variables = []): Collection<Node>`
Returns a paginated page of invoices for the active business.

| Parameter | Type | Default | Description |
|---|---|---|---|
| `page` | int | 1 | Page number |
| `pageSize` | int | 20 | Results per page |
| `sort` | `InvoiceSort::*` | `INVOICE_DATE_ASC` | Sort order |
| `modifiedAtAfter` | DateTime string | — | Filter to invoices modified after this timestamp |
| `modifiedAtBefore` | DateTime string | — | Filter to invoices modified before this timestamp |

```php
use Jeffgreco13\Wave\Data\InvoiceSort;

$invoices = $wave->getInvoices([
    'pageSize'        => 50,
    'sort'            => InvoiceSort::MODIFIED_AT_DESC,
    'modifiedAtAfter' => now()->subHours(24)->toIso8601String(),
]);
```

Each `Node` has: `id`, `status`, `invoiceNumber`, `poNumber`, `invoiceDate`, `dueDate`, `title`, `subhead`, `memo`, `footer`, `pdfUrl`, `viewUrl`, `exchangeRate`, `customer`, `currency`, `amountDue`, `amountPaid`, `taxTotal`, `total`, `items`, `lastSentAt`, `lastSentVia`, `lastViewedAt`, `disableCreditCardPayments`, `disableBankPayments`, `disableAmexPayments`, `createdAt`, `modifiedAt`.

#### `getInvoicesByCustomer(string $customerId, ?array $variables = []): Collection<Node>`
Returns a paginated page of invoices filtered to a specific customer. Accepts the same sort/filter parameters as `getInvoices`.

```php
$invoices = $wave->getInvoicesByCustomer('Q3VzdG9tZXI6...', [
    'sort' => InvoiceSort::DUE_AT_ASC,
]);
```

#### `getAllInvoices(?array $variables = []): Collection<Node>`
Automatically pages through all invoices (150 per page) and returns a flat merged Collection. Accepts the same filter parameters as `getInvoices` except `page` and `pageSize`.

```php
$recent = $wave->getAllInvoices([
    'modifiedAtAfter' => now()->subDays(30)->toIso8601String(),
]);
```

#### `createInvoice(array $input): Node`
Creates a new invoice. `businessId` is injected automatically. Returns the full invoice `Node`.

Required fields: `customerId`, `invoiceDate`. Common optional fields: `items`, `dueDate`, `memo`, `footer`, `title`.

`items` array structure:

| Field | Type | Description |
|---|---|---|
| `productId` | string | Wave product ID |
| `description` | string | Line item description (overrides product default) |
| `quantity` | float | Quantity (supports decimals, default 1) |
| `unitPrice` | float | Price per unit (overrides product default) |
| `taxes` | string[] | Array of sales tax IDs to apply |

```php
$invoice = $wave->createInvoice([
    'customerId'  => 'Q3VzdG9tZXI6...',
    'invoiceDate' => now()->toDateString(),  // 'YYYY-MM-DD'
    'dueDate'     => now()->addDays(30)->toDateString(),
    'memo'        => 'Thank you for your business.',
    'items'       => [
        [
            'productId' => 'UHJvZHVjdDo...',
            'quantity'  => 2,
            'unitPrice' => 150.00,
            'taxes'     => ['U2FsZXNUYXg6...'],
        ],
    ],
]);
echo $invoice->id;
echo $invoice->status; // 'DRAFT'
```

#### `approveInvoice(string $invoiceId): bool`
Moves an invoice from `DRAFT` to `SAVED`/`SENT` status so it can be sent. Returns `true` on success.

```php
$ok = $wave->approveInvoice($invoice->id);
```

#### `sendInvoice(array $input): bool`
Sends an approved invoice to one or more recipients. Returns `true` on success.

Required fields: `invoiceId`, `to` (array of email strings).

```php
use Jeffgreco13\Wave\Data\InvoiceSendMethod;

$ok = $wave->sendInvoice([
    'invoiceId' => $invoice->id,
    'to'        => ['client@example.com'],
    'subject'   => 'Your invoice from Acme',
    'message'   => 'Please find your invoice attached.',
    'sendMethod'=> InvoiceSendMethod::WAVE,
]);
```

---

### Products

#### `getAllProducts(): Collection<Node>`
Returns up to 200 products for the active business. No filter or sort parameters.

Each `Node` has: `id`, `name`, `description`, `unitPrice`, `isSold`, `isBought`, `incomeAccount` (id, name), `expenseAccount` (id, name), `defaultSalesTaxes` (id, name), `isArchived`, `createdAt`, `modifiedAt`.

```php
$products = $wave->getAllProducts();
```

---

### Taxes

#### `getAllTaxes(): Collection<Node>`
Returns up to 200 sales taxes for the active business. No parameters.

Each `Node` has: `id`, `name`, `abbreviation`, `description`, `taxNumber`, `showTaxNumberOnInvoices`, `rate`, `rates` (effective, rate), `isCompound`, `isRecoverable`, `isArchived`, `createdAt`, `modifiedAt`.

```php
$taxes = $wave->getAllTaxes();
$gstId = $taxes->firstWhere('abbreviation', 'GST')?->id;
```

---

### Currencies & countries

#### `getAllCurrencies(): Collection`
Returns a Collection of raw currency arrays from the Wave API (not `Node` objects). Fields: `code`, `symbol`, `name`, `plural`, `exponent`.

Prefer using `WaveCurrency` (see below) for repeated access rather than hitting the API each time.

#### `getAllCountries(): Collection`
Returns a Collection of raw country arrays. Fields: `code`, `name`, `nameWithArticle`, `currency`, `provinces` (code, name).

---

## WaveCurrency — local currency lookup

`WaveCurrency` reads from a cached local JSON file created by the `wave:pull-currencies` Artisan command. It does not call the Wave API at runtime.

### Setup

Run once to download and persist the currency list:

```bash
php artisan wave:pull-currencies
```

This saves `storage/wave_currencies.json` and populates Laravel's cache under the `currencies` key forever.

### `WaveCurrency::all(): Collection<WaveCurrency>`
Returns all currencies as a Collection of `WaveCurrency` objects.

```php
use Jeffgreco13\Wave\WaveCurrency;

$currencies = WaveCurrency::all();
```

### `WaveCurrency::firstWhere(string $key, string $value): ?WaveCurrency`
Finds the first currency matching a key/value pair. Returns `null` if not found.

```php
$ars = WaveCurrency::firstWhere('code', 'ARS');
echo $ars->name;   // "Argentinian peso"
echo $ars->symbol; // "$"
```

Each `WaveCurrency` object exposes: `code`, `symbol`, `name`, `plural`, `exponent`. Access via property or array syntax. Implements `Arrayable` (`toArray()`).

---

## Constant/enum classes

Use these classes instead of raw strings to avoid typos and enable IDE completion.

### `InvoiceSort`
`AMOUNT_DUE_ASC`, `AMOUNT_DUE_DESC`, `AMOUNT_PAID_ASC`, `AMOUNT_PAID_DESC`, `CREATED_AT_ASC`, `CREATED_AT_DESC`, `CUSTOMER_NAME_ASC`, `CUSTOMER_NAME_DESC`, `DUE_AT_ASC`, `DUE_AT_DESC`, `INVOICE_DATE_ASC`, `INVOICE_DATE_DESC`, `INVOICE_NUMBER_ASC`, `INVOICE_NUMBER_DESC`, `MODIFIED_AT_ASC`, `MODIFIED_AT_DESC`, `STATUS_ASC`, `STATUS_DESC`, `TOTAL_ASC`, `TOTAL_DESC`

### `CustomerSort`
`CREATED_AT_ASC`, `CREATED_AT_DESC`, `MODIFIED_AT_ASC`, `MODIFIED_AT_DESC`, `NAME_ASC`, `NAME_DESC`

### `InvoiceStatus`
`DRAFT`, `SAVED`, `SENT`, `VIEWED`, `PAID`, `PARTIAL`, `OVERDUE`, `UNPAID`

### `InvoiceSendMethod`
`WAVE`, `GMAIL`, `OUTLOOK`, `YAHOO`, `EXPORT_PDF`, `SHARED_LINK`, `MARKED_SENT`, `NOT_SENT`, `SKIPPED`

---

## Error handling

All errors throw exceptions. Catch them as needed:

| Exception | Trigger |
|---|---|
| `AuthenticationException` | Missing token or `UNAUTHENTICATED` from the API |
| `MalformedQueryException` | `GRAPHQL_VALIDATION_FAILED` — the query has a syntax/schema error |
| `ResourceNotFoundException` | `NOT_FOUND` — the requested resource does not exist |
| `ExecutionException` | `INTERNAL_SERVER_ERROR` from Wave |
| `InvalidDataException` | `VARIABLE_VALUE` — incorrect variable types supplied |
| `\Exception` | Any other Wave error |

All exceptions are in the `Jeffgreco13\Wave\Exceptions` namespace.

```php
use Jeffgreco13\Wave\Exceptions\AuthenticationException;
use Jeffgreco13\Wave\Exceptions\ResourceNotFoundException;

try {
    $invoice = $wave->createInvoice([...]);
} catch (ResourceNotFoundException $e) {
    // customer or product ID was not found
} catch (AuthenticationException $e) {
    // token invalid or expired
}
```

Mutations also expose field-level validation errors through the `inputErrors` field on the raw response. Check `didSucceed()` first, then inspect `getCachedResponse()` for error details if needed.

---

## Common patterns

### Discover your business ID

```bash
php artisan tinker
> (new \Jeffgreco13\Wave\WaveService())->getAllBusinesses()->pluck('name','id')
```

### Full invoice lifecycle

```php
$wave = new WaveService();

// 1. Create
$invoice = $wave->createInvoice([
    'customerId'  => 'Q3VzdG9tZXI6...',
    'invoiceDate' => now()->toDateString(),
    'items'       => [['productId' => 'UHJvZHVjdDo...', 'quantity' => 1]],
]);

// 2. Approve (moves out of DRAFT)
$wave->approveInvoice($invoice->id);

// 3. Send
$wave->sendInvoice([
    'invoiceId' => $invoice->id,
    'to'        => ['client@example.com'],
]);
```

### Multi-business usage

```php
$businesses = $wave->getAllBusinesses();

foreach ($businesses as $business) {
    $wave->setBusinessId($business->id);
    $invoices = $wave->getAllInvoices();
    // process invoices for this business...
}
```
