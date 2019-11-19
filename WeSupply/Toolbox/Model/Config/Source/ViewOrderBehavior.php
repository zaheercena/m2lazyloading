<?php
namespace WeSupply\Toolbox\Model\Config\Source;

class ViewOrderBehavior implements \Magento\Framework\Option\ArrayInterface
{

    /**
     * Tracking Info default type
     */
    const WESUPPLY_TYPE_CODE = 0;

    /**
     * Tracking Info default type label
     */
    const WESUPPLY_TYPE_LABEL ='Open on WeSupply Platform';

    /**
     * Tracking Info Iframe type
     */
    const IFRAME_TYPE_CODE = 1;

    /**
     * Tracking Info Iframe type label
     */
    const  IFRAME_TYPE_LABEL = 'Open in Modal';

    public function toOptionArray()
    {
        return [
            ['value' => self::IFRAME_TYPE_CODE, 'label' => __(self::IFRAME_TYPE_LABEL)],
            ['value' => self::WESUPPLY_TYPE_CODE, 'label' => __(self::WESUPPLY_TYPE_LABEL)]
        ];
    }
}