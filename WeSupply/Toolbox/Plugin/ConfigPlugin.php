<?php

namespace WeSupply\Toolbox\Plugin;

use Magento\Framework\App\Action\Context;
use Magento\Framework\Phrase;
use WeSupply\Toolbox\Api\WeSupplyApiInterface;
use WeSupply\Toolbox\Helper\Data as Helper;

class ConfigPlugin
{
    /**
     * @var \Magento\Framework\Message\ManagerInterface
     */
    protected $messageManager;

    /**
     * @var WeSupplyApiInterface
     */
    protected $_weSupplyApi;

    /**
     * @var Helper
     */
    private $_helper;

    /**
     * API Credentials
     */
    private $subdomain;
    private $apiClientId;
    private $apiClientSecret;

    /**
     * ConfigPlugin constructor.
     * @param Context $context
     * @param WeSupplyApiInterface $weSupplyApi
     * @param Helper $helper
     */
    public function __construct(
        Context $context,
        WeSupplyApiInterface $weSupplyApi,
        Helper $helper
    ) {
        $this->messageManager = $context->getMessageManager();
        $this->_weSupplyApi = $weSupplyApi;
        $this->_helper = $helper;
    }

    /**
     * @param \Magento\Config\Model\Config $subject
     * @return \Magento\Config\Model\Config
     */
    public function beforeSave(\Magento\Config\Model\Config $subject)
    {
        $groups = $subject->getGroups();
        $enableSmsNotification = $this->_helper->recursivelyGetArrayData(['step_4','fields','checkout_page_notification','value'], $groups, false);

        /**
         * Return if groups does not have WeSupply Notification settings
         * or if the SMS Notification status has no changes
         */
        $currentSmsNotificationStatus = $this->_helper->getEnabledNotification();
        if (!$enableSmsNotification || $enableSmsNotification == $currentSmsNotificationStatus) {
            return $subject;
        }

        $apiCredentials = array_merge($groups['step_1']['fields'], $groups['step_2']['fields']);
        $params = $this->prepareApiParams($apiCredentials);

        if ($params['has_error']) {
            $this->messageManager->addErrorMessage(__($params['validation_message']));
            $groups['step_4']['fields']['checkout_page_notification']['value'] = 0;
            $subject->setData('groups', $groups);

            return $subject;
        }

        $apiPath = $this->subdomain.'.'.$this->_helper->getWeSupplyDomain().'/api/';
        $this->_weSupplyApi->setProtocol($this->_helper->getProtocol());
        $this->_weSupplyApi->setApiPath($apiPath);
        $this->_weSupplyApi->setApiClientId($this->apiClientId);
        $this->_weSupplyApi->setApiClientSecret($this->apiClientSecret);

        $apiResponse = $this->_weSupplyApi->checkServiceAvailability('sms');

        if (!$apiResponse) {
            $groups['step_4']['fields']['checkout_page_notification']['value'] = 0;
            $subject->setData('groups', $groups);

            return $subject;
        }

        if (isset($apiResponse['allowed']) && $apiResponse['allowed'] === false) {
            $this->messageManager->addErrorMessage(__('SMS alert notification is only available in Startup and Pro plan, please update you plan.'));
            $groups['step_4']['fields']['checkout_page_notification']['value'] = 0;
            $subject->setData('groups', $groups);
        }

        return $subject;
    }

    /**
     * @param $apiCredentials
     * @return array
     */
    private function prepareApiParams($apiCredentials)
    {
        $response = [
            'has_error' => false,
            'validation_message' => ''
        ];

        $params = $params = [
            'subdomain' => $apiCredentials['wesupply_subdomain']['value'],
            'apiClientId' => $apiCredentials['wesupply_client_id']['value'],
            'apiClientSecret' => $apiCredentials['wesupply_client_secret']['value']
        ];

        $validationMessage = $this->_validateParams($params);
        if ($validationMessage) {
            $response['has_error'] = true;
            $response['validation_message'] = $validationMessage;
        }

        return $response;
    }

    /**
     * @param $params
     * @return bool|Phrase
     */
    private function _validateParams($params)
    {
        if (!$params['subdomain'] || empty($params['subdomain'])) {
            return __('WeSupply Subdomain is required in order to enable SMS alert notification.');
        }

        if (!$params['apiClientId'] || empty($params['apiClientId'])) {
            return __('WeSupply Account Client Id is required in order to enable SMS alert notification.');
        }

        if (!$params['apiClientSecret'] || empty($params['apiClientSecret'])) {
            return __('WeSupply Account Client Secret is required in order to enable SMS alert notification.');
        }

        $this->subdomain = $params['subdomain'];
        $this->apiClientId = $params['apiClientId'];
        $this->apiClientSecret = $params['apiClientSecret'];

        return false;
    }
}