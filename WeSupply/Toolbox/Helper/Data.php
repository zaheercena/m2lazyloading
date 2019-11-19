<?php

/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace WeSupply\Toolbox\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Shipping\Model\Config;
use Magento\Catalog\Model\Session as CatalogSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Directory\Model\CountryFactory;
use Magento\Directory\Model\AllowedCountries;
use Magento\Framework\UrlInterface;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Framework\App\Request\Http;
use WeSupply\Toolbox\Model\OrderInfoBuilder;
use WeSupply\Toolbox\Api\WeSupplyApiInterface;
use WeSupply\Toolbox\Logger\Logger;

/**
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Data extends AbstractHelper
{
    /**
     * WeSupply protocol
     */
    const WESUPPLY_PROTOCOL = 'https';

    /**
     * WeSupply domain name
     */
    const WESUPPLY_DOMAIN = 'labs.wesupply.xyz';

    /**
     * WeSupply local domain name and port
     */
    const WESUPPLY_DOMAIN_LOCAL = 'wesupply.local';
    const WESUPPLY_DOMAIN_LOCAL_PORT = '3080';

    /**
     * Platform name
     */
    const WESUPPLY_PLATFORM_TYPE = 'embedded';

    /**
     * WeSupply tracking cms page url
     */
    const WESUPPLY_TRACKING_INFO_URI = 'wesupply/track/shipment';

    /**
     * Array of carrier codes that are excluded from being sent to wesupply validation
     */
    const EXCLUDED_CARRIERS = [
        'flatrate',
        'tablerate',
        'freeshipping'
    ];
    
    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var \WeSupply\Toolbox\Api\WeSupplyApiInterface
     */
    protected $weSupplyApi;

    /**
     * @var \Magento\Shipping\Model\Config
     */
    protected $shipConfig;

    /**
     * @var \Magento\Catalog\Model\Session
     */
    protected $catalogSession;

    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $customerSession;

    /**
     * @var \Magento\Directory\Model\CountryFactory
     */
    private $countryFactory;

    /**
     * @var UrlInterface
     */
    protected $_urlInterface;
    
    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @var RemoteAddress
     */
    private $remoteAddress;
    
    private $request;
    
    /**
     * @var AllowedCountries
     */
    private $allowedCountries;
    
    /**
     * @var Logger
     */
    protected $logger;
    
    /**
     * Data constructor.
     * @param SerializerInterface $serializer
     * @param WeSupplyApiInterface $weSupplyApi
     * @param Context $context
     * @param StoreManagerInterface $storeManager
     * @param Config $shipConfig
     * @param CatalogSession $catalogSession
     * @param CustomerSession $customerSession
     * @param CountryFactory $countryFactory
     * @param UrlInterface $urlInterface
     * @param RemoteAddress $remoteAddress
     * @param Http $request
     * @param AllowedCountries $allowedCountries
     * @param Logger $logger
     */
    public function __construct(
        SerializerInterface $serializer,
        WeSupplyApiInterface $weSupplyApi,
        Context $context,
        StoreManagerInterface $storeManager,
        Config $shipConfig,
        CatalogSession $catalogSession,
        CustomerSession $customerSession,
        CountryFactory $countryFactory,
        UrlInterface $urlInterface,
        RemoteAddress $remoteAddress,
        Http $request,
        AllowedCountries $allowedCountries,
        Logger $logger
    ) {
        parent::__construct($context);
    
        $this->storeManager = $storeManager;
        $this->serializer = $serializer;
        $this->weSupplyApi = $weSupplyApi;
        $this->shipConfig = $shipConfig;
        $this->catalogSession = $catalogSession;
        $this->customerSession = $customerSession;
        $this->allowedCountries = $allowedCountries;
        $this->countryFactory = $countryFactory;
        $this->_urlInterface = $urlInterface;
        $this->remoteAddress = $remoteAddress;
        $this->request = $request;
        $this->logger = $logger;
     }

    /**
     * @return mixed
     */
    public function getWeSupplyEnabled()
    {
        return $this->scopeConfig->getValue('wesupply_api/integration/wesupply_enabled', ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return string
     */
    public function getGuid()
    {
        return $this->scopeConfig->getValue('wesupply_api/step_2/access_key', ScopeInterface::SCOPE_STORE);
    }
    
    /**
     * @return bool|string
     */
    public function getApiEndpoint()
    {
        try {
            return $this->getBaseUrlByScopeConfigView($this->getScopeConfigView()) . 'wesupply';
        } catch (NoSuchEntityException $e) {
            $this->logger->addError($e->getMessage());
        } catch (LocalizedException $e) {
            $this->logger->addError($e->getMessage());
        }
    
        return false;
    }
    
    /**
     * @return bool|string
     */
    public function getClientName()
    {
        try {
            return $this->getWeSupplySubDomain($this->getScopeConfigView()) ?? null;
        } catch (NoSuchEntityException $e) {
            $this->logger->addError($e->getMessage());
        } catch (LocalizedException $e) {
            $this->logger->addError($e->getMessage());
        }
        
        return false;
    }

    /**
     * @return int
     */
    public function getBatchSize()
    {
        //return $this->scopeConfig->getValue('wesupply_api/massupdate/batch_size', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        return 0;
    }

    /**
     * @return string
     */
    public function getProtocol()
    {
        return self::WESUPPLY_PROTOCOL;
    }

    /**
     * @return string
     */
    public function getPlatform()
    {
        return self::WESUPPLY_PLATFORM_TYPE;
    }

    /**
     * @return mixed
     */
    public function getWeSupplyDomain()
    {
        if ($this->isStagingMode()) {
            return self::WESUPPLY_DOMAIN . '/stage';
        }

        if ( // detect local environment
            $this->remoteAddress->getRemoteAddress() == '127.0.0.1' ||
            (isset($_SERVER['PHP_SELF']) && strpos($_SERVER['PHP_SELF'], '.local') !== false)
        ) {
            return self::WESUPPLY_DOMAIN_LOCAL . ':' . self::WESUPPLY_DOMAIN_LOCAL_PORT;
        }

        return self::WESUPPLY_DOMAIN;
    }
    
    /**
     * @param array $scopeConfig
     * @return mixed
     */
    public function getWeSupplySubDomain(
        $scopeConfig = [
            'scope_type' => ScopeInterface::SCOPE_STORE,
            'scope_code' => null
        ]
    )
    {
        return $this->scopeConfig->getValue(
            'wesupply_api/step_2/wesupply_subdomain',
            $scopeConfig['scope_type'],
            $scopeConfig['scope_code']
        );
    }

    /**
     * @return mixed
     */
    public function getEnabledNotification()
    {
        return $this->scopeConfig->getValue('wesupply_api/step_4/checkout_page_notification', ScopeInterface::SCOPE_STORE);
    }


    /**
     * @return mixed
     */
    public function getNotificationDesign()
    {
        return $this->scopeConfig->getValue('wesupply_api/step_4/design_notification', ScopeInterface::SCOPE_STORE);
    }


    /**
     * @return mixed
     */
    public function getNotificationAlignment()
    {
        return $this->scopeConfig->getValue('wesupply_api/step_4/design_notification_alingment', ScopeInterface::SCOPE_STORE);
    }


    /**
     * @return mixed
     */
    public function getNotificationBoxType()
    {
        return $this->scopeConfig->getValue('wesupply_api/step_4/notification_type', ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getEnableWeSupplyOrderView()
    {
        return $this->scopeConfig->getValue('wesupply_api/step_3/wesupply_order_view_enabled', ScopeInterface::SCOPE_STORE);
    }


    /**
     * @return mixed
     */
    public function getWeSupplyApiClientId()
    {
        return $this->scopeConfig->getValue('wesupply_api/step_1/wesupply_client_id', ScopeInterface::SCOPE_STORE);
    }


    /**
     * @return mixed
     */
    public function getWeSupplyApiClientSecret()
    {
        return $this->scopeConfig->getValue('wesupply_api/step_1/wesupply_client_secret', ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getWeSupplyOrderViewEnabled()
    {
        return $this->scopeConfig->getValue('wesupply_api/step_3/wesupply_order_view_enabled', ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getDeliveryEstimationsHeaderLinkEnabled()
    {
        return $this->scopeConfig->getValue('wesupply_api/step_3/enable_delivery_estimations_header_link', ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getDeliveryEstimationsEnabled()
    {
        return $this->scopeConfig->getValue('wesupply_api/step_5/enable_delivery_estimations', ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getDeliveryEstimationsRange()
    {
        return $this->scopeConfig->getValue('wesupply_api/step_5/estimation_range', ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getDeliveryEstimationsFormat()
    {
        return $this->scopeConfig->getValue('wesupply_api/step_5/estimation_format', ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getDeliveryEstimationsOrderWithin()
    {
        return $this->scopeConfig->getValue('wesupply_api/step_5/estimation_order_within', ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getDisplySpinner()
    {
        return $this->scopeConfig->getValue('wesupply_api/step_5/estimation_display_spinner', ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return array|bool
     */
    public function getEstimationsDefaultCarrierAndMethod()
    {
        $defaultCarrier = $this->scopeConfig->getValue('wesupply_api/step_5/estimation_default_carrier', ScopeInterface::SCOPE_STORE);
        if($defaultCarrier == '0'){
            return FALSE;
        }

        try {
            $searchedMethod = strtolower($defaultCarrier);
            $defaultMethod = $this->scopeConfig->getValue('wesupply_api/step_5/estimation_carrier_methods_' . $searchedMethod, ScopeInterface::SCOPE_STORE);


            return ['carrier' => $defaultCarrier , 'method'=> $defaultMethod];
        }catch (\Exception $e)
        {
            return FALSE;
        }
    }

    /**
     * @return mixed
     */
    public function orderViewModalEnabled()
    {
        return $this->scopeConfig->getValue('wesupply_api/step_3/wesupply_order_view_iframe', ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function trackingInfoIframeEnabled()
    {
        return $this->scopeConfig->getValue('wesupply_api/step_3/wesupply_tracking_info_iframe', ScopeInterface::SCOPE_STORE);
    }
    
    /**
     * @param $orders
     * @return string
     */
    public function externalOrderIdString($orders)
    {
        $arrayOrders = $orders->toArray();

        $externalOrderIdString = implode(',', array_map(function($singleOrderArray) {
            return $singleOrderArray['increment_id'];
        }, $arrayOrders['items']));

        return $externalOrderIdString;
    }

    /**
     * @param $orders
     * @return string
     */
    public function internalOrderIdString($orders)
    {
        $arrayOrders = $orders->toArray();

        $externalOrderIdString = implode(',', array_map(function($singleOrderArray) {
            return OrderInfoBuilder::PREFIX.$singleOrderArray['entity_id'];
        }, $arrayOrders['items']));

        return $externalOrderIdString;
    }

    /**
     * maps the Wesupply Api Response containing links to each order, to an internal array
     */
    public function getGenerateOrderMap($orders)
    {
        $orderIds = $this->externalOrderIdString($orders);
        try{
            $this->weSupplyApi->setProtocol($this->getProtocol());
            $apiPath = $this->getWeSupplySubDomain().'.'.$this->getWeSupplyDomain().'/api/';
            $this->weSupplyApi->setApiPath($apiPath);
            $this->weSupplyApi->setApiClientId($this->getWeSupplyApiClientId());
            $this->weSupplyApi->setApiClientSecret($this->getWeSupplyApiClientSecret());

            $result = $this->weSupplyApi->weSupplyInterogation($orderIds);
        }catch(\Exception $e){
            $this->logger->error("Error on WeSupply getGenerateOrderMap: " . $e->getMessage());
        }

        return $result;
    }

    /**
     * @param $string
     * @return float|int
     */
    public function strbits($string)
    {
        return (strlen($string)*8);
    }

    /**
     * @param $bytes
     * @return string
     */
    public function formatSizeUnits($bytes)
    {

        /**
         * transforming bytes in MB
         */
        if ($bytes >= 1048576)
        {
            $bytes = number_format($bytes / 1048576, 2);
        }
        else
        {
            return 0;
        }


        return $bytes;
    }

    /**
     * gets an array of al available shipping methods mapped to wesupply naming conventions
     * @return array
     */
    public function getMappedShippingMethods(){

        try {
            $activeCarriers = $this->shipConfig->getActiveCarriers();
            $methods = array();
            foreach ($activeCarriers as $carrierCode => $carrierModel) {

                if(in_array($carrierCode, self::EXCLUDED_CARRIERS)){
                    continue;
                }

                if(isset(WeSupplyMappings::MAPPED_CARRIER_CODES[$carrierCode])){
                    $carrierCode = WeSupplyMappings::MAPPED_CARRIER_CODES[$carrierCode];
                    $methods[] = $carrierCode;
                }
            }

            return $methods;

        }catch(\Exception $e){
            $this->logger->error("Error on WeSupply getMappedShippingMethods: " . $e->getMessage());
            return [];
        }
    }

    /**
     * returns mapped ups xml carrier code value
     * @param $magentoUpsCarrierCode
     * @return string
     */
    public function getMappedUPSXmlMappings($magentoUpsCarrierCode)
    {
        if(isset(WeSupplyMappings::UPS_XML_MAPPINGS[$magentoUpsCarrierCode])){
            return WeSupplyMappings::UPS_XML_MAPPINGS[$magentoUpsCarrierCode];
        }

        return '';
    }

    /**
     * @param $countryCode
     * @return string
     */
    public function getCountryname($countryCode)
    {
        try {
            $country = $this->countryFactory->create()->loadByCode($countryCode);
            return $country->getName();
        }catch(\Exception $e)
        {
            return '';
        }
    }

    /**
     * reverts back wesupply quotes to magento format
     * @param $quotes
     * @return array
     */
    public function revertWesupplyQuotesToMag($quotes)
    {
        $flipedCarrierMappings = array_flip(WeSupplyMappings::MAPPED_CARRIER_CODES);
        $mappedQuotes = [];
        foreach($quotes as $carrierKey => $values)
        {
            $magentoCarrierKey = $carrierKey;
            if(isset($flipedCarrierMappings[$carrierKey])){
                $magentoCarrierKey = $flipedCarrierMappings[$carrierKey];
            }
            $mappedQuotes[$magentoCarrierKey] = $values;
        }
        return $mappedQuotes;
    }

    /**
     * sets estimations data into session if session exists, otherwise creates a new session variable
     * @param $estimations
     */
    public function setEstimationsData($estimations)
    {
        $sessionEstimationsData = $this->catalogSession->getEstimationsData();
        /** existing session variable update */
        if ($sessionEstimationsData) {
            $sessionEstimationsArr = $this->serializer->unserialize($sessionEstimationsData);
            if(isset($estimations['zip'])){
                $sessionEstimationsArr[$estimations['zip']] = $estimations;
                $sessionEstimationsArr['default'] = $estimations['zip'];
                $this->catalogSession->setEstimationsData($this->serializer->serialize($sessionEstimationsArr));
            }
          return;
        }

        /**  new session creation */
        if(isset($estimations['zip'])){
            $sessionEstimationsArr[$estimations['zip']] = $estimations;
            $sessionEstimationsArr['default'] = $estimations['zip'];
            $sessionEstimationsArr['created_at'] = time();
            $this->catalogSession->setEstimationsData($this->serializer->serialize($sessionEstimationsArr));
        }
        return;
    }

    /**
     * Generates all printable options for my account order view
     * @param $order
     * @return array
     */
    public function generateAllPrintableOptionsForOrder($order)
    {
        $options = [];
        $options[] = [
            'label' => __('Print...'),
            'url' => '#'
        ];

        if($order->hasInvoices()){
            $options[] = ['label' => 'All Invoices', 'url' => $this->getPrintAllInvoicesUrl($order)];
        }

        if($order->hasShipments()){
            $options[] = ['label' => 'All Shipments', 'url' => $this->getPrintAllShipmentsUrl($order)];
        }

        if($order->hasCreditmemos()){
            $options[] = ['label' => 'All Refunds', 'url' => $this->getPrintAllCreditMemoUrl($order)];
        }

        return $options;
    }

    /**
     * @param object $order
     * @return string
     */
    public function getPrintAllInvoicesUrl($order)
    {
        return $this->_getUrl('sales/order/printInvoice', ['order_id' => $order->getId()]);
    }

    /**
     * @param $order
     * @return string
     */
    public function getPrintAllShipmentsUrl($order)
    {
        return $this->_getUrl('sales/order/printShipment', ['order_id' => $order->getId()]);
    }

    /**
     * @param $order
     * @return string
     */
    public function getPrintAllCreditMemoUrl($order)
    {
        return $this->_getUrl('sales/order/printCreditmemo', ['order_id' => $order->getId()]);
    }

    /**
     * @return string
     */
    public function getWesupplyFullDomain()
    {
        return $this->getProtocol() . '://' .  $this->getWeSupplySubDomain() . '.' . $this->getWeSupplyDomain() . '/';
    }

    /**
     * @param array $needle
     * @param array $haystack
     * @param string $default
     * @return string
     */
    public function recursivelyGetArrayData($needle, $haystack, $default = '')
    {
        $result = $default;
        foreach ($needle as $key) {
            if (array_key_exists($key, $haystack)) {
                $result = $haystack[$key];
                if (is_array($result)) {
                    unset($needle[0]);
                    $remaining = array_values($needle);
                    return $this->recursivelyGetArrayData($remaining, $result);
                }
            }
        }

        return $result;
    }

    /**
     * @return string
     */
    public function getTrackingInfoUri()
    {
        return self::WESUPPLY_TRACKING_INFO_URI;
    }

    /**
     * @return string
     */
    public function getStoreLocatorIdentifier()
    {
        return $this->scopeConfig->getValue('wesupply_api/step_6/store_locator_cms', ScopeInterface::SCOPE_STORE);
    }
    
    /**
     * @return string
     */
    public function getStoreDetailsIdentifier()
    {
        return $this->scopeConfig->getValue('wesupply_api/step_6/store_details_cms', ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return string
     */
    public function getTrackingInfoPageUrl()
    {
        return $this->_urlInterface->getBaseUrl() . $this->getTrackingInfoUri() . '/';
    }

    /**
     * @return string
     */
    public function getStoreLocatorPageUrl()
    {
        return $this->_urlInterface->getBaseUrl() . $this->getStoreLocatorUri() . '/';
    }

    /**
     * @return bool
     */
    private function isStagingMode()
    {
        if (strpos($this->getWeSupplySubDomain(), 'staging') !== false) {
            return true;
        }

        return false;
    }
    
    /**
     * @return array
     */
    public function getAllowedCountries()
    {
        return $this->allowedCountries->getAllowedCountries(ScopeInterface::SCOPE_WEBSITE);
    }
    
    /**
     * @return array|mixed
     */
    public function getShippingAllowedCountries()
    {
        if ($allowedCountries = $this->customerSession->getAllowedCountries()) {
            return $allowedCountries ?? [];
        }
        
        $this->weSupplyApi->setProtocol($this->getProtocol());
        $apiPath = $this->getWeSupplySubDomain() . '.' . $this->getWeSupplyDomain() . '/api/';
        $this->weSupplyApi->setApiPath($apiPath);
        $this->weSupplyApi->setApiClientId($this->getWeSupplyApiClientId());
        $this->weSupplyApi->setApiClientSecret($this->getWeSupplyApiClientSecret());
    
        $allowedCountries = $this->weSupplyApi->getWeSupplyAllowedCountries();
        
        // memorize allowed countries
        if ($allowedCountries) {
            sort($allowedCountries);
            $this->customerSession->setAllowedCountries($allowedCountries);
        }
        
        return $allowedCountries ?? [];
    }
    
    /**
     * @param $scopeConfig
     * @return mixed
     */
    private function getBaseUrlByScopeConfigView($scopeConfig)
    {
        $isSecure = $this->scopeConfig->getValue('web/secure/use_in_frontend', $scopeConfig['scope_type'], $scopeConfig['scope_code']);
        $path = $isSecure ? 'web/secure/base_url' : 'web/unsecure/base_url';
        
        return $this->scopeConfig->getValue($path,$scopeConfig['scope_type'],$scopeConfig['scope_code']);
    }
    
    /**
     * @return array
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function getScopeConfigView()
    {
        $scope = ScopeInterface::SCOPE_STORE;
        $id = (int) $this->request->getParam('store', 0);
        $code = $this->storeManager->getStore($id)->getCode();
    
        if ($id === 0) {
            $id = (int) $this->request->getParam('website', 0);
            if ($id) {
                $scope = ScopeInterface::SCOPE_WEBSITE;
                $code = $this->storeManager->getWebsite($id)->getCode();
            }
        }
        
        return [
            'scope_type' => $scope,
            'scope_code' => $code
        ];
    }
}