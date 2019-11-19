<?php

/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace WeSupply\Toolbox\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

/**
 * Class EstimationDisplay
 * @package WeSupply\Toolbox\Model\Config\Source
 */

class EstimationDisplay implements ArrayInterface
{
    
    /**
     * Return array of options as value-label pairs
     *
     * @return array Format: array(array('value' => '<value>', 'label' => '<label>'), ...)
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => 'earliest',
                'label' => __('Earliest')
            ],
            [
                'value' => 'latest',
                'label' => __('Latest')
            ],
            [
                'value' => 'range',
                'label' => __('Range')
            ]
        ];
    }
}