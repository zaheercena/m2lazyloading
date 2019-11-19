<?php

namespace WeSupply\Toolbox\Controller\Api;

use Magento\Framework\App\Response\Http;

class Notify extends \Magento\Framework\App\Action\Action
{
    private $returnsInterface;

    private $weSupplyApiInterface;

    private $helper;

    private $logger;

    /**
     * Flag constructor.
     * @param \Magento\Framework\App\Action\Context $context
     * @param \WeSupply\Toolbox\Helper\Data $helper
     * @param \WeSupply\Toolbox\Api\ReturnsInterface $returnsInterface
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \WeSupply\Toolbox\Helper\Data $helper,
        \WeSupply\Toolbox\Api\ReturnsInterface $returnsInterface,
        \WeSupply\Toolbox\Api\WeSupplyApiInterface $weSupplyApi,
        \Psr\Log\LoggerInterface $logger

    )
    {
        parent::__construct($context);
        $this->helper = $helper;
        $this->returnsInterface = $returnsInterface;
        $this->weSupplyApiInterface = $weSupplyApi;
        $this->logger = $logger;

    }



    public function execute()
    {
        $params = $this->getRequest()->getParams();
        $result = $this->_validateParams($params);

        if ($result) {
            /** Add the error response */
            //$response .= $this->addResponseStatus('true', 'ERROR', $result);
            $this->getResponse()
                ->setStatusCode(Http::STATUS_CODE_500)
                ->setContent($result);
            return;

        }else{
            if(isset($params['returns'])){
                $this->_eventManager->dispatch('wesupply_return_request');
            }
        }


    }

    /**
     * @param $params
     * @return bool|\Magento\Framework\Phrase
     */
    private function _validateParams($params)
    {
        $guid = isset($params['guid']) ? $params['guid'] : false;
        $clientName = isset($params['ClientName']) ? $params['ClientName'] : false;
        //$orderId  = isset($params['OrderId']) ? $params['OrderId'] : false;
        $requiredGuid = $this->helper->getGuid();
        $requiredClientName = $this->helper->getClientName();

        if (!$guid) {
            return __('guid is required. Please specify it');
        }
        if (!$clientName) {
            return __('ClientName is required. Please specify it');
        }

        if ($guid != $requiredGuid) {
            return __('guid is invalid');
        }
        if ($clientName != $requiredClientName) {
            return __('ClientName is invalid');
        }

        $this->guid = $guid;
        //$this->orderId = preg_replace("/^".OrderInfoBuilder::PREFIX.'/', '', $orderId);

        return false;
    }
}