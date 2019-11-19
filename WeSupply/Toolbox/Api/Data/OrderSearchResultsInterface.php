<?php
namespace WeSupply\Toolbox\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

/**
 * Interface OrderSearchResultsInterface
 * @package WeSupply\Toolbox\Api\Data
 */
interface OrderSearchResultsInterface extends SearchResultsInterface
{
    /**
     * Get blocks list.
     *
     * @return \WeSupply\Toolbox\Api\Data\OrderInterface[]
     */
    public function getItems();

    /**
     * Set blocks list.
     *
     * @param \WeSupply\Toolbox\Api\Data\OrderInterface[] $items
     * @return $this
     */
    public function setItems(array $items);
}
