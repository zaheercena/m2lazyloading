<?php
namespace WeSupply\Toolbox\Block;

use Magento\Framework\View\Element\Template;

class WeSupplyLink extends \Magento\Framework\View\Element\Html\Link
{
    protected $_helper;

    public function __construct(
        Template\Context $context,
        \WeSupply\Toolbox\Helper\Data $helper,
        array $data = [])
    {
        parent::__construct($context, $data);
        $this->_helper = $helper;
    }


    public function getHref(){
        if ($this->_helper->trackingInfoIframeEnabled()) {
            return $this->_helper->getTrackingInfoPageUrl();
        }

        return  $this->_helper->getProtocol(). '://' . $this->_helper->getWeSupplySubDomain() . '.' . $this->_helper->getWeSupplyDomain() . '/';
    }

    public function getLabel(){
        return __('Tracking Info');
    }

    public function getTarget()
    {
        if ($this->_helper->trackingInfoIframeEnabled()) {
            return __('_self');
        }

        return __('_blank');
    }

    public function getClass()
    {
        return 'wesupply-tracking-info';
    }

    protected function _toHtml()
    {
        if (false != $this->getTemplate()) {
            return parent::_toHtml();
        }

        if(!$this->_helper->getWeSupplyEnabled() || !$this->_helper->getDeliveryEstimationsHeaderLinkEnabled()){
            return '';
        }


        return '<li><a ' . $this->getLinkAttributes() . ' >' . $this->escapeHtml($this->getLabel()) . '</a></li>';
    }
}