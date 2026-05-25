<?php

namespace n1ghteyes\apicore;

use GuzzleHttp\ClientInterface;
use n1ghteyes\apicore\structure\apicore;

class client extends apiCore implements \Stringable
{
    public function __construct(?ClientInterface $client = null)
    {
        parent::__construct($client);
    }

    public function __toString(): string
    {
        return (string)$this->request;
    }
}
