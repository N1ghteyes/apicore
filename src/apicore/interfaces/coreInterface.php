<?php

namespace n1ghteyes\apicore\interfaces;

use GuzzleHttp\ClientInterface;
use n1ghteyes\apicore\structure\response;

interface coreInterface
{
    /**
     * coreInterface constructor.
     *
     * V3: optional Guzzle client for tests — falls back to a default
     * `GuzzleHttp\Client` when null. Inject a client built around
     * `MockHandler` to drive deterministic responses in tests.
     */
    public function __construct(?ClientInterface $client = null);

    /**
     * Function to set the request schema. By default assumes http
     * @param string $schema
     * @return mixed
     */
    public function setSchema($schema = 'http');


    /**
     * Function to set the api server address.
     * @param $address
     * @param $port
     * @return mixed
     */
    public function setServer($address, $port);

    /**
     * Function to set the version and if it should be used as part of the api request path
     * @param string $version
     * @param bool $flag
     * @return mixed
     */
    public function setVersion($version, $flag = true);

    /**
     * Function to set the method used in the request
     * @param $method
     * @return mixed
     */
    public function setHTTPMethod($method);

    /**
     * Function to set the type of data being sent and received
     * @param $format
     * @return mixed
     */
    public function setBodyFormat($format);

    /**
     * Set auto Auth information, if it is needed.
     * @return mixed
     */
    public function auth($user, $pass, $type = 'basic');

    /**
     * V3: opt into Guzzle's BadResponseException semantics for 4xx /
     * 5xx responses. Default off — non-2xx flows through `processResult`
     * so callers see the body.
     */
    public function throwOnError(bool $throw = true): self;

    /**
     * V3: add headers scoped to the next request only.
     *
     * @param array<string, string> $headers
     */
    public function withHeaders(array $headers): self;

    /**
     * PHP Magic function. Should be used to set the HTTP method of a request
     * @param string $name
     * @return mixed
     */
    public function __get($name);

    /**
     * Implements magic PHP __call method. Turns undeclared method calls into api endpoint requests.
     *
     * V3: returns a fresh `response` instance (V2 returned a singleton).
     *
     * @param $name
     * @param $arguments
     * @return response
     */
    public function __call($name, $arguments);
}
