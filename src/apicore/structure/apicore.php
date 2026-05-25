<?php

namespace n1ghteyes\apicore\structure;

use n1ghteyes\apicore\interfaces\coreInterface;
use GuzzleHttp;
use GuzzleHttp\ClientInterface;
use n1ghteyes\apicore\interfaces\loggingInterface;

/**
 * PHP class to handle connections to any number of APIs. Config provided by YAML config file, Can be extended to account for differences in api structure.
 *
 * @author Toby New <t@sc.vg> - www.source-control.co.uk
 * @copyright 2018 Toby New
 * @license license.txt The MIT License (MIT)
 */

/**
 * Class apiCore
 *
 * V3 (breaking) changes:
 *   - `__call()` returns a FRESH `response` instance per call (V2
 *     returned a process-wide singleton — concurrent / threaded
 *     callers stepped on each other + tests were hostile).
 *   - Guzzle's `http_errors` is OFF by default so 4xx / 5xx responses
 *     populate `statusCode` + `rawBodyData` instead of being swallowed
 *     into an exception with the body lost. Opt back in via
 *     `->throwOnError(true)`.
 *   - New `->withHeaders([...])` adds headers scoped to the NEXT call
 *     only; sticky `auth()` headers are preserved across calls as
 *     before.
 *   - Optional injectable Guzzle client (via constructor) for tests:
 *     swap in a `Client` built around `GuzzleHttp\Handler\MockHandler`
 *     to drive deterministic responses.
 */
abstract class apiCore implements coreInterface
{
    protected $request;
    protected $version;
    private $httpMethod = 'GET';
    private $bodyFormat = 'body';
    private $lastResult;
    private $args = array();
    private $rawResponse = false;
    private $processedResponse = false;
    private $errors = array();
    /** @var loggingInterface */
    protected $logger;

    /**
     * Guzzle's `http_errors` setting (V3 default: false). When false,
     * 4xx/5xx responses come through `processResult` and populate the
     * response's `statusCode` + `rawBodyData` — the caller decides
     * how to react. When true, Guzzle throws BadResponseException
     * and the response's `error` field is populated instead (V2
     * behaviour for back-compat opt-in).
     */
    private bool $throwOnError = false;

    /**
     * Headers added via `withHeaders()` — cleared after the next
     * `__call`. Distinct from `auth()` headers which stick.
     *
     * @var array<string, string>
     */
    private array $perCallHeaders = [];

    /** Injectable for tests. Falls back to a default Guzzle client. */
    private ?ClientInterface $client = null;

    /**
     * apiCore constructor.
     */
    public function __construct(?ClientInterface $client = null)
    {
        $this->client = $client;
        $this->request = new request();
        $this->setSchema(); //set the defaults
        $this->setDefaultCurlOpts();
        $this->setBodyFormat();
    }

    public function addLogger(loggingInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Function to set request Schema
     * @param string $schema
     * @return self
     */
    public function setSchema($schema = 'https://')
    {
        $this->request->setSchema($schema);
        return $this;
    }

    /**
     * Function to set the server address for the api call.
     * @param $address
     * @param int $port
     * @return self
     */
    public function setServer($address, $port = 443)
    {
        $this->request->setServer($address, $port);
        return $this;
    }

    /**
     * Set the base request path
     * @param $path
     * @return $this
     */
    public function setBasePath($path)
    {
        $this->request->setBasePath($path);
        return $this;
    }

    /**
     * Function to set API version and whether this should be used in the request URL
     * @param string $version
     * @param bool $flag
     * @return self
     */
    public function setVersion($version, $flag = true)
    {
        $this->version = $version;
        $this->request->setVersion($version, $flag);
        return $this;
    }

    /**
     * Function to set the type of data being sent and received
     * @param $format
     * @return mixed
     */
    public function setBodyFormat($format = 'body')
    {
        $this->bodyFormat = match ($format) {
            'form' => 'form_params',
            default => $format,
        };
        return $this;
    }

    /**
     * Get the current version provided to the API
     * @return mixed
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * V3: opt into Guzzle's BadResponseException semantics for 4xx /
     * 5xx responses. Default off — non-2xx populates the response's
     * `statusCode` + `rawBodyData` instead, so the caller can branch
     * on status without try/catch.
     */
    public function throwOnError(bool $throw = true): self
    {
        $this->throwOnError = $throw;
        return $this;
    }

    /**
     * V3: add headers scoped to the next request only. After dispatch
     * the map is cleared. Headers added via `auth(..., 'header')`
     * stick across calls as before.
     *
     * @param array<string, string> $headers
     */
    public function withHeaders(array $headers): self
    {
        foreach ($headers as $name => $value) {
            $this->perCallHeaders[(string) $name] = (string) $value;
        }
        return $this;
    }

    /**
     * Function to add any auth details to
     * @param $key
     * @param $value
     * @param string $type
     * @return mixed|void
     */
    public function auth($key, $value, $type = 'basic')
    {
        switch($type) {
            case "header":
                $this->args['headers'][$key] = $value;
                break;
            case 'basic':
            default:
                $this->args['auth'] = array($key, $value);
                break;
        }
    }

    /**
     * Function to set the HTTP method needed for thr request.
     * @param $method
     * @return mixed|void
     */
    public function setHTTPMethod($method)
    {
        $this->httpMethod = strtoupper((string) $method);
    }

    /**
     * Magic __get function for setting the HTTP method and API path
     * @param string $name
     * @return $this|mixed
     */
    public function __get($name)
    {
        match (strtolower($name)) {
            'get' => $this->setHTTPMethod('GET'),
            'post' => $this->setHTTPMethod('POST'),
            'put' => $this->setHTTPMethod('PUT'),
            'delete' => $this->setHTTPMethod('DELETE'),
            default => $this->request->addPathElement($name),
        };
        return $this;
    }

    /**
     * Occasionally we need to force a request, for example to the base domain.
     * @return mixed
     */
    public function makeDirectRequest(string $name = '', array $arguments = [])
    {
        return $this->__call($name, $arguments);
    }

    /**
     * Magic __call method, will translate all function calls to object to API requests
     * @param $name - name of the function
     * @param $arguments - an array of arguments
     * @return response a fresh response instance per call (V3 — no
     *                  longer a singleton)
     */
    public function __call($name, $arguments)
    {
        $client = $this->client ?? new GuzzleHttp\Client();
        $query = count($arguments) < 1 || !is_array($arguments) ? array() : $arguments[0];
        //Allow endpoints starting with an integer to be prepended with an underscore to make them valid method calls for PHP.
        if (str_starts_with((string) $name, '_')) {
            $name = strlen((string) $name) > 1 && is_numeric($name[1]) ? ltrim((string) $name, '_') : $name;
        }
        $this->request->addEndpoint($name);
        $this->processArgs($query);

        // Merge per-call headers into args for this dispatch. Sticky
        // `auth(..., 'header')` headers already live in args['headers'];
        // we union per-call on top so a per-call value overrides a
        // sticky one with the same name (intuitive override semantics).
        $stickyHeaders = isset($this->args['headers']) && is_array($this->args['headers'])
            ? $this->args['headers']
            : [];
        $callArgs = $this->args;
        if ($this->perCallHeaders !== []) {
            // `$a + $b` keeps `$a`'s value when keys collide — so per-call
            // headers go first so a per-call value overrides a sticky one
            // with the same name (intuitive override semantics).
            $callArgs['headers'] = $this->perCallHeaders + $stickyHeaders;
        }

        // V3: http_errors=false by default — non-2xx flow through to
        // `processResult` so callers see the body.
        $callArgs['http_errors'] = $this->throwOnError;

        $response = new response();
        $response->setVerb($this->httpMethod);

        //do we have a logging class? If so, add data to it.
        if ($this->logger !== null) {
            $this->logger->addMethod($this->httpMethod);
            $this->logger->addRequestURL((string)$this->request);
            $this->logger->addRequestArgs(json_encode($this->args));
            $this->logger->addRequestEndpoint($name);
            $this->logger->setRequestTime(time());
        }
        try {
            $result = $client->request($this->httpMethod, (string)$this->request, $callArgs);
            $response->processResult($result);
        } catch (GuzzleHttp\Exception\BadResponseException $e) {
            // Only reachable when throwOnError(true) is set (Guzzle
            // wraps 4xx/5xx into BadResponseException). Preserve the
            // status + body off the wrapped response so callers don't
            // lose the payload.
            if ($e->hasResponse()) {
                $response->processResult($e->getResponse());
            }
            $response->addError($e->getCode(), $e->getMessage());
        } catch (GuzzleHttp\Exception\GuzzleException $e) {
            // Transport-layer failure (DNS, connect timeout, etc).
            // Body unavailable; surface the error only.
            $response->addError($e->getCode(), $e->getMessage());
        }

        if ($this->logger !== null) {
            $this->logger->setResponseTime(time());
            if ($response->hasError()) {
                $this->logger->addRawResponse((string) ($response->getError()['message'] ?? ''));
                $this->logger->addResponseStatusCode((int) ($response->getError()['code'] ?? 0));
            } else {
                $this->logger->addRawResponse($response->rawBodyData);
                $this->logger->addResponseStatusCode((int) $response->statusCode);
            }
        }

        //reset some stuff post-query so we can handle the next one cleanly. Leave auth and headers in place by default.
        $this->request->resetQueryString();
        unset($this->args['query']);
        unset($this->args[$this->bodyFormat]);
        // V3: per-call headers cleared after dispatch.
        $this->perCallHeaders = [];

        return $response;
    }

    /**
     * Function to get the last result returned by an API call.
     * @return mixed
     */
    public function getLastResult()
    {
        return $this->lastResult;
    }

    /**
     * Function to return the last endpoint we called.
     * @return mixed
     */
    public function getLastCall()
    {
        return $this->request->getEndpoint();
    }

    /**
     * Getter for the errors array
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Function to reset the path in the api request
     */
    public function resetPath()
    {
        $this->request->resetPath();
    }

    public function addCurlOpts($opts)
    {
        $this->args['config']['curl'] = array_merge($this->args['config']['curl'], $opts);
    }

    /**
     * Function to set some default cURL arguments, such as SSL version.
     */
    private function setDefaultCurlOpts()
    {
        $this->args['config'] =
            array(
                'curl' => array(
                    'CURLOPT_SSLVERSION' => 6,
                )
            );
    }

    /**
     * Function to process the arguments passed depending on selected http method.
     * @param array $args
     * @return self
     */
    private function processArgs($args)
    {
        if (!empty($args)) {
            switch ($this->httpMethod) {
                case 'DELETE':
                case 'GET':
                    $this->args['query'] = $args;
                    break;
                case 'PUT':
                case 'POST':
                    $this->args[$this->bodyFormat] = $args;
                    break;
            }
        }
        return $this;
    }
}
