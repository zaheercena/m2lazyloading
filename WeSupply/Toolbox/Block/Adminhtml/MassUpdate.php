<?php

namespace WeSupply\Toolbox\Block\Adminhtml;

class MassUpdate extends \Magento\Backend\Block\Template
{
    /**
     * @return string
     */
	public function getMassUpdateOrderNumberUrl() {
        return $this->getUrl('wesupply/massupdater/getorders');
    }

    /**
     * @return string
     */
    public function getMassUpdateUpdateOrdersUrl() {
        return $this->getUrl('wesupply/massupdater/update');
    }
}