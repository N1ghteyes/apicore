<?php

namespace n1ghteyes\apicore\structure;

class request implements \Stringable
{
    private $request;
    private $schema;
    private $server;
    private $basePath;
    private $version;
    private $useVersion;
    private $queryString = '';
    private $endpoint;
    private $path = array();

    public function __construct()
    {
    }

    /**
     * Function to set the request schema. By default assumes http
     * @param string $schema
     * @return mixed
     */
    public function setSchema($schema)
    {
        $this->schema = $schema;
        return $this;
    }

    /**
     * Function to set the api server address.
     * @param $address
     * @param $port
     * @return mixed
     */
    public function setServer($address, $port)
    {
        $this->server = $address.':'.$port;
        return $this;
    }

    /**
     * Function to set the base path of an API. for example, an api that always lives under '/api'
     * @param $path
     * @return $this
     */
    public function setBasePath($path)
    {
        $this->basePath = rtrim(ltrim((string) $path, '/'), '/');
        return $this;
    }

    /**
     * Function to set the version and if it should be used as part of the api request path
     * @param string $version
     * @param bool $flag
     * @return mixed
     */
    public function setVersion($version, $flag)
    {
        $this->version = $version;
        $this->useVersion = $flag;
        return $this;
    }

    /**
     * Function to add to the request path. If reset then the path is overwritten
     * @param $path
     * @param bool $reset
     * @return $this
     */
    public function addPathElement($path, $reset = false)
    {
        $elements = array();
        $path = ltrim((string) $path, '/');
        if (str_contains($path, '/')) {
            $elements = explode('/', $path);
        } else {
            $elements[] = $path;
        }
        if ($reset) {
            $this->path = $elements;
        } elseif (!empty($this->path)) {
            $this->path = array_merge($this->path, $elements);
        } else {
            $this->path = $elements;
        }
        return $this;
    }

    /**
     * Function to reset the path part of the request. Allows a request object to be reused.
     */
    public function resetPath()
    {
        $this->path = array();
        return $this;
    }

    /**
     * Function to add query string parameters to the request
     * @param $args
     */
    public function addQueryString($args)
    {
        $this->queryString = '?'.http_build_query($args);
    }

    /**
     * Function to reset the query string.
     */
    public function resetQueryString()
    {
        $this->queryString = '';
    }

    /**
     * Function to add
     * @param $endPoint
     */
    public function addEndpoint($endPoint)
    {
        $this->endpoint = $endPoint;
    }

    /**
     * Allow us to grab the last endpoint call that was made.
     * @return mixed
     */
    public function getEndpoint()
    {
        return $this->endpoint;
    }

    /**
     * Function to build the final request string
     */
    private function buildRequest()
    {
        $this->request = $this->schema . $this->server;
        if ($this->useVersion) {
            $this->request .= '/'.$this->version;
        }
        if ($this->basePath) {
            $this->request .= '/'.$this->basePath;
        }
        if (!empty($this->path)) {
            $this->request .= '/' . implode('/', $this->path);
        }
        $this->request .= '/'.$this->endpoint.$this->queryString;
        return $this;
    }

    /**
     * Implements php magic __toString method. Return the specific request made as a string (Sans arguments)
     * @return mixed
     */
    public function __toString(): string
    {
        $this->buildRequest();
        return (string) $this->request;
    }
}
