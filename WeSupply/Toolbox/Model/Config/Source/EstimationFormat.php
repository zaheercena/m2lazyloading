<?php
namespace WeSupply\Toolbox\Model\Config\Source;

class EstimationFormat implements \Magento\Framework\Option\ArrayInterface
{


    public function toOptionArray()
    {
        return [
            ['value' => 'm/d', 'label' => __('mm/dd (04/28)')],
            ['value' => 'd/m', 'label' => __('dd/mm (28/04)')],
            ['value' => 'F d', 'label' => __('Month Day (April 28)')],
            ['value' => 'd F', 'label' => __('Day Month (28 April)')],
            ['value' => 'd/m/Y', 'label' => __('dd/mm/yyyy (28/04/2019)')],
        ];
    }
}