<?php

namespace App\Service;

use Psr\Log\LoggerInterface;

class HealthCheckService
{
    private ?LoggerInterface $logger;

    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger;
    }
}
