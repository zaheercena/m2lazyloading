<?php

/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
 
namespace WeSupply\Toolbox\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Catalog\Model\Session as CatalogSession;
use Magento\Catalog\Helper\Data as CatalogHelper;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\LocalizedException;
use WeSupply\Toolbox\Helper\Data as ToolboxHelper;

class DeliveryEstimate extends Template
{
    
    /**
     * @var $product
     */
    private $product;
    
    /**
     * @var ToolboxHelper
     */
    private $helper;
    
    /**
     * @var SerializerInterface
     */
    private $serializer;
    
    /**
     * @var CheckoutSession
     */
    private $checkoutSession;
    
    /**
     * @var CustomerSession
     */
    private $customerSession;
    
    /**
     * @var array $defaultAddress
     */
    private $defaultAddress = [];
    
    /**
     * @var CatalogSession
     */
    private $catalogSession;
    
    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;
    
    /**
     * @var CatalogHelper
     */
    private $catalogHelper;
    
    /**
     * DeliveryEstimate constructor.
     * @param Context $context
     * @param SerializerInterface $serializer
     * @param ScopeConfigInterface $scopeConfig
     * @param CheckoutSession $session
     * @param CustomerSession $customerSession
     * @param CatalogSession $catalogSession
     * @param CatalogHelper $catalogHelper
     * @param ToolboxHelper $helper
     * @param array $data
     */
    public function __construct(
        Context $context,
        SerializerInterface $serializer,
        ScopeConfigInterface $scopeConfig,
        CheckoutSession $session,
        CustomerSession $customerSession,
        CatalogSession $catalogSession,
        CatalogHelper $catalogHelper,
        ToolboxHelper $helper,
        array $data = []
    ) {
        $this->serializer = $serializer;
        $this->helper = $helper;
        $this->checkoutSession = $session;
        $this->customerSession = $customerSession;
        $this->catalogSession = $catalogSession;
        $this->scopeConfig = $scopeConfig;
        $this->catalogHelper = $catalogHelper;
        
        $this->setCurrentProduct();
        $this->initDefaultAddress();
        
        parent::__construct($context, $data);
    }
    
    /**
     * for logged in customers, we init the default address
     */
    private function initDefaultAddress()
    {
        $customer = $this->customerSession->getCustomer();
        if ($customer){
            $defaultAddress = $customer->getDefaultShippingAddress();
            if (!$defaultAddress){
                $defaultAddress = $customer->getDefaultBillingAddress();
            }
            if ($defaultAddress){
                $this->defaultAddress = $defaultAddress;
            }
        }
    }
    
    /**
     * @return array|bool
     */
    public function getSelectedDeliveryEstimate()
    {
        $sessionEstimationsData = $this->catalogSession->getEstimationsData();
        if(!$sessionEstimationsData){
            return false;
        }
        $estimationsArr = $this->serializer->unserialize($sessionEstimationsData);
        /**
         * if estimations session array was created more then 3 hours ago, we destroy it
         */
        if(isset($estimationsArr['created_at'])){
            if( (time() - $estimationsArr['created_at']) > 10800){
                $this->catalogSession->unsEstimationsData();
                return false;
            }
        }
        if(isset($estimationsArr['default'])){
            $selectedZip = $estimationsArr['default'];
            if(isset($estimationsArr[$selectedZip])){
                if(isset($estimationsArr[$selectedZip]['estimated_arrival'])){
                    $result = array();
                    $estimatedDelivery = $estimationsArr[$selectedZip]['estimated_arrival'];
                    $countrycode = (isset($estimationsArr[$selectedZip]['countrycode'])) ? $estimationsArr[$selectedZip]['countrycode'] : '';
                    $country = $this->helper->getCountryname($countrycode);
                    $result['estimatedDelivery'] = $estimatedDelivery;
                    $result['zipcode'] = $selectedZip;
                    $result['country'] = $country;
                    return $result;
                }
            }
        }
        return false;
    }
    
    /**
     * @param $key
     * @return string|null
     */
    public function getAddressDetail($key)
    {
        if(is_object($this->defaultAddress) && method_exists($this->defaultAddress, 'getData')){
            try{
                return $this->defaultAddress->getData($key);
            }catch(\Exception $e){
                return null;
            }
        }
    }
    
    /**
     * @return bool|mixed
     */
    public function getDeliveryEstimationsEnabled()
    {
        if($this->helper->getWeSupplyEnabled()) {
            return $this->helper->getDeliveryEstimationsEnabled();
        }
        return false;
    }
    
    /**
     * @return mixed
     */
    public function getProduct()
    {
        return $this->product;
    }
    
    /**
     * @return bool
     */
    public function productIsShippable()
    {
        if(!$this->product){
            return false;
        }
        if( !$this->product->isSaleable()
            || $this->product->getTypeId() ==  \Magento\Downloadable\Model\Product\Type::TYPE_DOWNLOADABLE
            || $this->product->getTypeId() ==  \Magento\GroupedProduct\Model\Product\Type\Grouped::TYPE_CODE
            || $this->product->getIsVirtual())
        {
            return false;
        }
        return true;
    }
    
    /**
     * @return bool
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function checkQuoteIsVirtual()
    {
        if($this->checkoutSession instanceof CheckoutSession){
            return $this->checkoutSession->getQuote()->isVirtual();
        }
        return true;
    }
    
    /**
     * @return float|int
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getQuoteTotal()
    {
        if($this->checkoutSession instanceof CheckoutSession) {
            return $this->checkoutSession->getQuote()->getGrandTotal();
        }
        return 0;
    }
    
    /**
     * @return int
     * @throws NoSuchEntityException
     */
    public function getCurrentStoreId()
    {
        return $this->_storeManager->getStore()->getId();
    }
    
    /**
     * @return string
     */
    public function getEstimationsUrl()
    {
        return $this->getUrl('wesupply/estimations/estimatedelivery');
    }
    
    /**
     * setting the current product internally
     */
    private function setCurrentProduct()
    {
        $this->product = $this->catalogHelper->getProduct();
    }
    
    /**
     * @return mixed
     * @throws NoSuchEntityException
     */
    public function getStoreCurrency()
    {
        return $this->_storeManager->getStore()->getCurrentCurrency()->getCode();
    }
    
    /**
     * @return int
     */
    public function getProductPrice()
    {
        if(!$this->product){
            return 1;
        }
        return $this->product->getFinalPrice();
    }
    
    /**
     * @param $for
     * @return string
     */
    public function getDeliveryEstimateUrl($for)
    {
        switch ($for) {
            case 'product':
                return $this->getUrl('wesupply/estimations/deliveryproduct');
            case 'checkout':
                return $this->getUrl('wesupply/estimations/deliverycheckout');
        }
    }
    
    /**
     * @return mixed
     */
    public function getProductId()
    {
        return $this->product->getId();
    }
    
    /**
     * @return mixed
     */
    public function getProductType()
    {
        return $this->product->getTypeId();
    }
    
    public function getOrderWithinSeconds()
    {
        $orderWithin = $this->helper->getDeliveryEstimationsOrderWithin();
        // convert in seconds
        return $orderWithin * 60 * 60;
    }

    public function getEstimationDisplay()
    {
        return $this->product->getWesupplyEstimationDisplay() !== null ? $this->product->getWesupplyEstimationDisplay() : 1;
    }
}