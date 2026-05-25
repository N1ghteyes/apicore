<?php

declare(strict_types=1);

namespace n1ghteyes\apicore\tests;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use n1ghteyes\apicore\client as ApiCoreClient;
use n1ghteyes\apicore\structure\response as ApiCoreResponse;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * V3 unit tests — drive APICore against Guzzle's MockHandler so the
 * canonical mocking pattern is exercised + documented by the tests
 * themselves.
 */
final class ApiCoreTest extends TestCase
{
    /** @var array<int, array{0: Request, 1?: mixed}> */
    private array $history = [];

    /** Build an APICore `client` whose Guzzle is fed by canned responses. */
    private function makeClient(array $queue): ApiCoreClient
    {
        // Guzzle's Middleware::history takes the container by reference
        // — needs to be a real variable, not a property access (PHP
        // property reads return a copy). Bind a local + reference the
        // property to it.
        $this->history = [];
        $historyRef = &$this->history;
        $mock = new MockHandler($queue);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($historyRef));
        $guzzle = new GuzzleClient(['handler' => $stack]);

        $api = new ApiCoreClient($guzzle);
        $api->setServer('api.test', 443);
        $api->setVersion('1', false);
        return $api;
    }

    #[Test]
    public function returns_fresh_response_instance_per_call(): void
    {
        $api = $this->makeClient([
            new Response(200, ['Content-Type' => 'application/json'], '{"a":1}'),
            new Response(200, ['Content-Type' => 'application/json'], '{"a":2}'),
        ]);

        $r1 = $api->GET->resource();
        $api->resetPath();
        $r2 = $api->GET->resource();

        self::assertInstanceOf(ApiCoreResponse::class, $r1);
        self::assertInstanceOf(ApiCoreResponse::class, $r2);
        self::assertNotSame($r1, $r2, 'V3: each __call must yield a fresh instance');
        self::assertSame(1, $r1->data->a);
        self::assertSame(2, $r2->data->a);
    }

    #[Test]
    public function non_2xx_populates_status_and_body_without_throwing(): void
    {
        $api = $this->makeClient([
            new Response(400, ['Content-Type' => 'application/json'], '{"error":"invalid_grant"}'),
        ]);
        $api->setBodyFormat('form');

        $r = $api->POST->token(['code' => 'CODE']);

        self::assertSame(400, $r->statusCode);
        self::assertSame('{"error":"invalid_grant"}', $r->rawBodyData);
        self::assertSame('invalid_grant', $r->data->error);
        self::assertFalse($r->hasError(), 'http_errors=false leaves the error bag empty on 4xx');
    }

    #[Test]
    public function throw_on_error_opt_in_still_preserves_body(): void
    {
        $api = $this->makeClient([
            new Response(500, ['Content-Type' => 'application/json'], '{"error":"boom"}'),
        ]);
        $api->throwOnError(true);

        $r = $api->GET->resource();

        // BadResponseException was caught + the wrapped response was
        // populated, so the caller still sees the body. The error bag
        // also carries the Guzzle exception message.
        self::assertSame(500, $r->statusCode);
        self::assertSame('{"error":"boom"}', $r->rawBodyData);
        self::assertTrue($r->hasError());
    }

    #[Test]
    public function transport_failure_surfaces_error_with_no_body(): void
    {
        $api = $this->makeClient([
            new ConnectException(
                'Connection refused',
                new Request('GET', 'https://api.test/resource'),
            ),
        ]);

        $r = $api->GET->resource();

        self::assertNull($r->statusCode);
        self::assertSame('', $r->rawBodyData);
        self::assertTrue($r->hasError());
        self::assertStringContainsString('Connection refused', $r->getError()['message']);
    }

    #[Test]
    public function with_headers_scopes_to_next_call_only(): void
    {
        $api = $this->makeClient([
            new Response(200, [], '{}'),
            new Response(200, [], '{}'),
        ]);
        $api->withHeaders(['X-Trace-Id' => 'trace-1']);

        $api->GET->resource();
        $api->resetPath();
        $api->GET->resource();

        self::assertCount(2, $this->history);
        self::assertSame('trace-1', $this->history[0]['request']->getHeaderLine('X-Trace-Id'));
        self::assertSame('', $this->history[1]['request']->getHeaderLine('X-Trace-Id'),
            'V3: per-call headers must clear after dispatch');
    }

    #[Test]
    public function sticky_auth_headers_persist_across_calls(): void
    {
        $api = $this->makeClient([
            new Response(200, [], '{}'),
            new Response(200, [], '{}'),
        ]);
        $api->auth('Authorization', 'Bearer ya29.GOOD', 'header');

        $api->GET->resource();
        $api->resetPath();
        $api->GET->resource();

        self::assertCount(2, $this->history);
        self::assertSame('Bearer ya29.GOOD', $this->history[0]['request']->getHeaderLine('Authorization'));
        self::assertSame('Bearer ya29.GOOD', $this->history[1]['request']->getHeaderLine('Authorization'));
    }

    #[Test]
    public function per_call_header_overrides_sticky_with_same_name(): void
    {
        $api = $this->makeClient([new Response(200, [], '{}')]);
        $api->auth('X-Trace-Id', 'sticky', 'header');
        $api->withHeaders(['X-Trace-Id' => 'override']);

        $api->GET->resource();

        self::assertSame('override', $this->history[0]['request']->getHeaderLine('X-Trace-Id'));
    }

    #[Test]
    public function get_request_args_become_query_string(): void
    {
        $api = $this->makeClient([new Response(200, [], '{}')]);
        $api->GET->messages(['maxResults' => 50, 'labelIds' => 'INBOX']);

        $uri = $this->history[0]['request']->getUri();
        $query = [];
        parse_str($uri->getQuery(), $query);
        self::assertSame('50', $query['maxResults']);
        self::assertSame('INBOX', $query['labelIds']);
    }

    #[Test]
    public function post_with_form_body_format_uses_form_params(): void
    {
        $api = $this->makeClient([new Response(200, [], '{}')]);
        $api->setBodyFormat('form');
        $api->POST->token(['grant_type' => 'authorization_code', 'code' => 'X']);

        $body = (string) $this->history[0]['request']->getBody();
        self::assertStringContainsString('grant_type=authorization_code', $body);
        self::assertStringContainsString('code=X', $body);
        self::assertStringContainsString(
            'application/x-www-form-urlencoded',
            $this->history[0]['request']->getHeaderLine('Content-Type'),
        );
    }

    #[Test]
    public function records_verb_on_response(): void
    {
        $api = $this->makeClient([new Response(204, [], '')]);
        $r = $api->DELETE->resource();
        self::assertSame('DELETE', $r->verb);
    }

    #[Test]
    public function response_headers_collapsed_to_lowercase_keys(): void
    {
        $api = $this->makeClient([
            new Response(200, [
                'Content-Type' => 'application/json',
                'X-RateLimit-Remaining' => '99',
            ], '{}'),
        ]);
        $r = $api->GET->resource();

        self::assertSame('application/json', $r->headers['content-type']);
        self::assertSame('99', $r->headers['x-ratelimit-remaining']);
    }
}
