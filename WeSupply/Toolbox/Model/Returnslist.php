<?php

namespace WeSupply\Toolbox\Model;

use  \Magento\Framework\Model\AbstractModel;
use  \WeSupply\Toolbox\Api\Data\ReturnslistInterface;

class Returnslist extends AbstractModel implements ReturnslistInterface
{

    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('WeSupply\Toolbox\Model\ResourceModel\Returnslist');
    }


    /**
     * @return int|mixed|null
     */
    public function getId()
    {
        return $this->getData(self::ID);
    }

    /**
     * @return int|mixed
     */
    public function getReturnId()
    {
        return $this->getData(self::RETURN_ID);
    }


    /**
     * Set ID
     *
     * @param int $id
     * @return ReturnslistInterface
     */
    public function setId($id)
    {
        return $this->setData(self::ID, $id);
    }

    /**
     * Set return id
     *
     * @param string $id
     * @return ReturnslistInterface
     */
    public function setReturnId($id)
    {
        return $this->setData(self::RETURN_ID, $id);
    }
}