<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Wave API Credentials
    |--------------------------------------------------------------------------
    */

    'access_token' => env('WAVE_ACCESS_TOKEN', null),

    'graphql_uri' => env('WAVE_GRAPHQL_URI', 'https://gql.waveapps.com/graphql/public'),

    'business_id' => env('WAVE_BUSINESS_ID', null),

    /*
    |--------------------------------------------------------------------------
    | Model Bindings
    |--------------------------------------------------------------------------
    | Override with your own Eloquent models. Each custom model must have a
    | `wave_id` column. Use the HasWaveSync trait for automatic field mapping,
    | or override `waveAttributeMap()` to customize the mapping for your model.
    |
    | Example:
    |   'customer' => \App\Models\Customer::class,
    */

    'models' => [
        'customer'  => \Jeffgreco13\Wave\Models\WaveCustomer::class,
        'invoice'   => \Jeffgreco13\Wave\Models\WaveInvoice::class,
        'product'   => \Jeffgreco13\Wave\Models\WaveProduct::class,
        'sales_tax' => \Jeffgreco13\Wave\Models\WaveSalesTax::class,
        'vendor'    => \Jeffgreco13\Wave\Models\WaveVendor::class,
        'business'  => \Jeffgreco13\Wave\Models\WaveBusiness::class,
        'account'   => \Jeffgreco13\Wave\Models\WaveAccount::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Sync Settings
    |--------------------------------------------------------------------------
    */

    'sync' => [
        'chunk_size' => 150,
    ],

];
