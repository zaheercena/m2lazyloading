<?php
namespace WeSupply\Toolbox\Plugin;

class Order extends AbstractOrder
{
       /**
     * @param \Magento\Sales\Model\Order $subject
     * @param $result
     * @return mixed
     */
    public function afterSave(
        \Magento\Sales\Model\Order $subject, $result
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
