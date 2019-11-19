<?php

namespace WeSupply\Toolbox\Cron;

class ReturnsCron
{

    protected $logger;

    protected $eventManager;

    /**
     * Constructor
     *
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\Event\ManagerInterface $eventManager
)
    {
        $this->logger = $logger;
        $this->eventManager = $eventManager;
    }

    /**
     * Execute the cron
     *
     * @return void
     */
    public function execute()
    {
        $this->eventManager->dispatch('wesupply_return_request');
        $this->logger->addInfo("Cronjob ReturnsCron is executed.");
    }
}
