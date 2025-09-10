<?php

namespace App\Service;

use Psr\Log\LoggerInterface;

class SondageService
{
    private ?LoggerInterface $logger;

    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger;
    }
}
