<?php

/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace WeSupply\Toolbox\Controller\Estimations;


use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Customer\Model\Session as CustomerSession;
use WeSupply\Toolbox\Helper\Data as Helper;
use WeSupply\Toolbox\Helper\Estimates as EstimatesHelper;
use WeSupply\Toolbox\Block\Estimations\Delivery as EstimatesDelivery;
use WeSupply\Toolbox\Logger\Logger as Logger;

/**
 * Class DeliveryProduct
 * @package WeSupply\Toolbox\Controller\Estimations
 */

class DeliveryProduct extends Action
{
    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;
    
    /**
     * @var Json
     */
    protected $json;
    
    /**
     * @var CustomerSession
     */
    protected $customerSession;
    
    /**
     * @var Helper
     */
    protected $helper;
    
    /**
     * @var EstimatesHelper
     */
    protected $estimatesHelper;
    
    /**
     * @var EstimatesDelivery
     */
    protected $estimatesDelivery;
    
    /**
     * @var Logger
     */
    protected $logger;
    
    /**
     * @var $params
     */
    protected $params;
    
    /**
     * @var $product
     * currently loaded or selected product
     */
    protected $product;
    
    /**
     * @var $configParent
     * the config parent of selected associated simple
     */
    protected $configParent = null;
    
    /**
     * DeliveryProduct constructor.
     * @param Context $context
     * @param JsonFactory $jsonFactory
     * @param Json $json
     * @param CustomerSession $customerSession
     * @param EstimatesDelivery $estimatesDelivery
     * @param Helper $helper
     * @param EstimatesHelper $estimatesHelper
     * @param Logger $logger
     */
    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        Json $json,
        CustomerSession $customerSession,
        EstimatesDelivery $estimatesDelivery,
        Helper $helper,
        EstimatesHelper $estimatesHelper,
        Logger $logger
    )
    {
        $this->resultJsonFactory = $jsonFactory;
        $this->json = $json;
        $this->customerSession = $customerSession;
        $this->estimatesDelivery = $estimatesDelivery;
        $this->helper = $helper;
        $this->estimatesHelper = $estimatesHelper;
        $this->logger = $logger;
    
        parent::__construct($context);
    }
    
    /**
     * Send request to WeSupply API to get the estimated delivery time
     */
    public function execute()
    {
        $this->params = $this->getRequest()->getParams();
        if (
            !isset($this->params['postcode']) || empty($this->params['postcode']) ||
            !isset($this->params['country_code']) || empty($this->params['country_code'])
        ) {
            // last chance to get proper address details
            if ($defaultAddress = $this->estimatesHelper->getDefaultAddress()) {
                $this->params['postcode'] = $defaultAddress->getPostcode();
                $this->params['country_code'] = $defaultAddress->getCountryId();
                // set params for further use
                $this->getRequest()->setParams([
                    'postcode' => $this->params['postcode'],
                    'country_code' => $this->params['country_code']
                ]);
            }
        }
        $result = $this->resultJsonFactory->create();
        
        if (!$this->params['allowed_countries']) {
            // there is no allowed countries set under WeSupply configuration page
            $this->logger->error('WeSupply Settings > Estimates > Allowed Countries are not set!');
            $response = ['success' => false, 'estimate' => ['error' => __('')]];
            return $result->setData($response);
        }
    
        try {
            $response = $this->estimatesDelivery->getEstimations();
            if (!$response) {
                $this->logger->error('No response from WeSupply');
                $response = ['success' => false, 'estimate' => ['error' => __('')]];
                return $result->setData($response);
            }
            
            if (isset($response['error'])) {
                $response = $this->processErrorResponse($response);
                return $result->setData($response);
            }
    
            $response = ['success' => true, 'estimate' => $this->processEstimatedResponse($response)];
            return $result->setData($response);
            
        } catch (\Exception $e) {
            $this->logger->error('WeSupply communication error! ' . $e->getMessage());
            $response = ['success' => false, 'estimate' => ['error' => $e->getMessage()]];
            $result->setData($response);
        }
    }
    
    /**
     * @param $estimationData
     * @return mixed
     */
    private function processEstimatedResponse(&$estimationData)
    {
        $estimatedTimestamp = $estimationData['estimated_delivery_date'];
        if ($estimationData['location_id'] == '-' || $estimationData['location_country'] == '-') {
            $estimationData['estimated_delivery_date'] = __('Not available due to an invalid postcode.');
            $this->resetLocation($estimationData);
            return $estimationData;
        }
    
        // just make sure there are no changes between page load and response display
        $allowedCountries = $this->refreshAllowedCountries($estimationData['allowed_countries']);
        if (!in_array($estimationData['location_country'], $allowedCountries)) {
            $estimationData['estimated_delivery_date'] = __('Shipping to %1 is not allowed.', $estimationData['location_country']);
            $this->resetLocation($estimationData);
            return $estimationData;
        }
        
        // apply date format
        $estimationsFormat = $this->helper->getDeliveryEstimationsFormat() ??  'd F';
        $estimationData['estimated_delivery_date'] = date($estimationsFormat, $estimatedTimestamp);
        
        // apply estimation as range
        $estimationsRange = $this->helper->getDeliveryEstimationsRange() ?? 0;
        if (!$estimationsRange) {
            return $estimationData;
        }
        $estimatedRange = date($estimationsFormat, strtotime('+' . $estimationsRange . ' days', $estimatedTimestamp));
        $estimationData['estimated_delivery_date'] = date($estimationsFormat, $estimatedTimestamp) . ' - ' . $estimatedRange;
        
        return $estimationData;
    }
    
    /**
     * @param array $weSupplyAllowedCountries
     * @return mixed
     */
    private function refreshAllowedCountries($weSupplyAllowedCountries = [])
    {
        if (!count($weSupplyAllowedCountries)) { // reset then re-fetch WeSupply Allowed Countries
            $this->customerSession->unsAllowedCountries();
        }
        
        $allowedCountries = $this->helper->getShippingAllowedCountries();
        if ( // compare the old values of allowed countries with the new ones just received from WeSupply
            count($weSupplyAllowedCountries) &&
            $this->json->serialize($weSupplyAllowedCountries) != $this->json->serialize($allowedCountries)
        ) {
            $this->customerSession->setAllowedCountries($weSupplyAllowedCountries);
        }
        
        return $this->customerSession->getAllowedCountries();
    }
    
    /**
     * Remove/reset invalid data
     * @param $estimationData
     */
    private function resetLocation(&$estimationData)
    {
        $estimationData['location_id'] = $estimationData['location_country'] = '';
    }
    
    /**
     * @param $response
     * @return array
     */
    private function processErrorResponse($response)
    {
        $processed = [
            'success' => false,
            'estimate' => [
                'error' => $this->filterErrorResponse($response['description'])
            ]
        ];
        
        return $processed;
    }
    
    /**
     * Edit error description
     * @param $errorDescription
     * @return string
     */
    private function filterErrorResponse($errorDescription)
    {
        if (strpos($errorDescription, 'zip code is invalid') !== false) { // The zip code is invalid.
            $errorDescription = __($errorDescription);
        } else {
            $errorDescription = '';
        }
        
        return $errorDescription;
    }
}