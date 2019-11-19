<?php

namespace WeSupply\Toolbox\Block\System\Config;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use WeSupply\Toolbox\Helper\Data as Helper;

class SubdomainText extends Field
{
    protected $_helper;

    /**
     * SubdomainText constructor.
     * @param Context $context
     * @param Helper $helper
     * @param array $data
     */
    public function __construct(
        Context $context,
        Helper $helper,
        array $data = []
    )
    {
        $this->_helper = $helper;

        parent::__construct($context, $data);
    }

    /**
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        return parent::_getElementHtml($element).'<span>.' . $this->_helper->getWeSupplyDomain() .'</span>';
    }

}