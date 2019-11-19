<?php

namespace WeSupply\Toolbox\Controller\Order;

use Magento\Framework\App\Response\Http;
use WeSupply\Toolbox\Model\OrderInfoBuilder;

class FetchSingleOrder extends \Magento\Framework\App\Action\Action
{

    /**
     * @var string
     */
    protected $guid;


    /**
     * @var
     */
    protected $orderId;
    /**
     * @var \WeSupply\Toolbox\Helper\Data
     */
    protected $helper;


    /**
     * @var \WeSupply\Toolbox\Api\OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * Fetch constructor.
     * @param \Magento\Framework\App\Action\Context $context
     * @param \WeSupply\Toolbox\Helper\Data $helper
     * @param \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder
     * @param \Magento\Framework\Api\SortOrderBuilder $sortOrderBuilder
     * @param \WeSupply\Toolbox\Api\OrderRepositoryInterface $orderRepository
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \WeSupply\Toolbox\Helper\Data $helper,
        \WeSupply\Toolbox\Api\OrderRepositoryInterface $orderRepository
    )
    {
        parent::__construct($context);
        $this->helper = $helper;
        $this->orderRepository = $orderRepository;
    }

    public function execute()
    {
        $response = '';
        $params = $this->getRequest()->getParams();
        $result = $this->_validateParams($params);

        if ($result) {
            /** Add the error response */
           // $response .= $this->addResponseStatus('true', 'ERROR', $result);
            $this->getResponse()
                ->setStatusCode(Http::STATUS_CODE_500)
                ->setContent($result);
            return;
        } else {
            /** Get the desired order data */
            $response .= $this->fetchOrder();
            $response .= $this->addResponseStatus('false', 'SUCCESS', '');
        }

        $response = '<Orders>' . $response . '</Orders>';
        $xml = simplexml_load_string($response);  // Might be ignored this and just send the $response as result

        $this->getResponse()->setHeader('Content-type', 'text/xml; charset=utf-8');
        $this->getResponse()->setBody($xml->asXML());
    }

    /**
     * @return null|string
     */
    protected function fetchOrder()
    {
        $order = $this->orderRepository->getByOrderId($this->orderId);
        $orderXml = $order->getInfo();
        return $orderXml;
    }

    /**
     * @param string $hasError
     * @param string $errorCode
     * @param string $errorDescription
     * @return string
     */
    protected function addResponseStatus($hasError, $errorCode, $errorDescription)
    {
        return "<Response>" .
            "<ResponseHasErrors>$hasError</ResponseHasErrors>" .
            "<ResponseCode>$errorCode</ResponseCode>" .
            "<ResponseDescription>$errorDescription</ResponseDescription>"
            . "</Response>";
    }

    /**
     * @param $params
     * @return bool|\Magento\Framework\Phrase
     */
    private function _validateParams($params)
    {
        $guid = isset($params['guid']) ? $params['guid'] : false;
        $clientName = isset($params['ClientName']) ? $params['ClientName'] : false;
        $orderId  = isset($params['OrderId']) ? $params['OrderId'] : false;
        $requiredGuid = $this->helper->getGuid();
        $requiredClientName = $this->helper->getClientName();

        if (!$guid) {
            return __('guid is required. Please specify it');
        }
        if (!$clientName) {
            return __('ClientName is required. Please specify it');
        }
        if(!$orderId) {
            return __('OrderId is required. Please specify it');
        }
        if ($guid != $requiredGuid) {
            return __('guid is invalid');
        }
        if ($clientName != $requiredClientName) {
            return __('ClientName is invalid');
        }

        $this->guid = $guid;
        $this->orderId = preg_replace("/^".OrderInfoBuilder::PREFIX.'/', '', $orderId);

        return false;
    }
}