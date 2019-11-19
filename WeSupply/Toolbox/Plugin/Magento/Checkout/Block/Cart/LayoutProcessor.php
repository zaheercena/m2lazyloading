<?php
namespace WeSupply\Toolbox\Plugin\Magento\Checkout\Block\Cart;

class LayoutProcessor
{
    /**
     * @var \WeSupply\Toolbox\Helper\Data
     */
    private $helper;

    /**
     * LayoutProcessor constructor.
     * @param \WeSupply\Toolbox\Helper\Data $helper
     */
    public function __construct(
        \WeSupply\Toolbox\Helper\Data $helper
    )
    {
        $this->helper = $helper;
    }

    public function afterProcess(
        \Magento\Checkout\Block\Cart\LayoutProcessor $subject,
        $result
    ) {
        if (!$this->helper->getWeSupplyEnabled() ||  !$this->helper->getDeliveryEstimationsEnabled()) {

            if (isset($result['components']['block-summary']['children']['block-rates']['config']['template'])) {
                if ($result['components']['block-summary']['children']['block-rates']['config']['template'] == 'WeSupply_Toolbox/cart/shipping-rates') {
                    unset($result['components']['block-summary']['children']['block-rates']['config']['template']);
                }

            }
        }
        return $result;
    }
}