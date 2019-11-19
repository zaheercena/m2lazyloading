<?php

/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace WeSupply\Toolbox\Plugin\Carrier;

use Magento\Framework\Serialize\SerializerInterface;
use Magento\Quote\Model\Cart\ShippingMethodConverter;
use Magento\Quote\Api\Data\ShippingMethodInterface;

class DeliveryDate
{


    /**
     * @var \Magento\Quote\Api\Data\ShippingMethodExtensionFactory
     */
    protected $extensionFactory;

    /**
     * @var \WeSupply\Toolbox\Helper\Data
     */
    protected $helper;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;

    /**
     * @var \WeSupply\Toolbox\Model\WeSupplyApi
     */
    protected $weSupplyApi;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var \Magento\Catalog\Model\Session
     */
    protected $catalogSession;

    /**
     * @var string
     */
    protected $carrierCode;

    /**
     * @var string
     */
    protected $serviceCode;

    /**
     * @var mixed|string
     */
    protected $estimationsFormat;

    /**
     * @var int|mixed
     */
    protected $estimationsRange;

    /**
     * @var
     */
    protected $postCode;

    /**
     * @var
     */
    protected $countryCode;

    /**
     * @var
     */
    protected $price;

    /**
     * @var
     */
    protected $currency;

    /**
     * @var
     */
    protected $storeId;

    /**
     * @var
     */
    protected $ipAddress;

    /**
     * @var
     */
    protected $sessionEstimationsArr;

    /**
     * @var
     */
    protected $quote;


    /**
     * @var array containing estimations
     */
    protected $estimationsArr;
    
    /**
     * @var SerializerInterface
     */
    private $serializer;
    
    
    /**
     * DeliveryDate constructor.
     * @param SerializerInterface $serializer
     * @param \Magento\Quote\Api\Data\ShippingMethodExtensionFactory $extensionFactory
     * @param \WeSupply\Toolbox\Helper\Data $helper
     * @param \Magento\Checkout\Model\Session $session
     * @param \WeSupply\Toolbox\Api\WeSupplyApiInterface $weSupplyApi
     * @param \Magento\Catalog\Model\Session $catalogSession
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(
        SerializerInterface $serializer,
        \Magento\Quote\Api\Data\ShippingMethodExtensionFactory $extensionFactory,
        \WeSupply\Toolbox\Helper\Data $helper,
        \Magento\Checkout\Model\Session $session,
        \WeSupply\Toolbox\Api\WeSupplyApiInterface $weSupplyApi,
        \Magento\Catalog\Model\Session $catalogSession,
        \Psr\Log\LoggerInterface $logger
    )
    {
        $this->serializer = $serializer;
        $this->extensionFactory = $extensionFactory;
        $this->helper = $helper;
        $this->checkoutSession = $session;
        $this->weSupplyApi = $weSupplyApi;
        $this->catalogSession = $catalogSession;
        $this->logger = $logger;
        $this->estimationsFormat = $this->helper->getDeliveryEstimationsFormat() ? $this->helper->getDeliveryEstimationsFormat() : 'd F';
        $this->estimationsRange = $this->helper->getDeliveryEstimationsRange() ? $this->helper->getDeliveryEstimationsRange() : 0;
    }

    /**
     * @param ShippingMethodConverter $subject
     * @param ShippingMethodInterface $result
     * @return ShippingMethodInterface
     */
    public function afterModelToDataObject(ShippingMethodConverter $subject, ShippingMethodInterface $result)
    {
        if ($this->helper->getWeSupplyEnabled() && $this->helper->getDeliveryEstimationsEnabled()) {

            // $extensibleAttribute = $result->getExtensionAttributes();

            $extensibleAttribute = ($result->getExtensionAttributes())
                ? $result->getExtensionAttributes()
                : $this->extensionFactory->create();

            $this->populateNeededParams();

            if ($this->quote && $this->postCode ) {

                    try {
                        $this->carrierCode = strtolower($result->getCarrierCode());
                        $this->serviceCode = strtolower($result->getMethodCode());

                        if(!is_array($this->estimationsArr)) {
                            $this->estimationsArr = $this->getSavedQuotes();
                        }

                        if(FALSE === $this->estimationsArr){
                            $this->estimationsArr = $this->getNewQuotes();
                        }


                        if (isset($this->estimationsArr[$this->carrierCode])) {
                            foreach ($this->estimationsArr[$this->carrierCode] as $estServiceCode => $estTmstp) {

                                // for ups, magento maps with own codes the shipping method
                                $mappedEstimatedServiceCode = '';
                                if( $this->carrierCode == 'ups') {
                                    $mappedEstimatedServiceCode = $this->helper->getMappedUPSXmlMappings($this->serviceCode);
                                }

                                if (strtolower($estServiceCode) == $this->serviceCode ||
                                    strtolower($mappedEstimatedServiceCode) == strtolower($estServiceCode)
                                    ) {
                                    if (isset($estTmstp)) {

                                        $estimatedTimestamp = $estTmstp;
                                        $estimatedDelivery = date($this->estimationsFormat, $estimatedTimestamp);
                                        if($this->estimationsRange > 0){
                                            $estimatedRange = date($this->estimationsFormat,strtotime('+'. $this->estimationsRange . ' days', $estimatedTimestamp));
                                            $estimatedDelivery .= ' - '.$estimatedRange;
                                        }

                                        $extensibleAttribute->setDeliveryTime($estimatedDelivery);
                                        $result->setExtensionAttributes($extensibleAttribute);
                                    }

                                    return $result;
                                }
                            }
                        }
                        $extensibleAttribute->setDeliveryTime('Not Available');
                        $result->setExtensionAttributes($extensibleAttribute);
                        return $result;

                    } catch (\Exception $ex) {
                        $this->logger->error("Error on WeSupply Shipping Rates Estimations: " . $ex->getMessage());
                        return $result;
                    }


            }

        }

        return $result;
    }


    /**
     * populates needed params
     */
    protected function populateNeededParams()
    {
        if(!$this->quote) {
            $this->quote = $this->checkoutSession->getQuote();
        }

        if(!$this->postCode) {
            $this->postCode = $this->quote->getShippingAddress()->getPostcode() ?? '';
        }

        if(!$this->countryCode) {
            $this->countryCode = $this->quote->getShippingAddress()->getCountryId() ?? '';
        }

        if(!$this->price) {
            $this->price = $this->quote->getGrandTotal();
        }

        if(!$this->storeId) {
            $this->storeId = $this->quote->getStoreId();
        }

        if(!$this->currency) {
            $this->currency = $this->quote->getCurrency()->getData('quote_currency_code');
        }

        if(!$this->ipAddress) {
            $this->ipAddress = $this->quote->getRemoteIp();
        }
    }

    /**
     * returns the previous wesupply shipper quotes from session, if there are any
     * @return bool/array
     */
    protected function getSavedQuotes()
    {
        if(!is_array($this->sessionEstimationsArr)) {
            $sessionEstimationsData = $this->catalogSession->getEstimationsData();
            if ($sessionEstimationsData) {
                $this->sessionEstimationsArr =  $this->serializer->unserialize($sessionEstimationsData);
            }
        }

        if (isset($this->sessionEstimationsArr[$this->postCode]['shipper'])) {
            if (!empty($this->sessionEstimationsArr[$this->postCode]['shipper'])) {
                return $this->sessionEstimationsArr[$this->postCode]['shipper'];
            }
        }

        return FALSE;

    }

    /**
     * creates new api call to wesupply shipper quotes; sets response to session
     * @return array
     */
    protected function getNewQuotes()
    {

        if(FALSE === $this->validateCallParams()){
            return [];
        }

        $apiPath = $this->helper->getWeSupplySubDomain() . '.' . $this->helper->getWeSupplyDomain() . '/api/';
        $this->weSupplyApi->setProtocol($this->helper->getProtocol());
        $this->weSupplyApi->setApiPath($apiPath);
        $this->weSupplyApi->setApiClientId($this->helper->getWeSupplyApiClientId());
        $this->weSupplyApi->setApiClientSecret($this->helper->getWeSupplyApiClientSecret());
        $carrierCodes = $this->helper->getMappedShippingMethods();

        if(count($carrierCodes) === 0){
            return [];
        }

        $newShipperResponse = $this->weSupplyApi->getShipperQuotes($this->ipAddress,$this->storeId, $this->postCode, $this->countryCode, $this->price, $this->currency, $carrierCodes);
        if (is_array($newShipperResponse) && isset($newShipperResponse['shipper'])) {

            $newShipperResponse['shipper'] = $this->helper->revertWesupplyQuotesToMag($newShipperResponse['shipper']);
            $this->helper->setEstimationsData($newShipperResponse);

            return  $newShipperResponse['shipper'];
        }

        return [];
    }


    /**
     * validates mandatory api call parameters
     * @return bool
     */
    protected function validateCallParams()
    {
        if(empty($this->ipAddress)){
            return FALSE;
        }

        if(empty($this->postCode)){
            return FALSE;
        }

        if(empty($this->countryCode)){
            return FALSE;
        }

        if(empty($this->storeId)){
            return FALSE;
        }

        if(empty($this->price)){
            return FALSE;
        }

        if(empty($this->currency)){
            return FALSE;
        }
        return TRUE;
    }


}