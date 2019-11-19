<?php

/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace WeSupply\Toolbox\Helper;

use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\Math\Random;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Customer\Model\Address;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Api\GroupRepositoryInterface;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Shipping\Model\Config as ShippingConfig;
use WeSupply\Toolbox\Logger\Logger;

/**
 * Class Estimates
 * @package WeSupply\Toolbox\Helper
 */

class Estimates extends AbstractHelper
{
    /**
     * @var Random
     */
    protected $mathRandom;
    
    /**
     * @var CustomerSession
     */
    protected $customerSession;
    
    /**
     * @var CheckoutSession
     */
    protected $checkoutSession;
    
    /**
     * @var GroupRepositoryInterface
     */
    protected $groupRepository;
    
    /**
     * @var CategoryRepositoryInterface
     */
    protected $categoryRepository;
    
    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;
    
    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;
    
    /**
     * @var ShippingConfig
     */
    protected $shipConfig;
    
    /**
     * @var Logger
     */
    protected $logger;
    
    /**
     * @var Http
     */
    protected $request;
    
    /**
     * @var
     */
    protected $params;
    
    /**
     * @var $product
     * current product
     */
    protected $product;
    
    /**
     * @var $selectedProduct
     * simple associated product
     * selected from the options of configurable product
     */
    protected $selectedProduct;
    
    /**
     * Estimates constructor.
     * @param Context $context
     * @param Http $request
     * @param Random $mathRandom
     * @param CustomerSession $customerSession
     * @param CheckoutSession $checkoutSession
     * @param GroupRepositoryInterface $groupRepository
     * @param CategoryRepositoryInterface $categoryRepository
     * @param StoreManagerInterface $storeManager
     * @param ScopeConfigInterface $scopeConfig
     * @param ShippingConfig $shipConfig
     * @param Logger $logger
     */
    public function __construct(
        Context $context,
        Http $request,
        Random $mathRandom,
        CustomerSession $customerSession,
        CheckoutSession $checkoutSession,
        GroupRepositoryInterface $groupRepository,
        CategoryRepositoryInterface $categoryRepository,
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig,
        ShippingConfig $shipConfig,
        Logger $logger
    )
    {
        parent::__construct($context);
    
        $this->request = $request;
        $this->mathRandom = $mathRandom;
        $this->customerSession = $customerSession;
        $this->checkoutSession = $checkoutSession;
        $this->groupRepository = $groupRepository;
        $this->categoryRepository = $categoryRepository;
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
        $this->shipConfig = $shipConfig;
        $this->logger = $logger;
    }
    
    /**
     * @return array
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function buildApiRequestCommonParams()
    {
        $this->params = $this->request->getParams();
        
        $locationType = 'ip_address';
        if ($this->checkAddressParams() || $this->getDefaultAddress()) {
            $locationType = 'zip_code';
        }
        
        return [
            'request_id' => $this->generateUniqueId(),
            'location_type' => $locationType,
            'location_id' => $locationType == 'zip_code' ? [
                'zip_code' => $this->checkAddressParams() ? $this->getPostcode() : $this->getDefaultAddress()->getPostcode(),
                'country_code' => $this->checkAddressParams() ? $this->getCountryCode() : $this->getDefaultAddress()->getCountryId()
            ] : $this->getIpAddress(),
            'user_type' => $this->customerSession->isLoggedIn() ? 'email' : 'session',
            'user_id' => $this->customerSession->isLoggedIn() ? $this->getCustomerEmail() : $this->customerSession->getSessionId(),
            'lists' => [
                'customer_group' => $this->getCustomerGroupName()
            ],
            'time' => [
                'timestamp' => time(),
                'timezone' => $this->scopeConfig->getValue('general/locale/timezone', ScopeInterface::SCOPE_STORE)
            ]
        ];
    }
    
    /**
     * Check if post code and country code were set
     * @return bool
     */
    private function checkAddressParams()
    {
        if (
            isset($this->params['postcode']) && isset($this->params['country_code']) &&
            !empty($this->params['postcode']) && !empty($this->params['country_code'])
        ) {
            return true;
        }
        
        return false;
    }
    
    private function getPostcode()
    {
        return $this->params['postcode'];
    }
    
    private function getCountryCode()
    {
        return $this->params['country_code'];
    }
    
    /**
     * @param $product
     * @param $configParent
     * @return array
     * @throws NoSuchEntityException
     */
    public function buildApiRequestProductParams($product, $configParent)
    {
        $this->product = $product;
        if ($configParent) {
            $this->product = $configParent;
            $this->selectedProduct = $product;
        }
        
        return [
            'id' => $this->getProductAttribute('entity_id'),
            'key' => $this->getProductAttribute('sku'),
            'name' => $this->getProductAttribute('name'),
            'url' => $this->getProductUrl(),
            'image_url' => $this->getProductImage(),
            'price' => $this->getFinalPrice(),
            'price_currency' => $this->storeManager->getStore()->getCurrentCurrency()->getCode(),
            'stock_status' => $this->getStockStatus(),
            'category_ids' => $this->getSanitizedCategoryIds(),
            'category_names' => $this->getCategoryNamesFromIds(),
            'attributes' => $this->collectAttributes(),
            'measurements' => $this->getMeasurements()
        ];
    }
    
    /**
     * @return string
     */
    private function generateUniqueId()
    {
        try {
            return $this->mathRandom->getUniqueHash();
        } catch (LocalizedException $e) {
            // log error and return empty string
            return '';
        }
    }
    
    /**
     * @return bool|Address|\Magento\Quote\Model\Quote\Address
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getDefaultAddress()
    {
        // first try to get shipping address from quote
        $quote = $this->checkoutSession->getQuote();
        if ($quote && $quote->getShippingAddress()->getPostcode()) {
            return $quote->getShippingAddress();
        }
        
        // than try to get default shipping address
        if ($this->customerSession->isLoggedIn()) {
            $customer = $this->customerSession->getCustomer();
            if ($defaultAddress = $customer->getDefaultShippingAddress()) {
                return $defaultAddress;
            }
        }
        
        return false;
    }
    
    /**
     * @return string
     */
    private function getCustomerEmail()
    {
        return $this->customerSession->getCustomer()->getEmail();
    }
    
    /**
     * Get real visitor IP behind CloudFlare network
     * @return mixed|null
     */
    private function getIpAddress()
    {
        if (isset($_SERVER["HTTP_CF_CONNECTING_IP"])) {
            $_SERVER['REMOTE_ADDR'] = $_SERVER["HTTP_CF_CONNECTING_IP"];
            $_SERVER['HTTP_CLIENT_IP'] = $_SERVER["HTTP_CF_CONNECTING_IP"];
        }
        
        $client  = $_SERVER['HTTP_CLIENT_IP'] ?? null;
        $forward = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null;
        $remote  = $_SERVER['REMOTE_ADDR'];
        
        if (filter_var($client, FILTER_VALIDATE_IP)) {
            $remoteAddress = $client;
        } elseif(filter_var($forward, FILTER_VALIDATE_IP)) {
            $remoteAddress = $forward;
        } else {
            $remoteAddress = $remote;
        }
        
        $ipArr = [
            '107.150.30.186',
            '134.201.250.155',
            '149.142.201.252'
        ];
        $randIndex = array_rand($ipArr);
        
        return $remoteAddress != '127.0.0.1' ? $remoteAddress : $ipArr[$randIndex];
    }
    
    /**
     * @return string
     */
    protected function getCustomerGroupName()
    {
        try {
            $group = $this->groupRepository
                ->getById($this->customerSession->getCustomerGroupId());
            return $group->getCode();
        } catch (NoSuchEntityException $e) {
            $this->logger->error('Customer Group error. ' . $e->getMessage());
            return '';
        } catch (LocalizedException $e) {
            $this->logger->error('Customer Group error. ' . $e->getMessage());
            return '';
        }
    }
    
    /**
     * @param $attrCode
     * @return string
     */
    protected function getProductAttribute($attrCode)
    {
        if ($this->simpleProductIsSet()) {
            if ($attrValue = $this->selectedProduct->getData($attrCode)) {
                return $attrValue;
            }
        }
    
        return $this->product->getData($attrCode) ?? '';
    }

    /**
     * @return array
     */
    protected function getSanitizedCategoryIds()
    {
        $ids = $this->getProductAttribute('category_ids');
        if (!is_array($ids)) {
            $ids = explode(',', $ids);
        }
        foreach ($ids as $key => $id) {
            if (is_null($id) || empty($id)) {
                $ids[$key] = 0;
                continue;
            }
        }

        return $ids;
    }
    
    /**
     * @return string
     */
    protected function getProductUrl()
    {
        if ($this->simpleProductIsSet()) {
            if (
                $this->selectedProduct->getVisibility() &&
                $this->selectedProduct->getVisibility() != 1 &&
                $this->selectedProduct->getProductUrl()
            ) {
                return $this->selectedProduct->getProductUrl();
            }
        }
        
        return $this->product->getProductUrl() ?? '';
    }
    
    /**
     * @return string
     * @throws NoSuchEntityException
     */
    protected function getProductImage()
    {
        $mediaPath = $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA ) . 'catalog/product';
        if ($this->simpleProductIsSet()) {
            if ($image = $this->selectedProduct->getImage()) {
                return  $mediaPath . $image;
            }
        }
        if ($image = $this->product->getImage()) {
            return  $mediaPath . $image;
        }
    
        return '';
    }
    
    /**
     * @return float
     */
    protected function getFinalPrice()
    {
        if ($this->simpleProductIsSet()) {
            if ($price = $this->selectedProduct->getFinalPrice()) {
                return $price;
            }
        }
        
        return $this->product->getFinalPrice() ?? 0.00;
    }
    
    /**
     * @return string
     */
    protected function getStockStatus()
    {
        $stockStatus = $this->product->getQuantityAndStockStatus();
        if ($this->simpleProductIsSet()) {
            $stockStatus = $this->selectedProduct->getQuantityAndStockStatus();
        }
        
        if (isset($stockStatus['is_in_stock']) && $stockStatus['is_in_stock']) {
            return 'in_stock';
        }
        
        return 'out_of_stock';
    }
    
    /**
     * @return array
     */
    protected function getCategoryNamesFromIds()
    {
        $ids = $this->getSanitizedCategoryIds();
        
        $categoryNames = [];
        foreach ($ids as $id) {
            try {
                $category = $this->categoryRepository->get($id, $this->storeManager->getStore()->getId());
                $categoryNames[] = $category->getName();
            } catch (NoSuchEntityException $e) {
                $categoryNames[] = 'Unknown Category';
                $this->logger->error('Category ID ' . $id . ' error. ' . $e->getMessage());
            }
        }
        
        return $categoryNames;
    }
    
    /**
     * @return array
     */
    protected function collectAttributes()
    {
        $filterableAttributes = $this->getDeliveryProductAttributes();
        if (!$filterableAttributes) {
            return [];
        }
        
        $productAttributes = $this->filterAndSetProductAttributes(explode(',', $filterableAttributes));
        
        return $productAttributes ?? [];
    }
    
    /**
     * @return mixed
     */
    public function getDeliveryProductAttributes()
    {
        return $this->scopeConfig->getValue('wesupply_api/step_5/estimation_product_attributes', ScopeInterface::SCOPE_STORE);
    }
    
    /**
     * @return mixed
     */
    public function getEstimationDisplayMode()
    {
        return $this->scopeConfig->getValue('wesupply_api/step_5/estimation_display', ScopeInterface::SCOPE_STORE);
    }
    
    /**
     * @param array $attributes
     * @return array
     */
    private function filterAndSetProductAttributes(array $attributes)
    {
        $productAttributes = $this->product->getAttributes();
        if ($this->simpleProductIsSet()) { // merge the two sets of attributes
            $productAttributes = array_merge($productAttributes, $this->selectedProduct->getAttributes());
        }
        
        foreach ($attributes as $key => $code) {
            if (!isset($productAttributes[$code])) { // remove attributes that are not assigned to product/s
                unset($attributes[$key]);
                continue;
            }
            if (
                (!$this->simpleProductIsSet() || !$this->selectedProduct->getData($code)) &&
                !$this->product->getData($code)
            )
            { // remove attributes with empty values
                unset($attributes[$key]);
                continue;
            }
            
            $attributeValue = $this->product->getData($code) ?? '';
            if ($this->simpleProductIsSet()) {
                if ($this->selectedProduct->getData($code)) {
                    $attributeValue = $this->selectedProduct->getData($code) ?? '';
                }
            }
            
            $attribute = $productAttributes[$code];
            if ($attribute->usesSource()) {
                $this->processAttributeValues($attributeValue, $attribute);
            }
            
            $attributes[$code] = $attributeValue;
            unset($attributes[$key]);
        }
        
        return $attributes;
    }
    
    /**
     * @param $attributeValue
     * @param $attribute
     */
    private function processAttributeValues(&$attributeValue, $attribute)
    {
        $optionsText = [];
        $attributeValueArr = explode(',', $attributeValue);
        foreach ($attributeValueArr as $optionId) {
            $optionsText[] = $attribute->getSource()->getOptionText($optionId);
        }
        $attributeValue = implode(',', $optionsText);
    }
    
    /**
     * @return array
     */
    private function getMeasurements()
    {
        return [
            'length' => $this->getProductAttribute('ts_dimensions_length') ?? '',
            'width' => $this->getProductAttribute('ts_dimensions_width') ?? '',
            'height' => $this->getProductAttribute('ts_dimensions_height') ?? '',
            'measure_unit' => $this->getWeightUnit() == 'lbs' ? 'in' : 'cm',
            'weight' => $this->getProductAttribute('weight') ?? '',
            'weight_unit' => $this->getWeightUnit() == 'lbs' ? 'lb' : 'kg'
        ];
    }
    
    /**
     * @return mixed
     */
    public function getWeightUnit()
    {
        return $this->scopeConfig->getValue('general/locale/weight_unit', ScopeInterface::SCOPE_STORE);
    }
    
    /**
     * Confirm that the associated product of configurable is set
     * @return bool
     */
    protected function simpleProductIsSet()
    {
        if ($this->selectedProduct) {
            return true;
        }
        
        return false;
    }
    
    /**
     * @return array
     */
    public function buildApiRequestShippingParams()
    {
        $carriers = [];
        $activeCarriers = $this->shipConfig->getActiveCarriers();
        foreach($activeCarriers as $carrierCode => $carrierModel) {
            $carriers[$carrierCode] = ['methods' => []];
            if ($carrierMethods = $carrierModel->getAllowedMethods()) {
                foreach ($carrierMethods as $code => $method) {
                    $carriers[$carrierCode]['methods'][] = $code;
                }
            }
        }
        
        return $carriers;
    }
}