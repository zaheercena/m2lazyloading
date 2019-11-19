<?php
namespace WeSupply\Toolbox\Controller\Adminhtml\MassUpdater;

class Getorders extends \WeSupply\Toolbox\Controller\Adminhtml\MassUpdater
{
    /**
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $result = [];
        $result['success'] = true;

        try {
            $orderCollection = $this->orderCollectionFactory->create();
            $orderNumbers = $orderCollection->getSize();
            $result['ordersNr'] = $orderNumbers;
        } catch (\Exception $ex) {
            $result['msg'] = $ex->getMessage();
            $result['success'] = false;
        }

        $resultJson = $this->resultJsonFactory->create();
        $resultJson->setData($result);
        return $resultJson;
    }
}