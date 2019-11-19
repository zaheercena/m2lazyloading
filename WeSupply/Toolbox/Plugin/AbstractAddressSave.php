<?php
namespace WeSupply\Toolbox\Plugin;

class AbstractAddressSave
{
    /**
     * @var \Magento\Sales\Api\OrderAddressRepositoryInterface
     */
    protected $orderAddressRepository;

    /**
     * @var \Magento\Framework\Event\ManagerInterface
     */
    protected $eventManager;

    /**
     * @var \WeSupply\Toolbox\Helper\Data
     */
    protected $helper;

    /**
     * AbstractAddressSave constructor.
     * @param \Magento\Framework\Event\ManagerInterface $eventManager
     * @param \Magento\Sales\Api\OrderAddressRepositoryInterface $orderAddressRepository
     * @param \WeSupply\Toolbox\Helper\Data $helper
     */
    public function __construct(
        \Magento\Framework\Event\ManagerInterface $eventManager,
        \Magento\Sales\Api\OrderAddressRepositoryInterface $orderAddressRepository,
        \WeSupply\Toolbox\Helper\Data $helper
    )
    {
        $this->eventManager = $eventManager;
        $this->orderAddressRepository = $orderAddressRepository;
        $this->helper = $helper;
    }
}
