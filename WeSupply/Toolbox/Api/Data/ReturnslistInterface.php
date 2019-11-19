<?php
namespace WeSupply\Toolbox\Api\Data;

interface ReturnslistInterface
{
    /**#@+
     * Constants for keys of data array. Identical to the name of the getter in snake case
     */
    const ID            = 'id';
    const RETURN_ID     = 'return_id';
    const TABLE_NAME    = 'wesupply_returns_list';

    /**
     * Get ID
     *
     * @return int|null
     */
    public function getId();

    /**
     * Get return id
     *
     * @return int
     */
    public function getReturnId();


    /**
     * @param $id
     * @return mixed
     */
    public function setId($id);

    /**
     * @param $id
     * @return mixed
     */
    public function setReturnId($id);
}