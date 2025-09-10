<?php

namespace App\Service;

use Psr\Log\LoggerInterface;

class LiveFeedService
{
    private ?LoggerInterface $logger;

    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger;
    }
}
