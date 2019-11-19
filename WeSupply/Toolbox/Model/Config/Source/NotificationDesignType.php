<?php
namespace WeSupply\Toolbox\Model\Config\Source;

class NotificationDesignType implements \Magento\Framework\Option\ArrayInterface
{

    /**
     * notification first design code
     */
    const FIRST_TYPE_CODE = 'default';

    /**
     * notification first design label
     */
    const FIRST_TYPE_LABEL ='Success Page';

    /**
     * notification second design code
     */
    const SECOND_TYPE_CODE = 'widget';

    /**
     * notification second design label
     */
    const  SECOND_TYPE_LABEL = 'Widget';

    public function toOptionArray()
    {
        return [
            ['value' => self::FIRST_TYPE_CODE, 'label' => __(self::FIRST_TYPE_LABEL)],
            ['value' => self::SECOND_TYPE_CODE, 'label' => __(self::SECOND_TYPE_LABEL)]
        ];
    }
}