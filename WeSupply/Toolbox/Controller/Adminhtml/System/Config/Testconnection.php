<?php
namespace WeSupply\Toolbox\Controller\Adminhtml\System\Config;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Phrase;
use WeSupply\Toolbox\Api\WeSupplyApiInterface;
use WeSupply\Toolbox\Helper\Data;


class Testconnection extends Action
{
    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var
     */
    protected $subdomain;

    /**
     * @var
     */
    protected $apiClientId;

    /**
     * @var
     */
    protected $apiClientSecret;

    /**
     * @var WeSupplyApiInterface
     */
    protected $weSupplyApi;

    /**
     * @var Data|Data
     */
    protected $helper;

    /**
     * Testconnection constructor.
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param WeSupplyApiInterface $weSupplyApi
     * @param Data $helper
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        WeSupplyApiInterface $weSupplyApi,
        Data $helper
    )
    {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->weSupplyApi = $weSupplyApi;
        $this->helper = $helper;
        parent::__construct($context);
    }

    /**
     * Collect relations data
     *
     * @return Json
     */
    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        $params = $this->getRequest()->getParams();
        $validationMessage = $this->_validateParams($params);

        if($validationMessage){
            return $result->setData(['success' => false, 'message' => $validationMessage]);
        }

        $apiPath = $this->subdomain.'.'.$this->helper->getWeSupplyDomain().'/api/';
        $this->weSupplyApi->setProtocol($this->helper->getProtocol());
        $this->weSupplyApi->setApiPath($apiPath);
        $this->weSupplyApi->setApiClientId($this->apiClientId);
        $this->weSupplyApi->setApiClientSecret($this->apiClientSecret);
        $credentialsCheck = $this->weSupplyApi->weSupplyAccountCredentialsCheck();
        $credentialsMessage = $credentialsCheck == true ? __('Valid account credentials') : __('Invalid account credentials');

        return $result->setData(['success' => $credentialsCheck, 'message' => $credentialsMessage]);
     }

    /**
     * @param $params
     * @return bool|Phrase
     */
    private function _validateParams($params)
    {
        $subdomain = isset($params['subdomain']) ? $params['subdomain'] : false;
        $apiClientId = isset($params['apiClientId']) ? $params['apiClientId'] : false;
        $apiClientSecret  = isset($params['apiClientSecret']) ? $params['apiClientSecret'] : false;


        if (!$subdomain) {
            return __('WeSupply Subdomain is required. Please specify it');
        }

        if (!$apiClientId) {
            return __('WeSupply Account Client Id is required. Please specify it');
        }

        if (!$apiClientSecret) {
            return __('WeSupply Account Client Secret is required. Please specify it');
        }

        $this->subdomain = $subdomain;
        $this->apiClientId = $apiClientId;
        $this->apiClientSecret = $apiClientSecret;

        return false;
    }

}