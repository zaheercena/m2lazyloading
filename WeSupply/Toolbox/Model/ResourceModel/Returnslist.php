<?php
namespace WeSupply\Toolbox\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Returnslist extends AbstractDb
{
    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('wesupply_returns_list', 'id');
    }
}
