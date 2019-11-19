<?php
/**
 * Created by PhpStorm.
 * User: adminuser
 * Date: 11.06.2019
 * Time: 17:06
 */

namespace WeSupply\Toolbox\Model\ResourceModel\Returnslist;

use WeSupply\Toolbox\Api\Data\ReturnslistInterface;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{

    protected function _construct()
    {
        $this->_init('WeSupply\Toolbox\Model\Returnslist', 'WeSupply\Toolbox\Model\ResourceModel\Returnslist');
    }


    /**
     * counting the number of returns
     * @param $returnId
     * @return int
     */
    public function countReturns($returnId)
    {
        $this->clear()->getSelect()->reset(\Zend_Db_Select::WHERE);
        $count = $this->addFieldToFilter(ReturnslistInterface::RETURN_ID, $returnId)->load()->count();

        return $count;
    }

}