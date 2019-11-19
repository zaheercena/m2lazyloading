<?php

namespace WeSupply\Toolbox\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use WeSupply\Toolbox\Helper\Data as Helper;

class Track extends Template
{
    /**
     * @var array
     */
    private $params;

    /**
     * @var Helper
     */
    private $helper;

    /**
     * Track constructor.
     * @param Context $context
     * @param Helper $helper
     */
    public function __construct(
        Context $context,
        Helper $helper
    )
    {
        $this->params = $context->getRequest()->getParams();
        $this->helper = $helper;

        parent::__construct($context);
    }

    /**
     * @return string
     */
    public function getPlatform()
    {
        return $this->helper->getPlatform();
    }
    
    /**
     * @return bool|mixed
     */
    public function getOrderId()
    {
        $params = $this->getParams();
        if (isset($params['orderID'])) {
            return $params['orderID'];
        }
        
        return false;
    }

    /**
     * @return string
     */
    public function getWeSupplyTrackUrl()
    {
        $protocol = $this->helper->getProtocol();
        $domaine = $this->helper->getWeSupplyDomain();
        $subDomaine = $this->helper->getWeSupplySubDomain();
    
        $trackingId = $this->getTrackingCode();
        if ($trackingId) {
            return $protocol . '://' . $subDomaine . '.' . $domaine . '/track/' . $trackingId . '/';
        }

        return $protocol . '://' . $subDomaine . '.' . $domaine . '/';
    }

    /**
     * @return array
     */
    private function getParams()
    {
        return $this->params;
    }
    
    /**
     * Return tracking id which should be a param key with empty value
     * @return bool|mixed
     */
    private function getTrackingCode()
    {
        $res = array_filter($this->getParams(), function($val) {
            return $val === '';
        });
        
        if ($res) {
            $keys = array_keys($res);
            return reset($keys) ?? false;
        }
        
        return false;
    }
}