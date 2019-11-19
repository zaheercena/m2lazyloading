<?php
namespace WeSupply\Toolbox\Observer;

use Magento\Framework\Event\ObserverInterface;

class UpdateOrderInfo implements ObserverInterface
{
    /**
     * @var \WeSupply\Toolbox\Api\OrderRepositoryInterface
     */
    protected $wesupplyOrderRepository;

    /**
     * @var \WeSupply\Toolbox\Model\OrderFactory
     */
    protected $wesupplyOrderFactory;

    /**
     * @var \WeSupply\Toolbox\Api\OrderInfoBuilderInterface
     */
    protected $orderInfoBuilder;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var \Magento\Framework\Stdlib\DateTime\TimezoneInterface
     */
    protected $timezone;

    /**
     * @param \WeSupply\Toolbox\Api\OrderRepositoryInterface $wesupplyOrderRepository
     * @param \WeSupply\Toolbox\Model\OrderFactory $wesupplyOrderFactory
     * @param \WeSupply\Toolbox\Api\OrderInfoBuilderInterface $orderInfoBuilder
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(
        \WeSupply\Toolbox\Api\OrderRepositoryInterface $wesupplyOrderRepository,
        \WeSupply\Toolbox\Model\OrderFactory $wesupplyOrderFactory,
        \WeSupply\Toolbox\Api\OrderInfoBuilderInterface $orderInfoBuilder,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $timezone
    )
    {
        $this->wesupplyOrderRepository = $wesupplyOrderRepository;
        $this->wesupplyOrderFactory = $wesupplyOrderFactory;
        $this->orderInfoBuilder = $orderInfoBuilder;
        $this->logger = $logger;
        $this->timezone = $timezone;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     * @return $this|void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $orderId = $observer->getData('orderId');

        $orderData = $this->orderInfoBuilder->gatherInfo($orderId);

        if (empty($orderData)) {
            $this->logger->error("WeSupply Error: OrderInfo gathering with order id $orderId is empty");
            return $this;
        }

        try {
            $orderInfo = $this->orderInfoBuilder->prepareForStorage($orderData);
            $wesupplyOrder = $this->wesupplyOrderRepository->getByOrderId($orderId);
            $wesupplyOrder->setOrderId($orderId);
            $wesupplyOrder->setStoreId($this->orderInfoBuilder->getStoreId($orderData));
            $wesupplyOrder->setInfo($orderInfo);
            /**
             * updated at in default Magento 2 UTC
             */
            $wesupplyOrder->setUpdatedAt(date("Y-m-d H:i:s"));
            $this->wesupplyOrderRepository->save($wesupplyOrder);
        } catch (\Exception $ex) {
            $this->logger->error("WeSupply Error: " . $ex->getMessage());
        }

        return $this;
    }



}
