<?php
namespace WeSupply\Toolbox\Api\Data;

/**
 * Interface OrderInterface
 * @package WeSupply\Toolbox\Api\Data
 */
interface OrderInterface
{
    /**#@+
     * Constants for keys of data array. Identical to the name of the getter in snake case
     */
    const ID            = 'id';
    const ORDER_ID      = 'order_id';
    const UPDATED_AT    = 'updated_at';
    const INFO          = 'info';
    const STORE_ID      = 'store_id';
    /**#@-*/

    /**
     * Get ID
     *
     * @return int|null
     */
    public function getId();

    /**
     * Get identifier
     *
     * @return int
     */
    public function getOrderId();

    /**
     * Get title
     *
     * @return string|null
     */
    public function getInfo();

    /**
     * Get updated at time
     *
     * @return string|null
     */
    public function getUpdatedAt();

    /**
     * Get store id
     *
     * @return int
     */
    public function getStoreId();

    /**
     * Set ID
     *
     * @param int $id
     * @return OrderInterface
     */
    public function setId($id);

    /**
     * Set Order ID
     *
     * @param int $id
     * @return OrderInterface
     */
    public function setOrderId($id);

    /**
     * Set order information
     *
     * @param string $info
     * @return OrderInterface
     */
    public function setInfo($info);

    /**
     * Set update time
     *
     * @param string $updateTime
     * @return OrderInterface
     */
    public function setUpdatedAt($updateTime);

    /**
     * Set Store ID
     *
     * @param int $id
     * @return OrderInterface
     */
    public function setStoreId($id);
}
