<?php

/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
    
namespace WeSupply\Toolbox\Plugin\Carrier;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Cart\ShippingMethodConverter;
use Magento\Quote\Api\Data\ShippingMethodInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Quote\Api\Data\ShippingMethodExtensionFactory;
use Magento\Framework\Serialize\Serializer\Json;
use WeSupply\Toolbox\Block\Estimations\Delivery as EstimatesDelivery;
use WeSupply\Toolbox\Helper\Estimates as EstimatesHelper;
use WeSupply\Toolbox\Helper\Data as WeSupplyHelper;

class AppendEstimations
{
    /**
     * @var CheckoutSession
     */
    protected $checkoutSession;

    /**
     * @var ShippingMethodExtensionFactory
     */
    protected $extensionFactory;
    
    /**
     * @var CheckoutSession::getQuote()
     */
    protected $quote;
    
    /**
     * @var Json
     */
    protected $json;
    
    /**
     * @var EstimatesDelivery
     */
    protected $estimatesDelivery;
    
    /**
     * @var EstimatesHelper
     */
    private $estimatesHelper;
    
    /**
     * @var WeSupplyHelper
     */
    private $helper;
    
    /**
     * @var $estimations
     */
    private $estimations;
    
    /**
     * @var $carrierCode
     */
    private $carrierCode;
    
    /**
     * @var $methodCode
     */
    private $methodCode;
    
    
    /**
     * AppendEstimations constructor.
     * @param CheckoutSession $checkoutSession
     * @param ShippingMethodExtensionFactory $extensionFactory
     * @param Json $json
     * @param EstimatesDelivery $estimatesDelivery
     * @param EstimatesHelper $estimatesHelper
     * @param WeSupplyHelper $helper
     */
    public function __construct
    (
        CheckoutSession $checkoutSession,
        ShippingMethodExtensionFactory $extensionFactory,
        Json $json,
        EstimatesDelivery $estimatesDelivery,
        EstimatesHelper $estimatesHelper,
        WeSupplyHelper $helper
    )
    {
        $this->checkoutSession = $checkoutSession;
        $this->extensionFactory = $extensionFactory;
        $this->json = $json;
        $this->estimatesDelivery = $estimatesDelivery;
        $this->estimatesHelper = $estimatesHelper;
        $this->helper = $helper;
    }
    
    /**
     * @param ShippingMethodConverter $subject
     * @param ShippingMethodInterface $result
     * @return ShippingMethodInterface
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function afterModelToDataObject(
        ShippingMethodConverter $subject,
        ShippingMethodInterface $result
    )
    {
        if (!$this->helper->getWeSupplyEnabled() || !$this->helper->getDeliveryEstimationsEnabled()) {
            return $result;
        }
    
        $this->carrierCode = strtolower($result->getCarrierCode());
        $this->methodCode = $result->getMethodCode();
    
        $this->quote = $this->checkoutSession->getQuote();
        $this->estimations = $this->getEstimationQuotes();

        // try to get a new fresh estimations, just in case something has changed meanwhile
        if ($this->estimationsHasErrors() && $this->estimations['estimation_attempt'] == 'observer'){
            $this->getNewDeliveryEstimations();
        }

        if ($this->estimationsHasErrors()) {
            // has errors - do nothing and exit
            return $result;
        }

        // address has changed? -> get a new fresh estimations
        if (
            (
                $this->quote->getShippingAddress()->getPostcode() &&
                $this->quote->getShippingAddress()->getCountryId()
            ) &&
            (
                $this->quote->getShippingAddress()->getPostcode() != $this->estimations['location_id'] ||
                $this->quote->getShippingAddress()->getCountryId() != $this->estimations['location_country']
            )
        ) {
            $this->getNewDeliveryEstimations();
            if ($this->estimationsHasErrors()) {
                // has errors - do nothing and exit
                return $result;
            }
        }
    
        if (!$this->estimationExists()) {
            // estimation for the current shipping method is not set
            // do nothing and exit
            return $result;
        }
    
        $estimationsFormat = $this->helper->getDeliveryEstimationsFormat();
        $estimatedTimestamp = $this->getEstimationTimestamp();
        
        $this->addEstimationRange($estimatedTimestamp);
        
        if (is_array($estimatedTimestamp)) { // Estimation Display Mode = 'Range'
            $estimatedDelivery = date($estimationsFormat, $estimatedTimestamp[0]);
            if (date('y-m-d', $estimatedTimestamp[0]) != date('y-m-d', $estimatedTimestamp[1])) {
                $estimatedDelivery .= ' - ' . date($estimationsFormat, $estimatedTimestamp[1]);
            }
        } else {
            $estimatedDelivery = date($estimationsFormat, $estimatedTimestamp);
        }
    
        // $extensibleAttribute = $result->getExtensionAttributes();
        $extensibleAttribute = ($result->getExtensionAttributes())
            ? $result->getExtensionAttributes()
            : $this->extensionFactory->create();

        $extensibleAttribute->setDeliveryTime($estimatedDelivery);
        $result->setExtensionAttributes($extensibleAttribute);
    
        return $result;
    }

    /**
     * @return bool
     */
    private function estimationsHasErrors()
    {
        if (empty($this->estimations) || !isset($this->estimations['location_id']) || isset($this->estimations['error'])) {
            return true;
        }

        return false;
    }

    /**
     * Check if estimation exists for the current shipping method
     * @return bool
     */
    private function estimationExists()
    {
        return isset($this->estimations['estimates'][$this->carrierCode]['methods'][$this->methodCode]['estimated_delivery_date']);
    }
    
    /**
     * @return array|integer
     */
    private function getEstimationTimestamp()
    {
        $estimations = $this->extractEstimationTimestamps();
        $displayMode = $this->estimatesHelper->getEstimationDisplayMode();
        switch ($displayMode) {
            case 'earliest':
                return reset($estimations);
                break;
            case 'latest':
                return end($estimations);
                break;
            default:
                return [reset($estimations), end($estimations)];
                break;
        }
    }
    
    /**
     * @return array
     */
    private function extractEstimationTimestamps()
    {
        $timestamps = [];
        $estimations = $this->estimations['estimates'][$this->carrierCode]['methods'][$this->methodCode]['estimated_delivery_date'];
        foreach ($estimations as $timestamp) {
            $timestamps[$timestamp] = $timestamp;
        }
    
        sort($timestamps);
    
        return $timestamps;
    }
    
    /**
     * @param $estimatedTimestamp
     * @return array
     */
    private function addEstimationRange(&$estimatedTimestamp)
    {
        $estimated = $estimatedTimestamp;
        
        if (!$this->helper->getDeliveryEstimationsRange()) {
            return $estimatedTimestamp;
        }
    
        $estimatedTimestamp = [];
        $estimationsRange = $this->helper->getDeliveryEstimationsRange();
        if (is_array($estimated)) {
            $estimatedTimestamp[0] = $estimated[0];
            $estimatedTimestamp[1] = strtotime('+'. $estimationsRange . ' days', $estimated[1]);
            
            return $estimatedTimestamp;
        }
    
        $estimatedTimestamp[0] = $estimated;
        $estimatedTimestamp[1] = strtotime('+'. $estimationsRange . ' days', $estimated);
        
        return $estimatedTimestamp;
    }
    
    /**
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function getNewDeliveryEstimations()
    {
        // clear current estimation
        $this->unsetEstimationQuotes();
    
        $items = $this->quote->getAllItems();
        $response = $this->estimatesDelivery->getEstimations($items);
        if (!$response)  $response = [];

        $response['estimation_attempt'] = 'append';
        $this->estimatesDelivery->removeEmptyEstimates($response);
    
        // set new estimations
        $this->setEstimationQuotes($response);
    }

    /**
     * @return array
     */
    private function getEstimationQuotes()
    {
        if ($this->checkoutSession->getEstimationQuotes()) {
            return $this->json->unserialize(
                $this->checkoutSession->getEstimationQuotes()
            );
        }

        return [];
    }
    
    /**
     * @param $response
     */
    private function setEstimationQuotes($response)
    {
        $this->estimations = $response;
        $this->checkoutSession->setEstimationQuotes(
            $this->json->serialize($response)
        );
    }
    
    /**
     * void
     */
    private function unsetEstimationQuotes()
    {
        $this->estimations = [];
        $this->checkoutSession->unsEstimationQuotes();
    }
}