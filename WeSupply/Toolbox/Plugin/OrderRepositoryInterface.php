<?php
namespace WeSupply\Toolbox\Plugin;

class OrderRepositoryInterface extends AbstractOrder
{
    /**
     * @param \Magento\Sales\Api\OrderRepositoryInterface $subject
     * @param $result
     * @return mixed
     */
    public function afterSave(
        \Magento\Sales\Api\OrderRepositoryInterface $subject, $result
    )
    {
        if($this->helper->getWeSupplyEnabled()) {
            $orderId = $result->getEntityId();
            $this->eventManager->dispatch(
                'wesupply_order_update',
                ['orderId' => $orderId]
            );
        }
        return $result;
    }


}
