<?php
namespace WeSupply\Toolbox\Plugin\Magento\Checkout\Block\Checkout;

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


    /**
     * @param \Magento\Checkout\Block\Checkout\LayoutProcessor $subject
     * @param $result
     * @return mixed
     */
    public function afterProcess(
        \Magento\Checkout\Block\Checkout\LayoutProcessor $subject,
        $result
    ) {

        if (!$this->helper->getWeSupplyEnabled() ||  !$this->helper->getDeliveryEstimationsEnabled()) {

            if (isset($result['components']['checkout']['children']['steps']['children']['shipping-step']['children']['shippingAddress']['config']['shippingMethodItemTemplate'])) {
                if ($result['components']['checkout']['children']['steps']['children']['shipping-step']['children']['shippingAddress']['config']['shippingMethodItemTemplate'] == 'WeSupply_Toolbox/wesupply-item-template') {
                    unset($result['components']['checkout']['children']['steps']['children']['shipping-step']['children']['shippingAddress']['config']['shippingMethodItemTemplate']);
                }
            }

            if (isset($result['components']['checkout']['children']['steps']['children']['shipping-step']['children']['shippingAddress']['config']['shippingMethodListTemplate'])) {
                if ($result['components']['checkout']['children']['steps']['children']['shipping-step']['children']['shippingAddress']['config']['shippingMethodListTemplate'] == 'WeSupply_Toolbox/wesupply-list-template') {
                    unset($result['components']['checkout']['children']['steps']['children']['shipping-step']['children']['shippingAddress']['config']['shippingMethodListTemplate']);
                }
            }

        }
        return $result;

    }

}
