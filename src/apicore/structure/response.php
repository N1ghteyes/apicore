<?php

namespace n1ghteyes\apicore\structure;

use GuzzleHttp;
use Psr\Http\Message\ResponseInterface;

/**
 * Response value object — one instance per API call.
 *
 * **V3 change (breaking)**: This class used to be a process-wide
 * singleton accessed via `response::getInstance()` + reset between
 * calls via `response::resetData()`. That made concurrent callers
 * step on each other and tests hostile. V3 returns a fresh instance
 * from `apiCore::__call()` and exposes errors as a per-instance
 * property rather than a static slot.
 *
 * Public fields preserved from V2 so consumers reading
 * `$response->statusCode` etc continue to work unchanged.
 */
class response
{
    public ?int $statusCode = null;
    public ?string $dataType = null;
    public mixed $data = null;
    public ?string $verb = null;
    public ?string $url = null;
    public string $rawBodyData = '';

    /**
     * Headers from the underlying HTTP response, lowercase-keyed.
     * Empty array on error path. New in V3.
     *
     * @var array<string, string>
     */
    public array $headers = [];

    /**
     * Error metadata when the underlying transport failed (network
     * error, malformed response, etc). Empty array on the happy
     * path. With `http_errors=false` (V3 default on apiCore), 4xx /
     * 5xx populate `statusCode` + `rawBodyData` *and* leave this
     * empty — only true transport faults set an error.
     *
     * @var array{code?: int|string, message?: string}
     */
    public array $error = [];

    /**
     * Record the HTTP verb the request was dispatched with. Guzzle
     * doesn't echo this back on the PSR-7 response so we capture it
     * at call-time.
     */
    public function setVerb(string $verb): void
    {
        $this->verb = $verb;
    }

    /**
     * Populate the response from a successful Guzzle dispatch (any
     * status code — with http_errors=false even 4xx/5xx arrive here).
     */
    public function processResult(ResponseInterface $guzzleResponse): void
    {
        $contentType = $guzzleResponse->getHeader('Content-Type');
        $this->dataType    = array_shift($contentType);
        $this->statusCode  = $guzzleResponse->getStatusCode();
        $this->rawBodyData = (string) $guzzleResponse->getBody();
        $this->url         = $guzzleResponse->getHeaderLine('Location');

        // Collapse PSR-7's array-of-values shape to a single string per
        // header (joined by ", " per RFC 9110 §5.2). Keys lowercased
        // for predictable lookup.
        $headers = [];
        foreach ($guzzleResponse->getHeaders() as $name => $values) {
            $headers[strtolower((string) $name)] = implode(', ', $values);
        }
        $this->headers = $headers;

        $this->data = match ($this->dataType) {
            'text/html', 'text/xml' => $this->xml2array(simplexml_load_string($this->rawBodyData)),
            default => json_decode($this->rawBodyData),
        };
    }

    /**
     * Record a transport-layer error. With http_errors=false this is
     * only hit for genuine network/parse failures — 4xx/5xx come
     * through `processResult` instead.
     */
    public function addError(int|string $code, string $message): void
    {
        $this->error = ['code' => $code, 'message' => $message];
    }

    /**
     * @return array{code?: int|string, message?: string} empty array on no error
     */
    public function getError(): array
    {
        return $this->error;
    }

    public function hasError(): bool
    {
        return $this->error !== [];
    }

    /**
     * Convert simpleXML object to array.
     * @see http://www.php.net/manual/en/ref.simplexml.php#111227
     *
     * @param  array<mixed> $out
     * @return array<mixed>
     */
    public function xml2array($xmlObject, array $out = []): array
    {
        foreach ((array) $xmlObject as $index => $node) {
            $out[$index] = (is_object($node) || is_array($node)) ? $this->xml2array($node) : $node;
        }
        return $out;
    }

    // ---------------------------------------------------------------
    // Deprecated static façade — preserved for V2 callers that ran
    // `response::getInstance()` or `response::getError()` directly.
    // These will be removed in V4. New code should use the instance
    // returned by `apiCore::__call()`.
    // ---------------------------------------------------------------

    /**
     * @deprecated V3: pass through the response instance returned by
     *             __call instead of fetching this singleton.
     */
    public static function getInstance(): self
    {
        return new self();
    }

    /**
     * @deprecated V3: no longer needed — apiCore creates a fresh
     *             instance per call.
     */
    public static function resetData(): void
    {
        // no-op; preserved so V2 callers that happened to invoke this
        // don't break.
    }
}
