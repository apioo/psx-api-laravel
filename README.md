
# PSX API Package

The PSX API package integrates the [PSX API components](https://phpsx.org/) into Laravel which help
to build fully type-safe REST APIs. Basically the package provides additional attributes which you
can use at your [controller](#controller) to map HTTP parameters to arguments of your controller
and commands to generate based on those attributes and type-hints different artifacts:

* Generate Client SDKs for different languages i.e. TypeScript and PHP
    * `php artisan generate:sdk client-typescript`
* Generate OpenAPI specification without additional attributes
    * `php artisan generate:sdk spec-openapi`
* Generate DTO classes using [TypeSchema](https://typeschema.org/)
    * `php artisan generate:model`

As you note this bundle is about REST APIs and not related to any PlayStation content, the name PSX
was invented way back and is simply an acronym which stands for "**P**HP, **S**QL, **X**ML"

## Installation

To install the bundle simply require the composer package at your Laravel project.

```
composer require psx/api-laravel
```

Make sure, that the package is registered at the `bootstrap/providers.php` file:

```php
return [
    PSX\ApiLaravel\ApiServiceProvider::class,
];
```

## Controller

The following is a simple controller which shows how to use the PSX specific attributes to describe
different HTTP parameters:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Model\PostCollection;
use App\Model\Post;
use App\Model\Message;
use PSX\Api\Attribute\Body;
use PSX\Api\Attribute\Param;
use PSX\Api\Attribute\Query;

final class PostController extends Controller
{
    public function __construct(private PostService $service, private PostRepository $repository)
    {
    }

    public function getAll(#[Query] ?string $filter): PostCollection
    {
        return $this->repository->findAll($filter);
    }

    public function get(#[Param] int $id): Post
    {
        return $this->repository->find($id);
    }

    public function create(#[Body] Post $payload): Message
    {
        return $this->service->create($payload);
    }

    public function update(#[Param] int $id, #[Body] Post $payload): Message
    {
        return $this->service->update($id, $payload);
    }

    public function delete(#[Param] int $id): Message
    {
        return $this->service->delete($id);
    }
}
```

Internally the routes are automatically registered so you dont need to add those routes
to the `routes/web.php` etc. In the example we use the `#[Query]`, `#[Param]` and `#[Body]`
attribute to map different parts of the incoming HTTP request. In the controller we use a
fictional `PostService` and `PostRepository` but you are complete free to design the controller
how you like, for PSX it is only important to map the incoming HTTP request parameters to
arguments and to provide a return type.

### Raw payload

We always recommend to generate concrete DTOs to describe the request and response payloads.
If you need a raw payload we provide the following type-hints to receive a raw value.

* `Psr\Http\Message\StreamInterface`
    * Receive the raw request as stream `application/octet-stream`
* `PSX\Data\Body\Json`
    * Receive the raw request as JSON `application/json`
* `PSX\Data\Body\Form`
    * Receive the raw request as form `application/x-www-form-urlencoded`
* `string`
    * Receive the raw request as string `text/plain`

For example to write a simple proxy method which returns the provided JSON payload s.

```php
public function create(#[Body] Json $body): Json
{
    return $body;
}
```

### Multiple response types

In case your method can return different response types you can use the `#[Outgoing]` attribute to
define a response schema independent of the return type.

```php
#[Outgoing(201, Message::class)]
#[Outgoing(400, Error::class)]
public function create(#[Body] Post $body)
{
    if (empty($body->getTitle())) {
        return response()->json(new Error('An error occurred'), 400);
    }

    return response()->json(new Message('Post successfully created'), 201);
}
```

## Generator

### SDK

To generate an SDK you can simply run the following command:

```
php artisan generate:sdk
```

This reads alls the attributes from your controller and writes the SDK to the `output` folder.
At first argument you can also provide a type, by default this is `client-typescript` but you can also
select a different type.

* `client-php`
* `client-typescript`
* `spec-openapi`

#### SDKgen

Through the SDKgen project you have the option to generate also client SDKs for
different programming languages, therefor you only need to register at the [SDKgen](https://sdkgen.app/)
website to obtain a client id and secret which you need to set as `psx_api.sdkgen_client_id` and `psx_api.sdkgen_client_secret`
at your config. After this you can use one of the following types:

* `client-csharp`
* `client-go`
* `client-java`
* `client-python`

### Model

This bundle also provides a model generator which helps to generate DTOs to describe the
incoming and outgoing payload s.

```
php artisan generate:model
```

This commands reads the [TypeSchema](https://typeschema.org/) specification located at `config/typeschema.json`
and writes all model classes to `src/Model`. In general TypeSchema is a JSON specification to describe data models.
The following is an example specification to generate a simple Student model.

```json
{
  "definitions": {
    "Student": {
      "description": "A simple student struct",
      "type": "struct",
      "properties": {
        "firstName": {
          "type": "string"
        },
        "lastName": {
          "type": "string"
        },
        "age": {
          "type": "integer"
        }
      }
    }
  }
}
```

## Configuration

The package needs the following `psx.php` configuration:

```php
return [

    'base_url' => '',

    /*
     * Optional username or app key of your sdkgen.app account
     */
    'sdkgen_client_id' => env('SDKGEN_CLIENT_ID'),

    /*
     * Optional password or app secret of your sdkgen.app account
     */
    'sdkgen_client_secret' => env('SDKGEN_CLIENT_SECRET'),

];
```

The `base_url` is the absolute url to your API so that you don't need to provide the
base url at your client SDK.

The `sdkgen_client_id` and `sdkgen_client_secret` are credentials to the [SDKgen](https://sdkgen.app/) app.

## Community

Feel free to create an issue or PR in case you want to improve this package.
