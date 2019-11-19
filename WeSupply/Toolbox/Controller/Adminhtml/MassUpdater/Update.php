<?php
namespace WeSupply\Toolbox\Controller\Adminhtml\MassUpdater;

class Update extends \WeSupply\Toolbox\Controller\Adminhtml\MassUpdater
{
    /**
     * @var int
     */
    protected $limit;

    /**
     * @var int
     */
    protected $offset;

    /**
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $result = [];
        $result['success'] = true;

        $params = $this->getRequest()->getParams();
        $validation = $this->_validateParams($params);

        if ($validation) {
            $result['msg'] = $validation;
            $result['success'] = false;
        } else {
            try {
                $orderCollection = $this->orderCollectionFactory->create();
                $orderIds = $orderCollection->getAllIds($this->limit, $this->offset);

                foreach ($orderIds as $orderId) {
                    $this->_eventManager->dispatch(
                        'wesupply_order_update',
                        ['orderId' => $orderId]
                    );
                }

            } catch (\Exception $ex) {
                $result['msg'] = $ex->getMessage();
                $result['success'] = false;
            }
        }

        $resultJson = $this->resultJsonFactory->create();
        $resultJson->setData($result);
        return $resultJson;
    }

    /**
     * @param $params
     * @return bool|\Magento\Framework\Phrase|mixed|string
     */
    protected function _validateParams($params) {
        $limit = isset($params['limit']) ? $params['limit'] : false;
        $offset = isset($params['offset']) ? $params['offset'] : false;

        if (!$limit) {
            return __('Limit is required. Please specify it');
        }
        if (!$offset && $offset != 0) {
            return __('Offset is required. Please specify it');
        }

        $this->limit = $limit;
        $this->offset = $offset;

        return false;
    }
}