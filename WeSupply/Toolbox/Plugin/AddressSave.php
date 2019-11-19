<?php
namespace WeSupply\Toolbox\Plugin;

class AddressSave extends AbstractAddressSave
{
    /**
     * @param \Magento\Sales\Controller\Adminhtml\Order\AddressSave $subject
     * @param $result
     * @return mixed
     */
    public function afterExecute(
        \Magento\Sales\Controller\Adminhtml\Order\AddressSave $subject, $result
    )
    {
        if($this->helper->getWeSupplyEnabled()) {

            $addressId = $subject->getRequest()->getParam('address_id') ?? false;
            if ($addressId) {
                $address = $this->orderAddressRepository->get($addressId);
                /**
                 * only shipping address is saved in the wesupply_orders table,
                 * so no need to dispatch event and update table if billing address is edited
                 */
                if ($address->getAddressType() == 'billing') {
                    return $result;
                }

                $orderId = $address->getParentId() ?? false;

                if ($orderId) {
                    $this->eventManager->dispatch(
                        'wesupply_order_update',
                        ['orderId' => $orderId]
                    );
                }
            }
        }
        return $result;

    }
}
