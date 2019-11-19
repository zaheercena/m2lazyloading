<?php
namespace WeSupply\Toolbox\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Class Order
 * @package WeSupply\Toolbox\Model\ResourceModel
 */
class Order extends AbstractDb
{
    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('wesupply_orders', 'id');
    }
}
