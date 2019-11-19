<?php

namespace WeSupply\Toolbox\Helper;

class WeSupplyMappings extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**
     * list of WeSupply order statuses
     */
    const WESUPPLY_ORDER_PROCESSING = 1;
    const WESUPPLY_ORDER_RECEIVED = 2;
    const WESUPPLY_ORDER_COMPLETE = 3;
    const WESUPPLY_ORDER_PENDING_SHIPPING = 4;
    const WESUPPLY_ORDER_CANCELLED = 5;
    const WESUPPLY_ORDER_ONHOLD = 6;
    const WESUPPLY_ORDER_PARTIALLY_COMPLETE = 7;
    const WESUPPLY_ORDER_PAYMENT_FAILURE = 8;
    const WESUPPLY_ORDER_RETURN = 9;

    const MAPPED_CARRIER_CODES = [
        'ups'   => 'UPS',
        'usps'  => 'USPS',
        'fedex' => 'FedEx',
        'dhl'   => 'DHL'
    ];


    const UPS_XML_MAPPINGS = [
        '11' => 'STD',     //  UPS Standard
        '14' => '1DM',     //  UPS Next Day Air Early A.M.
        '54' => 'XPR',     //  UPS Worldwide Express Plus
        '59' => '2DM',     //  UPS Second Day Air A.M.
        '65' => 'WXS',     //  UPS Worldwide Saver
        '01' => '1DA',     //  UPS Next Day Air
        '02' => '2DA',     //  UPS Second Day Air
        '03' => 'GND',     //  UPS Ground
        '07' => 'XPR',     //  UPS Worldwide Express
        '08' => 'XPD',     //  UPS Worldwide Expedited
        '12' => '3DS',     //  UPS Three-Day Select
    ];





    /**
     * @return array
     * maps Magento2 order states with WeSupply order statuses
     */
    public function mapOrderStateToWeSupplyStatus()
    {
        $arrayMaped = array();

        $arrayMaped[\Magento\Sales\Model\Order::STATE_NEW] = self::WESUPPLY_ORDER_RECEIVED;
        $arrayMaped[\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT] = self::WESUPPLY_ORDER_ONHOLD;
        $arrayMaped[\Magento\Sales\Model\Order::STATE_PROCESSING] = self::WESUPPLY_ORDER_PROCESSING;
        $arrayMaped[\Magento\Sales\Model\Order::STATE_COMPLETE] = self::WESUPPLY_ORDER_COMPLETE;
        $arrayMaped[\Magento\Sales\Model\Order::STATE_CLOSED] = self::WESUPPLY_ORDER_COMPLETE;
        $arrayMaped[\Magento\Sales\Model\Order::STATE_CANCELED] = self::WESUPPLY_ORDER_CANCELLED;
        $arrayMaped[\Magento\Sales\Model\Order::STATE_HOLDED] = self::WESUPPLY_ORDER_ONHOLD;
        $arrayMaped[\Magento\Sales\Model\Order::STATE_PAYMENT_REVIEW] = self::WESUPPLY_ORDER_PARTIALLY_COMPLETE;

        return $arrayMaped;
    }
}