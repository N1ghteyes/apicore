<?php

namespace n1ghteyes\apicore;

use n1ghteyes\apicore\structure\apicore;

class client extends apiCore implements \Stringable
{
    public function __construct()
    {
        parent::__construct();
    }

    public function __toString(): string
    {
        return (string)$this->request;
    }
}
