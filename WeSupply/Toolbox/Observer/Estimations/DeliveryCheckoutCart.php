<?php

/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace WeSupply\Toolbox\Observer\Estimations;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Checkout\Model\Session as CheckoutSession;
use WeSupply\Toolbox\Block\Estimations\Delivery as EstimatesDelivery;
use WeSupply\Toolbox\Helper\Data as WeSupplyHelper;
use WeSupply\Toolbox\Logger\Logger;

/**
 * Class DeliveryCheckoutCart
 * @package WeSupply\Toolbox\Observer\Estimations
 */

class DeliveryCheckoutCart implements ObserverInterface
{
    /**
     * @var CheckoutSession
     */
    protected $checkoutSession;
    
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
     * @var Logger
     */
    protected $logger;

    /**
     * @var WeSupplyHelper
     */
    private $helper;
    
    /**
     * DeliveryCheckoutCart constructor.
     * @param Json $json
     * @param CheckoutSession $checkoutSession
     * @param EstimatesDelivery $estimatesDelivery
     * @param WeSupplyHelper $helper
     * @param Logger $logger
     */
    public function __construct(
        CheckoutSession $checkoutSession,
        Json $json,
        EstimatesDelivery $estimatesDelivery,
        WeSupplyHelper $helper,
        Logger $logger
    )
    {
        $this->checkoutSession = $checkoutSession;
        $this->json = $json;
        $this->estimatesDelivery = $estimatesDelivery;
        $this->helper = $helper;
        $this->logger = $logger;
    }
    
    /**
     * @param Observer $observer
     * @return $this|void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function execute(Observer $observer)
    {
        if (!$this->helper->getWeSupplyEnabled() || !$this->helper->getDeliveryEstimationsEnabled()) {
            return $this;
        }

        $this->quote = $this->checkoutSession->getQuote();
        $items = $this->quote->getAllItems();

        if (!$items) {
            $this->resetEstimationQuotes();
            $this->logger->error('Odd situation... no items in cart!');
            $this->setEstimationQuotes($this->json->serialize(['estimation_attempt' => 'observer']));

            return $this;
        }
    
        /**
         * TODO don't need to remove entire estimation quotes
         * there are still work to do here
         */
        $this->resetEstimationQuotes();
        
        $response = $this->estimatesDelivery->getEstimations($items);
        if (!$response) {
            $this->logger->error('WeSupply returned an empty response.');
            $response = [];
        }

        $response['estimation_attempt'] = 'observer';

        if (isset($response['error'])) {
            $this->logger->error('Cannot get estimations due to an WeSupply error.');
            $this->setEstimationQuotes($this->json->serialize($response));
            
            return $this;
        }
        
        $this->estimatesDelivery->removeEmptyEstimates($response);
        $this->setEstimationQuotes($this->json->serialize($response));
        
        return $this;
    }
    
    /**
     * @param $jsonResponse
     */
    private function setEstimationQuotes($jsonResponse)
    {
        $this->checkoutSession->setEstimationQuotes($jsonResponse);
    }
    
    /**
     * Clear estimation quotes
     */
    private function resetEstimationQuotes()
    {
        $this->checkoutSession->unsEstimationQuotes();
    }
}