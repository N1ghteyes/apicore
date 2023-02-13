<?php

namespace n1ghteyes\apicore;

use n1ghteyes\apicore\structure\apicore;

class client extends apiCore
{
    public function __construct()
    {
        parent::__construct();
    }

    public function __toString()
    {
        return (string)$this->request;
    }
}
