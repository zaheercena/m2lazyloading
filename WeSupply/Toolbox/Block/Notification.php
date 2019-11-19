<?php
namespace WeSupply\Toolbox\Block;

use Magento\Framework\View\Element\Template;
use WeSupply\Toolbox\Model\Config\Source\NotificationDesignMode;
use WeSupply\Toolbox\Model\Config\Source\NotificationDesignType;
use WeSupply\Toolbox\Model\Config\Source\NotificationDesignModeAlignment;
use WeSupply\Toolbox\Model\OrderInfoBuilder;

class Notification extends Template
{

    const XML_CONFIG_COUNTRY_PATH = 'general/country/default';

    /**
     * @var \Magento\Checkout\Model\Session
     */
    private $checkoutSession;

    /**
     * @var \WeSupply\Toolbox\Helper\Data
     */
    private $helper;

    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    private $orderRepository;


    private $phoneCodes;

    private $scopeConfig;

    /**
     * Notification constructor.
     * @param Template\Context $context
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        \WeSupply\Toolbox\Helper\Data $helper,
        \WeSupply\Toolbox\Helper\PhoneCodes $phoneCodes,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->checkoutSession = $checkoutSession;
        $this->orderRepository = $orderRepository;
        $this->helper = $helper;
        $this->phoneCodes = $phoneCodes;
        $this->scopeConfig = $scopeConfig;
      }

    /**
     * @return array
     */
      public function getCountryPhoneCodes()
      {
          return $this->phoneCodes->getCountryPhoneCodes();
      }


      /**
        * @return mixed
        */
      public function getConfigDefaultCountryCode()
      {
         $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
          return $this->scopeConfig->getValue(self::XML_CONFIG_COUNTRY_PATH, $storeScope);
       }


    /**
     * @return string
     */
    public function getWesupplyExternalOrderId()
    {
        return $this->checkoutSession->getData('last_real_order_id');
    }

    /**
     * @return string
     */
    public function getWesupplyOrderId()
    {
        $weSupplyOrderId = '';
        if( array_key_exists('last_order_id', $this->checkoutSession->getData())){
            $weSupplyOrderId = OrderInfoBuilder::PREFIX.$this->checkoutSession->getData('last_order_id');
        }

        return $weSupplyOrderId;
    }

    /**
     * @return mixed
     */
    public function getShippingPhone()
    {
        $lastOrderId = $this->checkoutSession->getData('last_order_id');
        if($lastOrderId){
            $orderObj = $this->orderRepository->get($lastOrderId);
            $addresObj = $orderObj->getShippingAddress();
            if(!$addresObj)
            {
                $addresObj =  $orderObj->getBillingAddress();
            }

            return $addresObj->getData('telephone') ?? '';
        }

        return '';
     }

     public function getShippingCountryCode()
     {
         $lastOrderId = $this->checkoutSession->getLastOrderId();
         if($lastOrderId){
             $orderObj = $this->orderRepository->get($lastOrderId);
             $addresObj = $orderObj->getShippingAddress();
             if(!$addresObj)
             {
                 $addresObj =  $orderObj->getBillingAddress();
             }

             return $addresObj->getCountryId() ?? $this->getConfigDefaultCountryCode();
         }

         return $this->getConfigDefaultCountryCode();
     }

    /**
     * @return mixed
     */
    public function getDomain()
    {
        return $this->helper->getWeSupplyDomain();
    }

    /**
     * @return string
     */
    public function getSubdomain()
    {
        return $this->helper->getWeSupplySubDomain();
    }

    /**
     * @return mixed
     */
    public function getDesign()
    {
        return $this->helper->getNotificationDesign();
    }


    public function getAlignment()
    {
        return $this->helper->getNotificationAlignment();
    }

    /**
     * @return mixed
     */
    public function getEnabledNotification()
    {
        if($this->helper->getWeSupplyEnabled()) {
            return $this->helper->getEnabledNotification();
        }

        return false;
    }

    /**
     * @return mixed
     */
    public function getFirstDesignConstant()
    {
        return NotificationDesignMode::FIRST_DESIGN_CODE;
    }

    /**
     * @return string
     */
    public function getLeftAlignmentConstant()
    {
        return NotificationDesignModeAlignment::ALIGNMENT_LEFT_DESIGN_CODE;
    }

    /**
     * @return string
     */
    public function getCenterAlignmentConstant()
    {
        return NotificationDesignModeAlignment::ALIGNMENT_CENTER_DESIGN_CODE;
    }

    /**
     * @return string
     */
    public function getDefaultLocationType()
    {
        return NotificationDesignType::FIRST_TYPE_CODE;
    }

    /**
     * @return mixed
     */
    public function getNotificationBoxType()
    {
        return $this->helper->getNotificationBoxType();
    }

    /**
     * @return string
     */
    public function getAjaxUrl()
    {
        return $this->getUrl('wesupply/notification/notify');
    }
}