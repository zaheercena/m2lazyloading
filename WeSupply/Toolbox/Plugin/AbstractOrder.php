<?php
namespace WeSupply\Toolbox\Plugin;

class AbstractOrder
{
    /**
     * @var \Magento\Framework\Event\ManagerInterface
     */
    protected $eventManager;

    /**
     * @var \WeSupply\Toolbox\Helper\Data
     */
    protected $helper;

    /**
     * @param \Magento\Framework\Event\ManagerInterface $eventManager
     */
    public function __construct(
        \Magento\Framework\Event\ManagerInterface $eventManager,
        \WeSupply\Toolbox\Helper\Data $helper
    )
    {
        $this->eventManager = $eventManager;
        $this->helper = $helper;
    }
}
