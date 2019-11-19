<?php

namespace WeSupply\Toolbox\Block\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class AccessKeyText extends Field
{
    /**
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        return parent::_getElementHtml($element) . '<a class="copy-text" href="javascript:void(0)" data-copy-element="wesupply_api_step_2_access_key">' . __('Copy') . '</a>';
    }

}