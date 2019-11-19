<?php
namespace WeSupply\Toolbox\Model\Config\Source;

class NotificationDesignModeAlignment implements \Magento\Framework\Option\ArrayInterface
{

    /**
     * notification first design code
     */
    const ALIGNMENT_LEFT_DESIGN_CODE = 'left';

    /**
     * notification first design label
     */
    const ALIGNMENT_LEFT_DESIGN_LABEL ='Left';

    /**
     * notification second design code
     */
    const ALIGNMENT_CENTER_DESIGN_CODE = 'center';

    /**
     * notification second design label
     */
    const  ALINGMENT_CENTER_DESIGN_LABEL = 'Center';

    public function toOptionArray()
    {
        return [
            ['value' => self::ALIGNMENT_LEFT_DESIGN_CODE, 'label' => __(self::ALIGNMENT_LEFT_DESIGN_LABEL)],
            ['value' => self::ALIGNMENT_CENTER_DESIGN_CODE, 'label' => __(self::ALINGMENT_CENTER_DESIGN_LABEL)]
        ];
    }
}