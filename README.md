# Laravel API Model Driver

This library makes it easier to connect and use APIs with Laravel Eloquent models. You can build and get data from APIs just like you would from a database. It also works with Eloquent relationships.

You set up the API connection and link it to model classes. Then, you can use these models like regular database models, without worrying about API details or logins. But, this library only gets data from APIs, it doesn't send data back.

It also changes time zones automatically, which is helpful if the API's time zone is different. This saves you from having to adjust time zones manually.

## Features

Here's what the library offers for working with APIs in Laravel:

* **Query Builder**: Supports functions like `where`, `whereIn`, `whereBetween`, `orderBy`, and `limit`.
* **Eloquent Relationships**: Works well with Eloquent relationships.
* **Automatic Pluralization**: Automatically changes the name of a query parameter to plural if it's an array.
* **Auto Time Zone Change**: Converts time data to the right time zone automatically.
* **Several API Connections**: You can set up different API connections with their own settings.
* **Query String Splitting**: If a query string is too long, the library splits it up. See the `max_url_length` setting.
* **Efficient API Calls with php-curl**: Uses `php-curl` to make faster API calls by reusing connections.

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

    // (Optional) You can set the table name if it's different from the pluralized model name.
    // Laravel would use this by default for this model:
    protected $table = 'articles';
}
```

## Usage

Using the Eloquent model you can retrieve data from the API service as if it were a database:

```php
<?php

$query = Article::with(['dbModel', 'apiModel.anotherApiModel'])
    ->where('status', 'published')
    ->whereIn('author_id', [1, 2])
    ->whereBetween('publish_time', ['2020-08-01 00:00:00', '2020-08-04 23:59:59'])
    ->where('id', '>=', 3)
    ->where('id', '<=', 24)
    ->orderBy('publish_time', 'desc')
    ->limit(20);

// Perform the API request and retrieve the specified articles.
$articles = $query->get();
```

To see which URL the library generates, you can use the `toSql()` method:

```php
$url = $query->toSql();

echo $url;
// https://example.com/api/articles?status=published&author_ids[]=1&author_ids[]=2&min_publish_time=2020-08-01+00%3A00%3A00&max_publish_time=2020-08-04+23%3A59%3A59&min_id=3&max_id=24&order_by=publish_time&sort=desc&per_page=20
```
