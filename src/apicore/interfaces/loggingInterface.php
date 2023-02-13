<?php

namespace n1ghteyes\apicore\interfaces;

interface loggingInterface
{
    public function addMethod($method);
    public function addRequestURL($method);
    public function addRequestArgs($method);
    public function addRequestEndpoint($method);
    public function setRequestTime($time);

    public function setResponseTime($time);
    public function addRawResponse($rawResponse);
    public function addResponseStatusCode($status);
}
