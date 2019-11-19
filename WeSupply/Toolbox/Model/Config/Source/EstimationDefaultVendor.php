<?php
namespace WeSupply\Toolbox\Model\Config\Source;

use \WeSupply\Toolbox\Helper\Data;

class EstimationDefaultVendor implements \Magento\Framework\Option\ArrayInterface
{

    private $helperData;

    public function __construct(Data $helperData)
    {
        $this->helperData = $helperData;
    }

    public function toOptionArray()
    {

        $mappedShippingMethods = $this->helperData->getMappedShippingMethods();
        $options = array();
        $options[] = ['value'=>'', 'label'=>'Please Select'];
        foreach($mappedShippingMethods as $carrierCode)
        {

            $options[] = ['value'=> $carrierCode, 'label'=> $carrierCode];
        }


        return $options;

    }
}