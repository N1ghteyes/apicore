# PHP API Core - Agnostic RESTful client for Resful APIs

### Description

This PHP class is designed to provide a single library for all RESTful API integrations, it prevents the need to download and use individual libraries for multiple services used in a project. The core class uses a combination of php Magic methods to construct api calls based on chaining, meaning that the class itself needs to have no knowledge of API structure and can be used with any integration that provides a RESTful series of endpoints.

### V3 — what changed

> Upgrading from 2.x? Read the full notes in `CHANGELOG.md`. TL;DR:
>
> - `__call()` returns a **fresh response instance** per call (no more singleton).
> - **`http_errors` default is OFF** — 4xx / 5xx populate `$response->statusCode` + `rawBodyData` instead of throwing. Opt back in with `$api->throwOnError(true)`.
> - New `$api->withHeaders([...])` — scopes headers to the next call only.
> - New `client::__construct(?ClientInterface $client = null)` — inject a Guzzle client (e.g. with `MockHandler`) for tests.
> - `$response->headers` now exposes the response's PSR-7 headers as a lowercase-keyed array.
> - phpunit test suite + documented mocking pattern shipped with the package.

### Testing against APICore

APICore is mock-friendly via Guzzle's `MockHandler` — inject a `GuzzleHttp\Client` configured with canned responses + the history middleware:

```php
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use n1ghteyes\apicore\client as ApiCoreClient;

$history = [];
$historyRef = &$history;
$stack = HandlerStack::create(new MockHandler([
    new Response(200, ['Content-Type' => 'application/json'], '{"ok":true}'),
]));
$stack->push(Middleware::history($historyRef));

$api = new ApiCoreClient(new Client(['handler' => $stack]));
$response = $api->setServer('api.test')->GET->resource();

// Assert request shape:
$request = $history[0]['request']; // PSR-7 RequestInterface
// Assert response shape:
$response->statusCode;  // 200
$response->data->ok;    // true
```

### Installation

If you can, this should be installed with composer. Please note this is not currently available through packagist, so you will need to add the following repository to your composer.json:

```json
{
    "repositories": [
        {
            "type": "composer",
            "url": "https:\/\/codestore.sc.vg"
        }
    ]
}
```
```bash
$ composer require n1ghteyes/apicore
```
### Basic request structure

In order to make a request after setting some default values (info below), you must constuct a chain. the general structure is as follows:

```php
$response = $api->{HTTP VERB}->{PATH 1}->{PATH 2}->{ENDPOINT}({Args as array});
```

In more general terms, using a request to twitter as an example:
see: https://developer.twitter.com/en/docs/tweets/timelines/api-reference/get-statuses-user_timeline.html

> *In this instance twitter requires us to specify a file ending. As such the endpoint must be called as an encapsulated string*

```php
$response = $api->GET->statuses->{"user_timeline.json"}(["screen_name" => "noradio"]);
```

### Basic usage - Twitter Example

This example assumes you have already included the vendor autoload file.
```php
use n1ghteyes\apicore\client

//Instanciate the class.
$api = new client();

//Set the API server (In this example, twitter) - We assume the connection is HTTPS.
$api->setServer("api.twitter.com");

//Set the API version - This can be an arbitrary number. If you would like to exclude the version number from the request path, pass FALSE as the second argument.
$api->setVersion("1.1", FALSE);

//Make a request to the oauth endpoint to generate a grant token:
//@see https://developer.twitter.com/en/docs/basics/authentication/api-reference/token
$response = $api->POST->oauth2->token(['grant_type' => "xxxxxx"]);
```

The above example constructs a POST request for the following URL: https://api.twitter.com/oauth2/token passing grant_type in the POST body.

You can then go on to access other methods on the oauth2 route:
```php
$response # $api->POST->invalidate_token(["access_token" => "xxxx"])
```
> #### *NOTE*
> *In order to use a new route (reset the path to the base url) you must call the reset path method. This may change in the future, but for now:*
>
> ```php
> //reset the path
> $api->resetPath();
> ```

### Authentication

The class supports several authentication methods.

#### Basic Authentication (base62 encoded HTTP Auth)

One of the most common methods of authentication with anopther service is basic http authentication.

```php
//The method will automatically encode the values passed here.
$api->auth("user", "password");
```

#### Token Based Authentication

If you need to pass a grant or request token generated through oauth, you can also use the auth method as follows

```php
//Adding a third argument of "header" treats the first argument as the header name and the second as the header value. This can be used to add any custom header to the request. 
$api->auth("header-key", "token", "header")
```

### Other Notes

#### Numeric endpoints
You may come across an API where the endpoint (the final path element in the URL) is numeric or starts with a number, for example if that element is a numeric ID. In this instance, you must prepend that path with an underscore to make it a valid method for PHP.

The API Core will strip the underscore off the final path element if the second character in that path is numeric.

For example:

```php
//This is invalid, PHP will throw a fatal error.
$id = 1234;
$api->path1->path2->$id();

//This is valid
$id = '_1234';
$api->path1->path2->$id();

//Simplified to account for unknown, potentually numeric values:
$endpoint = is_numeric($id) ? '_'.$id : $id;
$api->path1->path2->$endpoint();
```
