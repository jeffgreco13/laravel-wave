## laravel-wave

This package provides a Laravel wrapper for the Wave Accounting GraphQL API (`https://gql.waveapps.com/graphql/public`), with local Eloquent model sync.

### Configuration

Required `.env` variables:
- `WAVE_ACCESS_TOKEN` — OAuth2 Bearer token from Wave
- `WAVE_BUSINESS_ID` — Default Wave business UUID
- `WAVE_GRAPHQL_URI` — (optional) defaults to the public endpoint

Publish and configure:

@verbatim
<code-snippet name="Publish config and migrations" lang="bash">
php artisan vendor:publish --tag=wave-config
php artisan vendor:publish --tag=wave-migrations
php artisan migrate
</code-snippet>
@endverbatim

Config file is at `config/wave.php`. Custom model bindings go in the `models` array:

@verbatim
<code-snippet name="Custom model binding in config/wave.php" lang="php">
'models' => [
    'customer' => \App\Models\Customer::class,
    // invoice, product, sales_tax, vendor, business, account
],
</code-snippet>
@endverbatim

### Using WaveService

Resolve via dependency injection or `app(WaveService::class)`:

@verbatim
<code-snippet name="Injecting WaveService" lang="php">
use Jeffgreco13\Wave\WaveService;

public function __construct(protected WaveService $wave) {}

// Fetch paginated customers
$customers = $this->wave->getCustomers(['page' => 1, 'pageSize' => 20]);

// Fetch all customers (handles pagination automatically)
$all = $this->wave->getAllCustomers();

// Create a customer in Wave
$customer = $this->wave->createCustomer([
    'name' => 'Acme Corp',
    'email' => 'billing@acme.com',
]);

// Update a customer
$this->wave->patchCustomer(['id' => $waveId, 'name' => 'Acme Corp Ltd']);

// Delete a customer
$this->wave->deleteCustomer($waveId);
</code-snippet>
@endverbatim

### Syncing Wave Data to Local Models

All sync jobs implement `ShouldQueue`. The model class is resolved from config so custom models are used automatically.

**Full sync** (upserts all records, deletes local records no longer in Wave):

@verbatim
<code-snippet name="Dispatch full sync job" lang="php">
use Jeffgreco13\Wave\Jobs\SyncCustomersFromWave;

SyncCustomersFromWave::dispatch(); // queued
</code-snippet>
@endverbatim

**Incremental sync** (only records modified after a date, never deletes):

@verbatim
<code-snippet name="Dispatch incremental sync job" lang="php">
SyncCustomersFromWave::dispatch(since: now()->subDay());
</code-snippet>
@endverbatim

**Deletion rule**: orphan deletion (`whereNotIn`) only runs on full sync (`$since = null`). Incremental syncs never delete local records.

### Available Sync Jobs

- `SyncCustomersFromWave($since, $businessId)` — supports incremental
- `SyncInvoicesFromWave($since, $businessId)` — supports incremental
- `SyncProductsFromWave($businessId)`
- `SyncSalesTaxesFromWave($businessId)`
- `SyncVendorsFromWave($businessId)`
- `SyncBusinessesFromWave()`
- `SyncAccountsFromWave($businessId)`

Customer CRUD jobs: `CreateWaveCustomer($input)`, `UpdateWaveCustomer($input)`, `DeleteWaveCustomer($waveCustomerId)`

### Artisan Commands

@verbatim
<code-snippet name="Available artisan commands" lang="bash">
php artisan wave:sync-customers          # Full sync (queued)
php artisan wave:sync-customers --sync   # Run synchronously
php artisan wave:sync-customers --since="2024-01-01"  # Incremental
php artisan wave:sync-customers --since=last           # Since last local record
php artisan wave:sync-all               # Queue all entity syncs
php artisan wave:pull-currencies        # Cache currencies locally
</code-snippet>
@endverbatim

### Custom Models with HasWaveSync

@verbatim
<code-snippet name="Custom model using HasWaveSync trait" lang="php">
use Jeffgreco13\Wave\Traits\HasWaveSync;

class Customer extends Model
{
    use HasWaveSync;

    // Override to customize field mapping
    public function waveAttributeMap(): array
    {
        return [
            'wave_id'   => 'id',
            'full_name' => 'name',
            'email'     => 'email',
            // dot-notation for nested Wave fields
            'currency'  => 'currency.code',
        ];
    }
}
</code-snippet>
@endverbatim

Register in `config/wave.php`:

@verbatim
<code-snippet name="Register custom model" lang="php">
'models' => ['customer' => \App\Models\Customer::class],
</code-snippet>
@endverbatim
