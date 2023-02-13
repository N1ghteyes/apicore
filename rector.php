<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\SetList;

return function (RectorConfig $rectorConfig): void {
    $rectorConfig->import(SetList::CODE_QUALITY);
};
