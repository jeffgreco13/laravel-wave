---
name: wave-development
description: Build and sync Wave Accounting data using the laravel-wave package, including querying the GraphQL API, syncing entities to local Eloquent models, and writing custom models.
---

# Wave Development

## When to use this skill

Use this skill when:
- Querying Wave Accounting data (customers, invoices, products, taxes, vendors, accounts)
- Setting up or customizing local sync for Wave entities
- Writing custom Eloquent models that map to Wave API fields
- Dispatching sync jobs or running sync commands
- Creating, updating, or deleting Wave records via jobs

## Setup Checklist

1. Add `.env` variables: `WAVE_ACCESS_TOKEN`, `WAVE_BUSINESS_ID`
2. Publish config: `php artisan vendor:publish --tag=wave-config`
3. Publish migrations: `php artisan vendor:publish --tag=wave-migrations`
4. Run migrations: `php artisan migrate`
5. Run initial sync: `php artisan wave:sync-all --sync`

## Querying the Wave API

Inject `WaveService` and call trait methods:

```php
use Jeffgreco13\Wave\WaveService;

$wave = app(WaveService::class);

// Change business context at runtime
$wave->setBusinessId('QnVzaW5lc3M6...');

// Paginated fetch — returns Collection of Node objects
$customers = $wave->getCustomers(['page' => 1, 'pageSize' => 20]);

// Fetch all (handles pagination)
$all = $wave->getAllCustomers();

// Access Node fields
foreach ($all as $customer) {
    echo $customer['name'];        // array access
    echo $customer->email;         // property access
}
```

Available `getAll*()` methods: `getAllCustomers`, `getAllInvoices`, `getAllProducts`,
`getAllTaxes`, `getAllVendors`, `getAllAccounts`, `getAllBusinesses`.

## Syncing Entities to Local Database

### Full sync (recommended for initial load or scheduled nightly runs)

Full sync upserts all Wave records and deletes any local records no longer present in Wave.

```php
use Jeffgreco13\Wave\Jobs\SyncCustomersFromWave;

// Queue it
SyncCustomersFromWave::dispatch();

// Or run synchronously in a command
SyncCustomersFromWave::dispatchSync();
```

### Incremental sync (for frequent refreshes)

Only fetches records modified after a given date. Never deletes local records.

```php
SyncCustomersFromWave::dispatch(since: now()->subHours(1));

// Or use the artisan command
// php artisan wave:sync-customers --since=last
```

### When to use each

| Mode | Use case | Deletes orphans? |
|---|---|---|
| Full (`$since = null`) | Nightly job, initial load | Yes |
| Incremental (`$since = Carbon`) | Frequent webhook-driven refresh | No |

## Querying Local Wave Models

```php
use Jeffgreco13\Wave\Models\WaveCustomer;

// Active customers
WaveCustomer::active()->get();

// Find by Wave ID
WaveCustomer::where('wave_id', $id)->first();

// Invoices for a customer
WaveInvoice::forCustomer($waveCustomerId)->where('status', 'UNPAID')->get();
```

## CRUD via Jobs

```php
use Jeffgreco13\Wave\Jobs\CreateWaveCustomer;
use Jeffgreco13\Wave\Jobs\UpdateWaveCustomer;
use Jeffgreco13\Wave\Jobs\DeleteWaveCustomer;

// Create in Wave and sync locally
CreateWaveCustomer::dispatch([
    'name'  => 'Acme Corp',
    'email' => 'billing@acme.com',
]);

// Update (must include 'id' = Wave customer UUID)
UpdateWaveCustomer::dispatch([
    'id'    => 'QnVzdG9tZXI6...',
    'email' => 'new@acme.com',
]);

// Delete from Wave and remove local record
DeleteWaveCustomer::dispatch('QnVzdG9tZXI6...');
```

## Custom Model Mapping

To use your own Eloquent model, implement `HasWaveSync` and set `wave.models.customer` in config.
The `waveAttributeMap()` method maps local columns to Wave API field paths (dot-notation for nested):

```php
use Jeffgreco13\Wave\Traits\HasWaveSync;

class Customer extends Model
{
    use HasWaveSync;

    protected $fillable = ['wave_id', 'full_name', 'email', 'currency'];

    public function waveAttributeMap(): array
    {
        return [
            'wave_id'   => 'id',
            'full_name' => 'name',
            'email'     => 'email',
            'currency'  => 'currency.code',   // nested: $data['currency']['code']
        ];
    }
}
```

Register in `config/wave.php`:

```php
'models' => ['customer' => \App\Models\Customer::class],
```

The `syncFromWave(array $data)` static method will then use your custom mapping automatically
in all sync jobs.

## Available Data Enums

Use enums for sort and filter parameters:

```php
use Jeffgreco13\Wave\Data\CustomerSort;
use Jeffgreco13\Wave\Data\InvoiceStatus;

$wave->getCustomers(['sort' => CustomerSort::NAME_ASC]);
$wave->getInvoices(['status' => InvoiceStatus::UNPAID]);
```

Available: `CustomerSort`, `InvoiceSort`, `InvoiceStatus`, `InvoiceCreateStatus`, `ProductSort`
