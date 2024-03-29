<?php

namespace n1ghteyes\apicore\structure;

use n1ghteyes\apicore\interfaces\coreInterface;
use GuzzleHttp;
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
     * apiCore constructor.
     */
    public function __construct()
    {
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
        switch($format) {
            case 'form':
                $this->bodyFormat = 'form_params';
                break;
            default:
                $this->bodyFormat = $format;
                break;
        }
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
        $this->httpMethod = strtoupper($method);
    }

    /**
     * Magic __get function for setting the HTTP method and API path
     * @param string $name
     * @return $this|mixed
     */
    public function __get($name)
    {
        switch (strtolower($name)) {
            case 'get':
                $this->setHTTPMethod('GET');
                break;
            case 'post':
                $this->setHTTPMethod('POST');
                break;
            case 'put':
                $this->setHTTPMethod('PUT');
                break;
            case 'delete':
                $this->setHTTPMethod('DELETE');
                break;
            default:
                $this->request->addPathElement($name);
        }
        return $this;
    }

    /**
     * Occasionally we need to force a request, for example to the base domain.
     * @param string $name
     * @param array $arguments
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
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        $client = new GuzzleHttp\Client();
        $query = count($arguments) < 1 || !is_array($arguments) ? array() : $arguments[0];
        //Allow endpoints starting with an integer to be prepended with an underscore to make them valid method calls for PHP.
        if (strpos($name, '_') === 0) {
            $name = strlen($name) > 1 && is_numeric($name[1]) ? ltrim($name, '_') : $name;
        }
        $this->request->addEndpoint($name);
        $this->processArgs($query);
        //clear the response object from last call.
        response::resetData();
        $response = response::getInstance();
        $response::verbUsed($this->httpMethod); //set the verb used for the request,
        //do we have a logging class? If so, add data to it.
        if ($this->logger !== null) {
            $this->logger->addMethod($this->httpMethod);
            $this->logger->addRequestURL((string)$this->request);
            $this->logger->addRequestArgs(json_encode($this->args));
            $this->logger->addRequestEndpoint($name);
            $this->logger->setRequestTime(time());
        }
        try {
            $result = $client->request($this->httpMethod, (string)$this->request, $this->args);
            //$this->processResult($result);
            $response::processResult($result);
        } catch (GuzzleHttp\Exception\GuzzleException $e) {
            $response::addError($e->getCode(), $e->getMessage());
        }

        if ($this->logger !== null) {
            $this->logger->setResponseTime(time());
            if (!empty($error = $response::getError())) {
                $this->logger->addRawResponse($error['message']);
                $this->logger->addResponseStatusCode($error['code']);
            } else {
                $this->logger->addRawResponse($response->rawBodyData);
                $this->logger->addResponseStatusCode($response->statusCode);
            }
        }

        //reset some stuff post-query so we can handle the next one cleanly. Leave auth and headers in place by default.
        $this->request->resetQueryString();
        unset($this->args['query']);
        unset($this->args[$this->bodyFormat]);

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
