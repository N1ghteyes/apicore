<?php

namespace n1ghteyes\apicore\interfaces;

interface coreInterface {
    /**
     * coreInterface constructor.
     */
    function __construct();

    /**
     * Function to set the request schema. By default assumes http
     * @param string $schema
     * @return mixed
     */
    function setSchema($schema = 'http');


    /**
     * Function to set the api server address.
     * @param $address
     * @param $port
     * @return mixed
     */
    function setServer($address, $port);

    /**
     * Function to set the version and if it should be used as part of the api request path
     * @param string $version
     * @param bool $flag
     * @return mixed
     */
    function setVersion($version, $flag = TRUE);

    /**
     * Function to set the method used in the request
     * @param $method
     * @return mixed
     */
    function setHTTPMethod($method);

    /**
     * Function to set the type of data being sent and received
     * @param $format
     * @return mixed
     */
    function setBodyFormat($format);

    /**
     * Set auto Auth information, if it is needed.
     * @return mixed
     */
    function auth($user, $pass, $type = 'basic');

    /**
     * PHP Magic function. Should be used to set the HTTP method of a request
     * @param string $name
     * @return mixed
     */
    function __get($name);

    /**
     * Implements magic PHP __call method. Turns undeclared method calls into api endpoint requests
     * @param $name
     * @param $arguments
     * @return mixed
     */
    function __call($name, $arguments);
}