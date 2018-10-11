<?php
namespace n1ghteyes\apicore\structure;

use n1ghteyes\apicore\interfaces\coreInterface;
use GuzzleHttp;

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
abstract class apiCore implements coreInterface{

    protected $request;
    protected $version;
    private $httpMethod = 'GET';
    private $bodyFormat = 'body';
    private $lastResult;
    private $args = [];
    private $rawResponse = FALSE;
    private $processedResponse = FALSE;

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
    public function setBasePath($path){
        $this->request->setBasePath($path);
        return $this;
    }

    /**
     * Function to set API version and whether this should be used in the request URL
     * @param string $version
     * @param bool $flag
     * @return self
     */
    public function setVersion($version, $flag = TRUE)
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
    public function setBodyFormat($format = 'form'){
        switch($format){
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
    public function getVersion(){
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
            case 'basic':
            default:
                $this->args['auth'] = [$key, $value];
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
     * Magic __call method, will translate all function calls to object to API requests
     * @param $name - name of the function
     * @param $arguments - an array of arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        $client = new GuzzleHttp\Client();
        $query = count($arguments) < 1 || !is_array($arguments[0]) ? [] : $arguments[0];
        $this->request->addEndpoint($name);
        $this->processArgs($query);
        try {
            $res = $client->request($this->httpMethod, (string)$this->request, $this->args);
            $this->processResult($res);
        } catch (GuzzleHttp\Exception\GuzzleException $e){
            print "<pre>";
            print_r($e->getCode());
            print_r($e->getMessage());
            print "</pre>";
        }

        return $this->processedResponse;
    }

    /**
     * Function to process the response from Guzzle, based on the expected format
     * @param $response
     * @return mixed
     */
    public function processResult($response){
        $type = array_shift($response->getHeader('Content-Type'));
        $this->rawResponse = (string)$response->getBody();
        $status = $response->getStatusCode();

        switch($type){
            case 'application/json':
            default:
                $this->processedResponse = json_decode($this->rawResponse);
                break;
        }
        return $this;
    }

    /**
     * Function to get the last result returned by an API call.
     * @return mixed
     */
    public function getLastResult(){
        return $this->lastResult;
    }

    /**
     * Function to reset the path in the api request
     */
    public function resetPath(){
        $this->request->resetPath();
    }

    /**
     * Function to set some default cURL arguments, such as SSL version.
     */
    private function setDefaultCurlOpts(){
        $this->args['config'] =
            [
                'curl' => [
                    CURLOPT_SSLVERSION => 6,
                ]
            ];
    }

    /**
     * Function to process the arguments passed depending on selected http method.
     * @param array $args
     * @return self
     */
    private function processArgs($args){
        if(!empty($args)) {
            switch ($this->httpMethod) {
                case 'GET':
                    $this->request->addQueryString($args);
                    break;
                case 'PUT':
                case 'POST':
                    $this->args[$this->bodyFormat] = $args;
                    break;
                    break;
                case 'DELETE':
                    $this->args['query'] = $args;
                    break;
            }
        }
        return $this;
    }
}
