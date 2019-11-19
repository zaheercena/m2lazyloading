<?php
namespace WeSupply\Toolbox\Plugin\Model\Source;

use Magento\Email\Model\Source\Variables;

class VariablePlugin
{
    /**
     * @param Variables $subject
     * @param $result
     * @return array
     */
    public function afterGetData(Variables $subject, $data)
    {
        $data[] = [
            'value' => 'wesupply_api/step_2/wesupply_subdomain',
            'label' => __('WeSupply SubDomain')
        ];

        return $data;
    }
}