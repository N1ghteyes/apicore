<?php

namespace n1ghteyes\apicore\interfaces;

interface coreInterface
{
    /**
     * coreInterface constructor.
     */
    public function __construct();

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
     * PHP Magic function. Should be used to set the HTTP method of a request
     * @param string $name
     * @return mixed
     */
    public function __get($name);

    /**
     * Implements magic PHP __call method. Turns undeclared method calls into api endpoint requests
     * @param $name
     * @param $arguments
     * @return mixed
     */
    public function __call($name, $arguments);
}
