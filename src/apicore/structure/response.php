<?php

namespace n1ghteyes\apicore\structure;

/**
 * @todo make this better.
 * Class response
 * @package n1ghteyes\apicore\structure
 */
class response{

    public $statusCode;
    public $dataType;
    public $data;
    public $verb;
    public $rawBodyData;

    private static $response;
    private static $error;

    private function __construct(){}

    /**
     * Static function to allow us to return a new response object.
     * @return response
     */
    public static function getInstance(){
        if(!self::$response) {
            self::$response = new response();
        }
        return self::$response;
    }

    /**
     * Allow the verb used to be recorded as guzzle doesn't provide this info post request.
     * @param $verb
     */
    public static function verbUsed($verb){
        self::$response->verb = $verb;
    }

    /**
     * Process the provided guzzle response object.
     * @param $guzzleResponse
     */
    public static function processResult($guzzleResponse){
        self::$response->dataType = array_shift($guzzleResponse->getHeader('Content-Type'));
        self::$response->statusCode = $guzzleResponse->getStatusCode();
        self::$response->rawBodyData = $guzzleResponse->getBody();

        switch(self::$response->dataType){
            case 'application/json':
            default:
            self::$response->data = json_decode(self::$response->rawBodyData);
                break;
        }
    }

    /**
     * @param $code
     * @param $message
     */
    public static function addError($code, $message){
        self::$error = array('code' => $code, 'message' => $message);
    }
}