# laravel-wave

A Laravel package for the [Wave Accounting](https://www.waveapps.com) GraphQL API. Query your Wave data, sync it to local Eloquent models, and manage customers, invoices, products, and more — all via queued jobs and Artisan commands.

---

## Requirements

- PHP 8.2+
- Laravel 11, 12, or 13
- A [Wave developer account](https://developer.waveapps.com) with an OAuth2 access token

---

## Installation

```bash
composer require jeffgreco13/laravel-wave
```

Publish the config file:

```bash
php artisan vendor:publish --tag=wave-config
```

Publish the migrations (optional — only needed if you want local sync):

```bash
php artisan vendor:publish --tag=wave-migrations
php artisan migrate
```

---

## Configuration

Add to your `.env`:

```env
WAVE_ACCESS_TOKEN=your_oauth_token_here
WAVE_BUSINESS_ID=your_wave_business_uuid_here
```

Full `config/wave.php` reference:

```php
return [
    'access_token' => env('WAVE_ACCESS_TOKEN'),
    'graphql_uri'  => env('WAVE_GRAPHQL_URI', 'https://gql.waveapps.com/graphql/public'),
    'business_id'  => env('WAVE_BUSINESS_ID'),

    // Override with your own Eloquent models (see "Custom Models" below)
    'models' => [
        'customer'  => \Jeffgreco13\Wave\Models\WaveCustomer::class,
        'invoice'   => \Jeffgreco13\Wave\Models\WaveInvoice::class,
        'product'   => \Jeffgreco13\Wave\Models\WaveProduct::class,
        'sales_tax' => \Jeffgreco13\Wave\Models\WaveSalesTax::class,
        'vendor'    => \Jeffgreco13\Wave\Models\WaveVendor::class,
        'business'  => \Jeffgreco13\Wave\Models\WaveBusiness::class,
        'account'   => \Jeffgreco13\Wave\Models\WaveAccount::class,
    ],

    'sync' => [
        'chunk_size' => 150, // records per page during sync
    ],
];
```

---

## Basic Usage

Inject `WaveService` or resolve it from the container:

```php
use Jeffgreco13\Wave\WaveService;

class InvoiceController extends Controller
{
    public function __construct(protected WaveService $wave) {}

    public function index()
    {
        // Returns a Collection of Node objects
        return $this->wave->getCustomers(['page' => 1, 'pageSize' => 20]);
    }
}
```

Nodes support both array and property access:

```php
foreach ($this->wave->getAllCustomers() as $customer) {
    echo $customer['name'];   // array access
    echo $customer->email;    // property access
}
```

### Available API Methods

| Method | Description |
|---|---|
| `getCustomers($vars)` | Paginated customer list |
| `getAllCustomers($vars)` | All customers (handles pagination) |
| `createCustomer($input)` | Create a new customer |
| `patchCustomer($input)` | Update a customer (requires `id`) |
| `deleteCustomer($customerId)` | Delete a customer |
| `getInvoices($vars)` | Paginated invoice list |
| `getAllInvoices($vars)` | All invoices |
| `createInvoice($input)` | Create an invoice |
| `approveInvoice($invoiceId)` | Approve a draft invoice |
| `sendInvoice($input)` | Send an invoice by email |
| `getAllProducts()` | All products |
| `getAllTaxes()` | All sales taxes |
| `getVendors($vars)` | Paginated vendor list |
| `getAllVendors()` | All vendors |
| `getAccounts($vars)` | Paginated chart-of-accounts |
| `getAllAccounts()` | All chart-of-accounts entries |
| `getAccount($accountId)` | Single account |
| `getAllBusinesses()` | All businesses on the account |
| `getBusiness($id)` | Single business |
| `getUser()` | Authenticated user |
| `rawQuery($query, $variables)` | Execute a custom GraphQL query |

### Pagination

```php
$wave->getCustomers(['page' => 1, 'pageSize' => 20]);

while ($wave->hasNextPage()) {
    $wave->nextPage();
    $wave->getCustomers();
}
```

---

## Data Sync

Sync jobs persist Wave data to your local database. All jobs implement `ShouldQueue`.

### Full Sync

Fetches all records from Wave and upserts them locally. After all pages are processed, any local record whose `wave_id` was **not** seen in Wave is deleted (orphan cleanup).

```php
use Jeffgreco13\Wave\Jobs\SyncCustomersFromWave;

// Queue
SyncCustomersFromWave::dispatch();

// Or via Artisan
php artisan wave:sync-customers
```

### Incremental Sync

Only fetches records modified after a given date. **Never deletes** local records — a missing record just means it was not recently modified, not that it was deleted.

```php
// Sync records modified in the last 24 hours
SyncCustomersFromWave::dispatch(since: now()->subDay());
```

```bash
# Specific date
php artisan wave:sync-customers --since="2024-06-01"

# Automatically use the most recently synced local record's wave_modified_at
php artisan wave:sync-customers --since=last
```

### Sync All Entities

```bash
# Queue all sync jobs
php artisan wave:sync-all

# Run synchronously
php artisan wave:sync-all --sync

# With business override
php artisan wave:sync-all --business-id=QnVzaW5lc3M6...
```

### Querying Local Wave Models

```php
use Jeffgreco13\Wave\Models\WaveCustomer;
use Jeffgreco13\Wave\Models\WaveInvoice;

// Active customers
WaveCustomer::active()->get();

// Find by Wave ID
WaveCustomer::where('wave_id', $id)->first();

// Unpaid invoices for a customer
WaveInvoice::active()->forCustomer($waveCustomerId)->status('UNPAID')->get();
```

---

## Customer CRUD via Jobs

```php
use Jeffgreco13\Wave\Jobs\CreateWaveCustomer;
use Jeffgreco13\Wave\Jobs\UpdateWaveCustomer;
use Jeffgreco13\Wave\Jobs\DeleteWaveCustomer;

// Create in Wave and upsert locally
CreateWaveCustomer::dispatch([
    'name'  => 'Acme Corp',
    'email' => 'billing@acme.com',
]);

// Update (must include 'id' — Wave's customer UUID)
UpdateWaveCustomer::dispatch([
    'id'    => 'QnVzdG9tZXI6...',
    'email' => 'new@acme.com',
]);

// Delete from Wave and remove the local record
DeleteWaveCustomer::dispatch('QnVzdG9tZXI6...');
```

---

## Custom Models

To use your own Eloquent model, implement the `HasWaveSync` trait and register it in `config/wave.php`.

```php
// app/Models/Customer.php
use Jeffgreco13\Wave\Traits\HasWaveSync;

class Customer extends Model
{
    use HasWaveSync;

    protected $fillable = ['wave_id', 'company_name', 'email', 'currency_code'];

    /**
     * Map local column names to Wave API field paths.
     * Use dot-notation for nested Wave objects.
     */
    public function waveAttributeMap(): array
    {
        return [
            'wave_id'       => 'id',
            'company_name'  => 'name',
            'email'         => 'email',
            'currency_code' => 'currency.code',
        ];
    }
}
```

Register in `config/wave.php`:

```php
'models' => [
    'customer' => \App\Models\Customer::class,
],
```

All sync jobs (`SyncCustomersFromWave`, `CreateWaveCustomer`, etc.) will use your model automatically.

---

## Available Artisan Commands

| Command | Description |
|---|---|
| `wave:sync-customers` | Sync customers from Wave |
| `wave:sync-all` | Sync all entities |
| `wave:pull-currencies` | Cache Wave currency list locally |

**Options for `wave:sync-customers` and `wave:sync-all`:**

| Option | Description |
|---|---|
| `--since="YYYY-MM-DD"` | Incremental sync from a date |
| `--since=last` | Incremental from most recent local record |
| `--business-id=` | Override the configured business ID |
| `--sync` | Run synchronously (skip queue) |
| `--fresh` | Truncate the local table first (full sync only) |

---

## Available Sync Jobs

| Job | Supports `$since`? | Deletes orphans? |
|---|---|---|
| `SyncCustomersFromWave` | Yes | Full sync only |
| `SyncInvoicesFromWave` | Yes | Full sync only |
| `SyncProductsFromWave` | No | Always (full sync) |
| `SyncSalesTaxesFromWave` | No | Always |
| `SyncVendorsFromWave` | No | Always |
| `SyncBusinessesFromWave` | No | Always |
| `SyncAccountsFromWave` | No | Always |

---

## Data Enums

Use built-in enums for sort/filter values:

```php
use Jeffgreco13\Wave\Data\CustomerSort;
use Jeffgreco13\Wave\Data\InvoiceSort;
use Jeffgreco13\Wave\Data\InvoiceStatus;
use Jeffgreco13\Wave\Data\InvoiceCreateStatus;
use Jeffgreco13\Wave\Data\ProductSort;

$wave->getCustomers(['sort' => CustomerSort::NAME_ASC]);
$wave->getInvoices(['status' => InvoiceStatus::UNPAID]);
```

---

## Laravel Boost

This package includes AI guidelines and a developer skill for [Laravel Boost](https://laravel.com/docs/13.x/boost). After installing Boost in your app, run:

```bash
php artisan boost:install
# or
php artisan boost:update --discover
```

Boost will automatically load the `wave-development` guidelines and skill to help AI agents write correct Wave integration code.

---

## Changelog / Upgrade Guide

### v2.0

- **Breaking:** Config key changed from `laravel-wave` to `wave`. Update any manual `config('laravel-wave.*')` calls to `config('wave.*')`.
- Added local Eloquent model sync (`wave_customers`, `wave_invoices`, `wave_products`, `wave_sales_taxes`, `wave_vendors`, `wave_businesses`, `wave_accounts`)
- Added `HasWaveSync` trait for custom model mapping
- Added sync jobs for all entities with full/incremental modes and orphan deletion
- Added `wave:sync-customers`, `wave:sync-all` Artisan commands
- Added `ManagesVendors` and `ManagesAccounts` traits to `WaveService`
- Added `deleteCustomer()` to `ManagesCustomers`
- Laravel Boost integration (`resources/boost/guidelines/` and `resources/boost/skills/`)

---

## License

MIT
