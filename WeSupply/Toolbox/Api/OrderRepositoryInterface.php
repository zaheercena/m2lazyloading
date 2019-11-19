<?php
namespace WeSupply\Toolbox\Api;

/**
 * Interface BlockRepositoryInterface
 * @package WeSupply\Toolbox\Api
 */
interface OrderRepositoryInterface
{
    /**
     * Save order details for wesupply.
     *
     * @param \Magento\Cms\Api\Data\BlockInterface $block
     * @return \Magento\Cms\Api\Data\BlockInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    /**
     * @param \WeSupply\Toolbox\Api\Data\OrderInterface $order
     * @return \WeSupply\Toolbox\Api\Data\OrderInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function save(Data\OrderInterface $order);

    /**
     * Retrieve order info after wesupply order id (db: id).
     *
     * @param int $orderId
     * @return \WeSupply\Toolbox\Api\Data\OrderInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getById($orderId);

    /**
     * Load the order info after order_id
     * @param $magentoOrderId
     * @return \WeSupply\Toolbox\Api\Data\OrderInterface
     */
    public function getByOrderId($magentoOrderId);

    /**
     * Retrieve wesupply orders matching the specified criteria.
     *
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     * @return \WeSupply\Toolbox\Api\Data\OrderSearchResultsInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getList(\Magento\Framework\Api\SearchCriteriaInterface $searchCriteria);

    /**
     * Delete wesupply order.
     *
     * @param \WeSupply\Toolbox\Api\Data\OrderInterface $order
     * @return bool true on success
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function delete(Data\OrderInterface $order);

    /**
     * Delete block by ID.
     *
     * @param int $orderId
     * @return bool true on success
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function deleteById($orderId);
}
