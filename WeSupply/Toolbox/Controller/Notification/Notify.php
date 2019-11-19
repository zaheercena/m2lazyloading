<?php
namespace WeSupply\Toolbox\Controller\Notification;

use Magento\Framework\App\Response\Http;

class Notify extends  \Magento\Framework\App\Action\Action
{

    /**
     * @var
     */
    protected $orderNo;

    /**
     * @var
     */
    protected $clientPhone;


    /**
     * @var \WeSupply\Toolbox\Api\WeSupplyApiInterface
     */
    protected $weSupplyApi;

    /**
     * @var \Magento\Framework\Controller\Result\JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var \WeSupply\Toolbox\Helper\Data
     */
    protected $helper;

    /**
     * @var \WeSupply\Toolbox\Helper\PhoneCodes
     */
    protected $phoneCodes;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \WeSupply\Toolbox\Helper\Data $helper,
        \WeSupply\Toolbox\Helper\PhoneCodes $phoneCodes,
        \WeSupply\Toolbox\Api\WeSupplyApiInterface $weSupplyApi,
        \Magento\Framework\Controller\Result\JsonFactory $jsonFactory
    )
    {
        parent::__construct($context);
        $this->helper = $helper;
        $this->phoneCodes = $phoneCodes;
        $this->weSupplyApi = $weSupplyApi;
        $this->resultJsonFactory = $jsonFactory;
    }



    public function execute()
    {
        $params = $this->getRequest()->getParams();
        $validation = $this->_validateParams($params);
        $result = $this->resultJsonFactory->create();
        $this->weSupplyApi->setProtocol($this->helper->getProtocol());
        $this->weSupplyApi->setApiPath($this->helper->getWeSupplySubDomain().'.'.$this->helper->getWeSupplyDomain().'/');

        if ($validation) {
            /** Add validation error response */
            return $result->setData(['success' => false, 'error' => $validation]);
        } else {
            $response = $this->weSupplyApi->notifyWeSupply($this->orderNo, $this->clientPhone);
        }

        if(!$response){
            /** Add Api communication error response */
            return $result->setData(['success' => false, 'error' => 'Error signing up for SMS updates!']);
        }

        if(is_array($response)){

            if(isset($response['error'])) {
                return $result->setData(['success' => false, 'error' => 'Error encountered : '.$response['error']]);
            }

            $result->setData(['success' => false, 'error' => 'Error signing up for SMS updates!']);
        }

        return $result->setData(['success' => true]);
    }


    /**
     * @param $params
     * @return bool|\Magento\Framework\Phrase
     */
    private function _validateParams($params)
    {
        $orderNo = isset($params['order']) ? $params['order'] : false;
        $clientPhone = isset($params['phone']) ? trim($params['phone']) : false;
        $phoneCountryCode = isset($params['countryCode']) ? trim($params['countryCode']) : false;

        if (!$orderNo) {
            return __('Order number missing');
        }
        if (!$clientPhone) {
            return __('Client Phone is missing');
        }
        if (!$phoneCountryCode) {
            return __('Country Phone Code is missing');
        }

        $this->orderNo = $orderNo;

        $valid = $this->phoneCodes->validatePhoneNr($phoneCountryCode, $clientPhone);

        if(!$valid){
            return __('Invalid Phone Number');
        }

        $this->clientPhone = $phoneCountryCode.$clientPhone;

        return false;
    }



}