<?php
namespace WeSupply\Toolbox\Model\Config\Source;

class NotificationDesignMode implements \Magento\Framework\Option\ArrayInterface
{

    /**
     * notification first design code
     */
    const FIRST_DESIGN_CODE = 'first_design';

    /**
     * notification first design label
     */
    const FIRST_DESIGN_LABEL ='Design 1';

    /**
     * notification second design code
     */
    const SECOND_DESIGN_CODE = 'second_design';

    /**
     * notification second design label
     */
    const  SECOND_DESIGN_LABEL = 'Design 2';

    public function toOptionArray()
    {
        return [
            ['value' => self::FIRST_DESIGN_CODE, 'label' => __(self::FIRST_DESIGN_LABEL)],
            ['value' => self::SECOND_DESIGN_CODE, 'label' => __(self::SECOND_DESIGN_LABEL)]
        ];
    }
}