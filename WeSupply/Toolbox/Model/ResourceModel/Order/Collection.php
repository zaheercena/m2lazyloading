<?php
namespace WeSupply\Toolbox\Model\ResourceModel\Order;

/**
 * Class Collection
 * @package WeSupply\Toolbox\Model\ResourceModel\Order
 */
class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'id';

    /**
     * Define resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('WeSupply\Toolbox\Model\Order', 'WeSupply\Toolbox\Model\ResourceModel\Order');
    }
}
