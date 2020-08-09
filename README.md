# Laravel API Model Driver
The library allows to create and assign an API connection to Laravel 7 Eloquent models and use Laravel query builder to build a query string and get data as if you get data from a database connection. It also allows to use Eloquent relationships.

Once developers define the configuration of a new API connection and make the related model classes use that connection, they don't need to think about API calls, authentication, etc. They just work with those models as if they are regular models that have a MySQL connection. However, the library only supports retrieving data from an API service.

There is also a possibility to configure automatic time zone conversion in case the API service provides the clients with time values in a different time zone so that the developers don't need to think about it while they are writing code.

## Features
- Support the following query builder functions: **where**, **whereIn**, **whereBetween**, **orderBy** and **limit**;
- Support Eloquent relationships;
- Automatic pluralization of the name of a query parameter that has an array;
- Automatic time zone conversion for time JSON properties and time query parameters;
- Possibility to define multiple API connections with their own configuration and authentication;
- Automatic splitting query strings whose length is too long (see the **max_url_length** parameter);
- The library makes API calls using the **php-curl** extension so that the subsequent requests reuse the connection that was established during the first request without wasting time on establishing new connections.

## Installation
Install the library using composer:
```bash
composer require nazarii-kretovych/laravel-api-model-driver
```

## Configuration
Open **config/database.php** and add a new API connection:

```php
<?php

return [
    // ...

    'connections' => [
        // ...

        'example_com_api' => [
            'driver' => 'laravel_api_model_driver',
            'database' => 'https://example.com/api',

            // You can define headers that will be sent to the API service in each request.
            // You might need to put your authentication token in a header.
            'headers' => [
                'Authorization: Bearer TOKEN_HERE',
            ],

            // If the API service has Laravel Passport Client Credentials authentication,
            // you can define client ID and client secret here:
            'auth' => [
                'type' => 'passport_client_credentials',
                'url' => 'https://example.com/oauth/token',
                'client_id' => 1,
                'client_secret' => 'SECRET_HERE',
            ],

            // Define default query parameters.
            'default_params' => [
                'per_page' => 1000,  // this parameter is required
                'del' => 'no',
            ],

            // If the generated URL is longer than **max_url_length**,
            // its query string will be split into several parts, and the data will be retrieved for each part separately.
            'max_url_length' => 8000,  // default: 4000

            // The following configuration will generate the following query string
            // for ->whereIn('id', [1,3]):  ids[]=1&ids[]=3
            'pluralize_array_query_params' => true,  // default: false
            'pluralize_except' => ['meta'],  // pluralization skips these query params

            // If the API service provides its clients with time values in a different time zone,
            // you can define the following configuration, which will enable automatic time zone conversion.
            'timezone' => 'Europe/Kiev',
            'datetime_keys' => ['created_at', 'updated_at', 'start_time', 'end_time'],
        ],
    ],
];
```

Create a new model class and set its connection:

```php
<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Article extends Model
{
    protected $connection = 'example_com_api';
    protected $table = 'articles';  // optional. Laravel generates it from the name of the class
}
```

## Usage
```php
<?php

Article::with('dbModel', 'apiModel.anotherApiModel')
    ->where('status', 'published')
    ->whereIn('author_id', [1, 2])
    ->whereBetween('publish_time', ['2020-08-01 00:00:00', '2020-08-04 23:59:59'])
    ->where('id', '>=', 3)
    ->where('id', '<=', 24);
    ->orderBy('publish_time', 'desc')
    ->limit(20)
    ->get();

// The library will generate the following URL for retrieving articles:
// https://example.com/api/articles?status=published&author_ids[]=1&author_ids[]=2&min_publish_time=2020-08-01+00%3A00%3A00&max_publish_time=2020-08-04+23%3A59%3A59&min_id=3&max_id=24&order_by=publish_time&sort=desc&per_page=20
```
