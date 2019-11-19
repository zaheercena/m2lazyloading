<?php

namespace WeSupply\Toolbox\Model;

/**
 * Class OrderInfoBuilder
 * @package WeSupply\Toolbox\Model
 */
class OrderInfoBuilder implements \WeSupply\Toolbox\Api\OrderInfoBuilderInterface
{

    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    protected $orderRepositoryInterface;

    /**
     * @var \Magento\Framework\Event\ManagerInterface
     */
    protected $eventManager;

    /**
     * @var \Magento\Directory\Model\CountryFactory
     */
    protected $countryFactory;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var bool
     */
    protected $debug = false;

    /**
     * @var \Magento\Catalog\Api\ProductRepositoryInterfaceFactory
     */
    protected $productRepositoryInterfaceFactory;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManagerInterface;

    /**
     * @var string
     * url to media directory
     */
    protected $mediaUrl;

    /**
     * @var string
     * order status label
     */
    protected $orderStatusLabel;

    /**
     * @var array
     */
    protected $weSupplyStatusMappedArray;


    /**
     * @var \WeSupply\Toolbox\Helper\WeSupplyMappings
     */
    protected $weSupplyMappings;

    /**
     * @var \Magento\Framework\Stdlib\DateTime\TimezoneInterface
     */
    private $timezone;

    /**
     * @var \WeSupply\Toolbox\Helper\Data
     */
    private $_helper;

    /**
     * @string  product image subdirectory
     */
    CONST PRODUCT_IMAGE_SUBDIRECTORY = 'catalog/product';

    /**
     * @string used as prefix for wesupply order id to avoid duplicate id with other providers (aptos)
     */
    CONST PREFIX = 'mage_';

    /**
     * @int
     */
    CONST ITEM_STATUS_SHIPPED = 1;

    CONST EXCLUDED_ITEMS
        = [
            1 => \Magento\Downloadable\Model\Product\Type::TYPE_DOWNLOADABLE,
            2 => \Magento\Catalog\Model\Product\Type::TYPE_VIRTUAL
        ];


    /**
     * OrderInfoBuilder constructor.
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepositoryInterface
     * @param \Magento\Framework\Event\ManagerInterface $eventManager
     * @param \Magento\Directory\Model\CountryFactory $countryFactory
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Catalog\Api\ProductRepositoryInterfaceFactory $productRepositoryInterfaceFactory
     * @param \Magento\Store\Model\StoreManagerInterface $storeManagerInterface
     * @param \WeSupply\Toolbox\Helper\WeSupplyMappings $weSupplyMappings
     * @param \Magento\Framework\Stdlib\DateTime\TimezoneInterface $timezone
     * @param \WeSupply\Toolbox\Helper\Data $helper
     */
    public function __construct(
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepositoryInterface,
        \Magento\Framework\Event\ManagerInterface $eventManager,
        \Magento\Directory\Model\CountryFactory $countryFactory,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Catalog\Api\ProductRepositoryInterfaceFactory $productRepositoryInterfaceFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManagerInterface,
        \WeSupply\Toolbox\Helper\WeSupplyMappings $weSupplyMappings,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $timezone,
        \WeSupply\Toolbox\Helper\Data $helper
    )
    {
        $this->orderRepositoryInterface = $orderRepositoryInterface;
        $this->eventManager = $eventManager;
        $this->countryFactory = $countryFactory;
        $this->logger = $logger;
        $this->productRepositoryInterfaceFactory = $productRepositoryInterfaceFactory;
        $this->storeManagerInterface = $storeManagerInterface;
        $this->weSupplyMappings = $weSupplyMappings;
        $this->weSupplyStatusMappedArray = $weSupplyMappings->mapOrderStateToWeSupplyStatus();
        $this->timezone = $timezone;
        $this->_helper = $helper;
     }

    /**
     * @param $flag
     */
    public function setDebug($flag)
    {
        $this->debug = $flag;
    }

    /**
     * @param $orderId
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function gatherInfo($orderId)
    {
        try {
            $order = $this->orderRepositoryInterface->get($orderId);
        } catch (\Exception $ex) {
            $this->logger->error("WeSupply Error: Order with id $orderId not found");
            return [];
        }
        $orderData = $order->getData();
        $this->orderStatusLabel = $order->getStatusLabel();
        if(!is_string($this->orderStatusLabel))
        {
            $this->orderStatusLabel = $order->getStatusLabel()->__toString();
        }

        $storeManager = $this->storeManagerInterface->getStore($orderData['store_id']);
        $this->mediaUrl = $storeManager->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);


        $shippingObj = $order->getShippingMethod(true);
        $carrierCode = '';
        if(is_object($shippingObj)){
            $carrierCode = $shippingObj->getData('carrier_code');
            if(isset($this->weSupplyMappings::MAPPED_CARRIER_CODES[$carrierCode])){
                $carrierCode = $this->weSupplyMappings::MAPPED_CARRIER_CODES[$carrierCode];
            }
        }

        if(empty($carrierCode)){
            $carrierCode = $order->getShippingMethod();
        }


        $orderData['carrier_code'] = $carrierCode;
        $orderData['wesupply_updated_at'] = date('Y-m-d H:i:s');

        unset($orderData['extension_attributes']);
        unset($orderData['items']);

        /** Gather order items information */
        $items = $order->getItems();
        foreach ($items as $item) {
            $itemData = $item->getData();
            if (isset($itemData['parent_item'])) {
                continue;
            }

            unset($itemData['has_children']);
            $orderData['OrderItems'][] = $itemData;
        }

        /** Set billing and shipping Address */
        $billingAddressData = $order->getBillingAddress()->getData();
        $orderData['billingAddressInfo'] = $billingAddressData;

        /** Downloadable product order have no shipping address */
        $shippingAdressData = $billingAddressData;
        if ($order->getShippingAddress()) {
            $shippingAdressData = $order->getShippingAddress()->getData();
        }
        $orderData['shippingAddressInfo'] = $shippingAdressData;

        /** Gather the shipments and trackings information */
        $shipmentTracks = [];
        $shipmentData = [];
        $shipmentCollection = $order->getShipmentsCollection();

        if ($shipmentCollection->getSize()) {
            foreach ($shipmentCollection->getItems() as $shipment) {
                $tracks = $shipment->getTracksCollection();

                foreach ($tracks->getItems() as $track) {
                    $shipmentTracks[$track['parent_id']]['track_number'] = $track['track_number'];
                    $shipmentTracks[$track['parent_id']]['title'] = $track['title'];
                    $shipmentTracks[$track['parent_id']]['carrier_code'] = $track['carrier_code'];
                }

                $sItems = $shipmentItems = $shipment->getItemsCollection();
                if(method_exists($shipmentItems,'getItems')){
                    $sItems = $shipmentItems->getItems();
                }
              //  foreach ($shipmentItems->getItems() as $shipmentItem) {
                foreach ($sItems as $shipmentItem) {
                    /** Default empty values for non existing tracking */
                    if (!isset($shipmentTracks[$shipmentItem['parent_id']])) {
                        $shipmentTracks[$shipmentItem['parent_id']]['track_number'] = '';
                        $shipmentTracks[$shipmentItem['parent_id']]['title'] = '';
                        $shipmentTracks[$shipmentItem['parent_id']]['carrier_code'] = '';
                    }
                    $shipmentData[$shipmentItem['order_item_id']][] = array_merge([
                        'qty' => $shipmentItem['qty'],
                        'sku' => $shipmentItem['sku']
                    ], $shipmentTracks[$shipmentItem['parent_id']]);
                }
            }
        }
        $orderData['shipmentTracking'] = $shipmentData;

        /** Set payment data */
        $paymentData = $order->getPayment()->getData();
        $orderData['paymentInfo'] = $paymentData;

        $this->eventManager->dispatch(
            'wesupply_order_gather_info_after',
            ['orderData' => $orderData]
        );

        $orderData = $this->mapFieldsForWesupplyStructure($orderData);

        return $orderData;

    }

    /**
     * Prepares the order information for db storage
     * @param array $orderData
     * @return string
     */
    public function prepareForStorage($orderData)
    {
        $orderInfo = $this->convertInfoToXml($orderData);
        return $orderInfo;
    }

    /**
     * Returns the order last updated time
     * @param array $orderData
     * @return string
     */
    public function getUpdatedAt($orderData)
    {
        return $orderData['OrderModified'];
//        return $orderData['WesupplyUpdatedAt'];
//        return $orderData['wesupply_updated_at'];
//        return $orderData['updated_at'];
    }

    /**
     * Return the store id from the order information array
     * @param array $orderData
     * @return int
     */
    public function getStoreId($orderData)
    {
        return $orderData['StoreId'];
        //return $orderData['store_id'];
    }


    /**
     * @param $date
     * @return false|string
     */
    protected function modifyToLocalTimezone($date)
    {
        $formatedDate = '';

        if($date){
            try{
                $formatedDate = $this->timezone->formatDateTime($date,\IntlDateFormatter::SHORT,\IntlDateFormatter::MEDIUM,null,null,'yyyy-MM-dd HH:mm:ss');
            }catch(\Exception $e){
                $this->logger->error("WeSupply Error when changing date to local timezone:".$e->getMessage());
               // return $formatedDate;
                return FALSE;
            }

        }

        return $formatedDate;
    }

    /**
     * @param $orderData
     * @return array|bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    protected function mapFieldsForWesupplyStructure($orderData)
    {
        $updatedAt =  $this->modifyToLocalTimezone($orderData['updated_at']);
        if(!$updatedAt){
            $updatedAt =  $this->modifyToLocalTimezone(date('Y-m-d H:i:s'));
        }

        $createdAt = $this->modifyToLocalTimezone($orderData['created_at']);
        if(!$createdAt){
            $createdAt = $this->modifyToLocalTimezone(date('Y-m-d H:i:s'));
        }

        $finalOrderData = [];
        // $finalOrderData['MagentoVersion'] = $this->productMetadata->getEdition();
        $finalOrderData['OrderDate'] = $createdAt;
        $finalOrderData['LastModifiedDate'] = $updatedAt;
        $finalOrderData['StoreId'] = $this->_helper->recursivelyGetArrayData(['store_id'], $orderData);
        // $finalOrderData['OrderModified'] = $this->modifyToLocalTimezone($this->_helper->recursivelyGetArrayData(['wesupply_updated_at'], $orderData));
        $finalOrderData['OrderModified'] = $this->_helper->recursivelyGetArrayData(['wesupply_updated_at'], $orderData);
        $finalOrderData['OrderPaymentTypeId'] = '';
        $finalOrderData['OrderID'] = self::PREFIX.$this->_helper->recursivelyGetArrayData(['entity_id'], $orderData);
        $finalOrderData['OrderNumber'] = $this->_helper->recursivelyGetArrayData(['increment_id'], $orderData);
        $finalOrderData['FirstName'] = $this->_helper->recursivelyGetArrayData(['shippingAddressInfo', 'firstname'], $orderData);
        $finalOrderData['LastName'] = $this->_helper->recursivelyGetArrayData(['shippingAddressInfo', 'lastname'], $orderData);
        $finalOrderData['OrderContact'] = $finalOrderData['FirstName'] . ' ' . $finalOrderData['LastName'];
        $finalOrderData['OrderAmount'] = $this->_helper->recursivelyGetArrayData(['base_subtotal'], $orderData);
        $finalOrderData['OrderAmountShipping'] = $this->_helper->recursivelyGetArrayData(['base_shipping_amount'], $orderData);
        $finalOrderData['OrderAmountTax'] = $this->_helper->recursivelyGetArrayData(['base_tax_amount'], $orderData);
        $finalOrderData['OrderAmountTotal'] = $this->_helper->recursivelyGetArrayData(['base_grand_total'], $orderData);
        $finalOrderData['OrderAmountCoupon'] = number_format(0, 4,'.','');
        $finalOrderData['OrderAmountGiftCard'] = $this->_helper->recursivelyGetArrayData(['base_gift_card_amount'], $orderData, '0.0000');
        $finalOrderData['OrderShippingAddress1'] = $this->_helper->recursivelyGetArrayData(['shippingAddressInfo','street'], $orderData);
        $finalOrderData['OrderShippingCity'] = $this->_helper->recursivelyGetArrayData(['shippingAddressInfo','city'], $orderData);
        $finalOrderData['OrderShippingStateProvince'] = $this->_helper->recursivelyGetArrayData(['shippingAddressInfo','region'], $orderData);
        $finalOrderData['OrderShippingZip'] = $this->_helper->recursivelyGetArrayData(['shippingAddressInfo','postcode'], $orderData);
        $finalOrderData['OrderShippingPhone'] = $this->_helper->recursivelyGetArrayData(['shippingAddressInfo','telephone'], $orderData);
        $finalOrderData['OrderShippingCountry'] = $this->getCountryName($this->_helper->recursivelyGetArrayData(['shippingAddressInfo','country_id'], $orderData));
        $finalOrderData['OrderShippingCountryCode'] = $this->_helper->recursivelyGetArrayData(['shippingAddressInfo','country_id'], $orderData);
        $finalOrderData['OrderPaymentType'] = $this->_helper->recursivelyGetArrayData(['paymentInfo','additional_information','method_title'], $orderData);
        $finalOrderData['OrderDiscountDetailsTotal'] = $this->_helper->recursivelyGetArrayData(['base_discount_amount'], $orderData);
        $finalOrderData['OrderExternalOrderID'] = $this->_helper->recursivelyGetArrayData(['increment_id'], $orderData);
        $finalOrderData['CurrencyCode'] = $this->_helper->recursivelyGetArrayData(['order_currency_code'], $orderData);
        // $orderStatusInfo = $this->mapOrderStateToWeSupply($orderData);
        $finalOrderData['OrderStatus'] = ($this->mapOrderStateToWeSupply($orderData))['OrderStatus'];
        $finalOrderData['OrderStatusId'] = ($this->mapOrderStateToWeSupply($orderData))['OrderStatusId'];

        /**
         * Check if customer is logged in get data from there otherwise from billing and shipping infromation
         */
        $finalOrderData['OrderCustomer']['CustomerName'] = $orderData['billingAddressInfo']['firstname'] . ' ' . $orderData['billingAddressInfo']['lastname'];

        $finalOrderData['OrderCustomer']['IsGuest'] = $this->_helper->recursivelyGetArrayData(['customer_is_guest'], $orderData);
        $finalOrderData['OrderCustomer']['CustomerID'] = $this->_helper->recursivelyGetArrayData(['customer_id'], $orderData) ?? intval(664616765 . '' . $orderData['entity_id']); // mage(hex) + order id
        $finalOrderData['OrderCustomer']['CustomerFirstName'] = $this->_helper->recursivelyGetArrayData(['billingAddressInfo', 'firstname'], $orderData);
        $finalOrderData['OrderCustomer']['CustomerLastName'] = $this->_helper->recursivelyGetArrayData(['billingAddressInfo', 'lastname'], $orderData);
        $finalOrderData['OrderCustomer']['CustomerPostalCode'] = $this->_helper->recursivelyGetArrayData(['billingAddressInfo', 'postcode'], $orderData);
        $finalOrderData['OrderCustomer']['CustomerEmail'] = $this->_helper->recursivelyGetArrayData(['billingAddressInfo', 'email'], $orderData);
        $finalOrderData['OrderCustomer']['CustomerShippingAddresses']['CustomerShippingAddress']['AddressID'] = $this->_helper->recursivelyGetArrayData(['shippingAddressInfo', 'entity_id'], $orderData);
        $finalOrderData['OrderCustomer']['CustomerShippingAddresses']['CustomerShippingAddress']['AddressContact'] =
            $this->_helper->recursivelyGetArrayData(['shippingAddressInfo', 'firstname'], $orderData) . ' ' . $this->_helper->recursivelyGetArrayData(['shippingAddressInfo', 'lastname'], $orderData);
        $finalOrderData['OrderCustomer']['CustomerShippingAddresses']['CustomerShippingAddress']['AddressAddress1'] = $this->_helper->recursivelyGetArrayData(['shippingAddressInfo', 'street'], $orderData);
        $finalOrderData['OrderCustomer']['CustomerShippingAddresses']['CustomerShippingAddress']['AddressCity'] = $this->_helper->recursivelyGetArrayData(['shippingAddressInfo', 'city'], $orderData);
        $finalOrderData['OrderCustomer']['CustomerShippingAddresses']['CustomerShippingAddress']['AddressState'] = $this->_helper->recursivelyGetArrayData(['shippingAddressInfo', 'region'], $orderData);
        $finalOrderData['OrderCustomer']['CustomerShippingAddresses']['CustomerShippingAddress']['AddressZip'] = $this->_helper->recursivelyGetArrayData(['shippingAddressInfo', 'postcode'], $orderData);
        $finalOrderData['OrderCustomer']['CustomerShippingAddresses']['CustomerShippingAddress']['AddressCountry'] = $this->getCountryname($this->_helper->recursivelyGetArrayData(['shippingAddressInfo', 'country_id'], $orderData));
        $finalOrderData['OrderCustomer']['CustomerShippingAddresses']['CustomerShippingAddress']['AddressCountryCode'] = $this->_helper->recursivelyGetArrayData(['shippingAddressInfo', 'country_id'], $orderData);
        $finalOrderData['OrderCustomer']['CustomerShippingAddresses']['CustomerShippingAddress']['AddressPhone'] = $this->_helper->recursivelyGetArrayData(['shippingAddressInfo', 'telephone'], $orderData);

        $orderItems = $this->prepareOrderItems($orderData);

        /**
         * if we only have virtual or downloadable products in order, we are not updating wesupply_orders table
         */
        if(count($orderItems)==0)
        {
            return false;
        }

        $finalOrderData['OrderItems'] = $orderItems;

        $this->eventManager->dispatch(
            'wesupply_order_mapping_info_after',
            [
                'initialOrderData' => $orderData,
                'finalOrderData' => $finalOrderData
            ]
        );

        return $finalOrderData;
    }


    /**
     * Converts order information
     * @param $orderData
     * @return mixed
     */
    protected function convertInfoToXml($orderData)
    {
        $xmlData = $this->array2xml($orderData, false);
        $xmlData = str_replace("<?xml version=\"1.0\"?>\n", '', $xmlData);

        return $xmlData;
    }

    /**
     * Convert array to xml
     * @param $array
     * @param bool $xml
     * @param string $xmlAttribute
     * @return mixed
     */
    private function array2xml($array, $xml = false, $xmlAttribute = '')
    {
        if ($xml === false) {
            $xml = new \SimpleXMLElement('<Order/>');
        }

        foreach ($array as $key => $value) {
            /**
             *  had to comment out str_replace because there is a field in Wesupply that uses an underscore (_)
             *  Field Name: Item_CouponAmount
             */
            //$key = str_replace("_", "", ucwords($key, '_'));
            $key = ucwords($key, '_');
            if (is_object($value)) continue;
            if (is_array($value)) {
                if (!is_numeric($key)) {
                    $this->array2xml($value, $xml->addChild($key), $key);
                } else {
                    //mapping for $key to proper
                    $xmlAttribute = $this->mapXmlAttributeForChildrens($xmlAttribute);
                    $this->array2xml($value, $xml->addChild($xmlAttribute), $key);
                }
            } else {
                if (is_numeric($key)) {
                    $child = $xml->addChild($xmlAttribute);
                    $child->addAttribute('key', $key);
                    $value = str_replace(['&','<','>'],['&amp;', '&lt;', '&gt;'], $value);
                    $child->addAttribute('value', $value);
                } else {
                    $value = str_replace(['&','<','>'],['&amp;', '&lt;', '&gt;'], $value);
                    $xml->addChild($key, $value);
                }
            }
        }

        return $xml->asXML();
    }

    /**
     * @param $key
     * @return mixed
     */
    private function mapXmlAttributeForChildrens($key)
    {
        $mappings = [
            'OrderItems' => 'Item',
            'AttributesInfo' => 'Info'
        ];

        if (isset($mappings[$key])) {
            return $mappings[$key];
        }

        return $key;
    }

    /**
     * Return country name
     * @param $countryId
     * @return string
     */
    protected function getCountryName($countryId)
    {
        $country = $this->countryFactory->create()->loadByCode($countryId);
        return $country->getName();
    }

    /**
     * @param $orderData
     * @return array
     * due to posibility of endless order statuses in magento2, we are transfering the order status label and order state mapped to WeSupply order status
     */
    protected function mapOrderStateToWeSupply($orderData)
    {

        $orderStatusId = $this->weSupplyStatusMappedArray[\Magento\Sales\Model\Order::STATE_NEW];

        if(isset($orderData['state'])) {
            $state = $orderData['state'];
            if (array_key_exists($state, $this->weSupplyStatusMappedArray)) {
                $orderStatusId = $this->weSupplyStatusMappedArray[$state];
            }
        }

        $statusInfo = [
            'OrderStatus' => $this->orderStatusLabel,
            'OrderStatusId' => $orderStatusId
        ];

        return $statusInfo;

    }


    /**
     * @param $status
     * @return array
     */
    protected function getItemStatusInfo($status)
    {
        switch ($status) {
            case 'canceled':
                $orderStatus = 'Canceled';
                $orderStatusId = 1;
                break;
            case 'refunded':
                $orderStatus = 'Refunded';
                $orderStatusId = 2;
                break;
            case 'shipped':
                $orderStatus = 'Shipped';
                $orderStatusId = 3;
                break;
            default:
                $orderStatus = 'Processing';
                $orderStatusId = 4;
                break;

        }

        $statusInfo = [
            'ItemStatus' => $orderStatus,
            'ItemStatusId' => $orderStatusId
        ];

        return $statusInfo;
    }


    /**
     * @param $orderData
     * @return array
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    protected function prepareOrderItems($orderData)
    {
        $orderItems = [];

        $itemFeeShipping = $this->_helper->recursivelyGetArrayData(['base_shipping_amount'], $orderData, 0);
        $orderItemsData = $orderData['OrderItems'];

        foreach ($orderItemsData as $item) {
            /**
             * we exclude downlodable and virtual products to be sent to WeSupply
             */
            if(in_array($item['product_type'], self::EXCLUDED_ITEMS))
            {
                continue;
            }
            $addedToShipment = false;
            $generalData = [];
            $generalData['ItemID'] = $this->_helper->recursivelyGetArrayData(['item_id'], $item);
            $generalData['ItemLevelSupplierName'] = $this->_helper->recursivelyGetArrayData(['store_id'], $orderData);
            $generalData['ItemPrice'] = $this->_helper->recursivelyGetArrayData(['base_price'], $item);
            $generalData['ItemAddressID'] = $this->_helper->recursivelyGetArrayData(['shippingAddressInfo', 'entity_id'], $orderData);
            $generalData['Option1'] = '';
            $generalData['Option2'] = $this->_fetchProductOptionsAsArray($item);
            $generalData['Option3'] = $this->_fetchProductBundleOptionsAsArray($item);
            $generalData['ItemProduct'] = [];
            $generalData['ItemProduct']['ProductID'] = $this->_helper->recursivelyGetArrayData(['product_id'], $item);
            $generalData['ItemProduct']['ProductCode'] = $this->_helper->recursivelyGetArrayData(['name'], $item);
            $generalData['ItemProduct']['ProductPartNo'] = $this->_helper->recursivelyGetArrayData(['sku'], $item);
            $generalData['ItemTitle'] = $this->_helper->recursivelyGetArrayData(['name'], $item);
            /**
             * new field added ItemImageUri
             */
            $generalData['ItemImageUri'] = $this->_fetchProductImage($item);

            $qtyOrdered = intval($this->_helper->recursivelyGetArrayData(['qty_ordered'], $item));
            $qtyCanceled = intval($this->_helper->recursivelyGetArrayData(['qty_canceled'], $item, 0));
            $qtyRefunded = intval($this->_helper->recursivelyGetArrayData(['qty_refunded'], $item, 0));
            $qtyShipped = intval($this->_helper->recursivelyGetArrayData(['qty_shipped'], $item, 0));
            $qtyProcessing = $qtyOrdered - $qtyCanceled - $qtyRefunded - $qtyShipped;

            $itemTotal = $this->_helper->recursivelyGetArrayData(['base_row_total'], $item);
            $taxTotal = $this->_helper->recursivelyGetArrayData(['base_tax_amount'], $item);
            $discountTotal = $this->_helper->recursivelyGetArrayData(['base_discount_amount'], $item);

            /** Send information about shipped items */
            $shippedItems = $orderData['shipmentTracking'];
            foreach ($shippedItems as $itemId => $shipment) {
                if ($itemId == $this->_helper->recursivelyGetArrayData(['item_id'], $item)) {
                    foreach ($shipment as $trackingInformation) {
                        // $carrierCode = isset($trackingInformation['carrier_code']) ? $trackingInformation['carrier_code'] : $orderData['carrier_code'];
                        $carrierCode = $this->_helper->recursivelyGetArrayData(['carrier_code'], $trackingInformation, $this->_helper->recursivelyGetArrayData(['carrier_code'], $orderData));
                        if(isset($this->weSupplyMappings::MAPPED_CARRIER_CODES[$carrierCode])){
                            $carrierCode = $this->weSupplyMappings::MAPPED_CARRIER_CODES[$carrierCode];
                        }
                        $itemInfo = $this->getItemSpecificInformation(
                            $itemFeeShipping,
                            $itemTotal,
                            $taxTotal,
                            $discountTotal,
                            $qtyOrdered,
                            $trackingInformation['qty'],
                            'shipped',
                            $trackingInformation['title'],
                            $trackingInformation['track_number'],
                            $carrierCode
                        );
                        $generalData = array_merge($generalData, $itemInfo);
                        $orderItems[] = $generalData;
                        $addedToShipment = true;
                    }
                }
            }

            if ($qtyCanceled && !$addedToShipment) {
                $itemInfo = $this->getItemSpecificInformation(
                    $itemFeeShipping,
                    $itemTotal,
                    $taxTotal,
                    $discountTotal,
                    $qtyOrdered,
                    $qtyCanceled,
                    'canceled',
                    '',
                    '',
                    $this->_helper->recursivelyGetArrayData(['carrier_code'], $orderData)
                );
                $generalData = array_merge($generalData, $itemInfo);
                $orderItems[] = $generalData;
            }

            /** For more detailed data we might use information  from teh created credit memos */
            if ($qtyRefunded  && !$addedToShipment) {
                $itemInfo = $this->getItemSpecificInformation(
                    $itemFeeShipping,
                    $itemTotal,
                    $taxTotal,
                    $discountTotal,
                    $qtyOrdered,
                    $qtyRefunded,
                    'refunded',
                    '',
                    '',
                    $this->_helper->recursivelyGetArrayData(['carrier_code'], $orderData)
                );
                $generalData = array_merge($generalData, $itemInfo);
                $orderItems[] = $generalData;
            }

            /** Send information about items still in processed state */
            if ($qtyProcessing > 0  && !$addedToShipment) {

                $itemInfo = $this->getItemSpecificInformation(
                    $itemFeeShipping,
                    $itemTotal,
                    $taxTotal,
                    $discountTotal,
                    $qtyOrdered,
                    $qtyProcessing,
                    '',
                    '',
                    '',
                    $this->_helper->recursivelyGetArrayData(['carrier_code'], $orderData)
                );
                $generalData = array_merge($generalData, $itemInfo);
                $orderItems[] = $generalData;
            }

            $itemFeeShipping = 0;
        }

        return $orderItems;
    }

    /**
     * @param $itemFeeShipping
     * @param $itemTotal
     * @param $taxTotal
     * @param $discountTotal
     * @param $qtyOrdered
     * @param $qty
     * @param $status
     * @param $shippingService
     * @param $shippingTracking
     * @param $carrierCode
     * @return array
     */
    protected function getItemSpecificInformation($itemFeeShipping, $itemTotal, $taxTotal, $discountTotal, $qtyOrdered, $qty, $status, $shippingService, $shippingTracking, $carrierCode)
    {
        $information = [];
        $information['ItemQuantity'] = $qty;
        $information['ItemShippingService'] = $shippingService;
        /**
         * new field added ItemPOShipper
         */
        $information['ItemPOShipper'] = $carrierCode;
        $information['ItemShippingTracking'] = $shippingTracking;
        $information['ItemTotal'] = number_format(($qty * $itemTotal) / $qtyOrdered, 4,'.','');
        $information['ItemTax'] = number_format(($qty * $taxTotal) / $qtyOrdered, 4,'.','');
        $information['Item_CouponAmount'] = number_format(($qty * $discountTotal) / $qtyOrdered, 4,'.','');
        $statusInfo = $this->getItemStatusInfo($status);
        $information['ItemStatus'] = $statusInfo['ItemStatus'];
        $information['ItemStatusId'] = $statusInfo['ItemStatusId'];
        /**
         *  new fields added
         *   ItemShipping -  the first item will have shipping value, all other items will have 0 value
         *   Item_CouponAmount - will always have 0, the discount amount is set trough OrderDiscountDetailsTotal field
         */
        $information['ItemShipping'] = number_format($itemFeeShipping, 4,'.','');
        $information['Item_CouponAmount'] = number_format(0,4,'.','');

        return $information;
    }


    private function _fetchProductBundleOptionsAsArray($item)
    {
        $bundleArray =  array();
        /**
         * bundle product options
         */
        $productOptions = $item['product_options'];
        if (isset($productOptions['bundle_options'])) {
            foreach($productOptions['bundle_options'] AS $bundleOptions)
            {
                $bundleProductInfo = array();
                $bundleProductInfo['label'] = $bundleOptions['label'];
                $finalOptionsCounter = 0;
                foreach($bundleOptions['value'] AS $finalOptions)
                {
                    $bundleProductInfo['product_'.$finalOptionsCounter] = $finalOptions;
                    $finalOptionsCounter++;

                }
                $bundleArray['value_'.$bundleOptions['option_id']] = $bundleProductInfo;

            }

        }

       return $bundleArray;
    }


    /**
     * @param $item
     * @return array
     */
    private function _fetchProductOptionsAsArray($item)
    {
        $optionsArray = array();
        /**
         * configurable product options
         */
        $productOptions = $item['product_options'];
        if (isset($productOptions['attributes_info'])) {
            foreach ($productOptions['attributes_info'] as $attributes) {
                $xmlLabel = preg_replace('/[^\w0-1]|^\d/','_',trim($attributes['label']));
                $optionsArray[$xmlLabel] = $attributes['value'];
            }
        }

        /**
         * custom options
         */
        if (isset($productOptions['options'])) {
            foreach($productOptions['options'] as $customOption)
            {
                $xmlLabel = preg_replace('/[^\w0-1]|^\d/','_',trim($customOption['label']));
                $optionsArray[$xmlLabel] = $customOption['value'];
            }
        }

        return $optionsArray;
    }

    /**
     * @param $item
     * @return string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * we are fetching item image (if we have a configurable product order, and the configurable item does not have an image, we are taking the main product image instead
     */
    private function _fetchProductImage($item)
    {

        $imageUrl = '';
        $productOptions = $item['product_options'];
        if ( (isset($productOptions['simple_sku'])) ) {
            $product = $this->productRepositoryInterfaceFactory->create()->get($productOptions['simple_sku']);
            $prdImage = $product->getImage();

            if(empty($prdImage))
            {
                $product = $this->productRepositoryInterfaceFactory->create()->getById($item['product_id']);
                $prdImage = $product->getImage();
            }

        }
        else
        {
            $product = $this->productRepositoryInterfaceFactory->create()->getById($item['product_id']);
            $prdImage = $product->getImage();
        }


        if($prdImage){
            $imageUrl = $this->mediaUrl.self::PRODUCT_IMAGE_SUBDIRECTORY.$prdImage;
        }


        return $imageUrl;

    }
}
