<?php
namespace WeSupply\Toolbox\Controller\Order;

use Magento\Setup\Exception;
use Magento\Framework\App\Response\Http;
use WeSupply\Toolbox\Model\OrderInfoBuilder;

class Cancel extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \WeSupply\Toolbox\Helper\Data
     */
    protected $helper;

    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    protected $orderRepositoryInterface;

    /**
     * @var int
     */
    protected $orderId;

    /**
     * Cancel constructor.
     * @param \Magento\Framework\App\Action\Context $context
     * @param \WeSupply\Toolbox\Helper\Data $helper
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepositoryInterface
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \WeSupply\Toolbox\Helper\Data $helper,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepositoryInterface

    )
    {
        parent::__construct($context);
        $this->helper = $helper;
        $this->orderRepositoryInterface = $orderRepositoryInterface;
    }


    public function execute()
    {
        $response = '';
        $params = $this->getRequest()->getParams();
        $result = $this->_validateParams($params);

        if ($result) {
            /** Add the error response */
            $this->getResponse()
                ->setStatusCode(Http::STATUS_CODE_500)
                ->setContent($result);
            return;
        } else {
            /** Get the orders from the required interval */
            $return = $this->cancelOrder();
            if($return['success']) {
                $response .= $this->addResponseStatus('false', 'SUCCESS', '');
            }
            else {
                $this->getResponse()
                    ->setStatusCode(Http::STATUS_CODE_500)
                    ->setContent('Error: '.$return['failure_reason']);
                return;
            }
        }

        $response = '<Orders>' . $response . '</Orders>';
        $xml = simplexml_load_string($response);  // Might be ignored this and just send the $response as result

        $this->getResponse()->setHeader('Content-type', 'text/xml; charset=utf-8');
        $this->getResponse()->setBody($xml->asXML());
    }


    /**
     * @return bool|string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function cancelOrder()
    {
        $return = array();
        if($this->orderId)
        {

            try {
                $order = $this->orderRepositoryInterface->get($this->orderId);

                if ($order->canCancel()) {
                    $order->cancel();
                    $this->orderRepositoryInterface->save($order);
                    $return['success'] = true;
                    $return['failure_reason'] = '';
                } else {
                    $return['success'] = false;
                    $return['failure_reason'] = 'Order with id ' . $this->orderId . ' can\'t be canceled';

                }
            }catch(Exception $exception)
            {
                $return['success'] = false;
                $return['failure_reason'] = $exception->getMessage();
            }
        }
        else{
            $return['success'] = false;
            $return['failure_reason'] = 'No Order Id';
        }

         return $return;

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