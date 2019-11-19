<?php
namespace WeSupply\Toolbox\Api;


interface WeSupplyApiInterface
{

    /**
     * @param $externalOrderIdString
     * @return mixed
     */
    function weSupplyInterogation($externalOrderIdString);

    /**
     * @param $orderNo
     * @param $clientPhone
     * @return mixed
     */
    function notifyWeSupply($orderNo, $clientPhone);

    /**
     * @param $ipAddress
     * @param $storeId
     * @param string $zipCode
     * @return mixed
     */
    function getEstimationsWeSupply($ipAddress, $storeId, $zipCode = '');

    /**
     * @param $protocol
     */
    public function setProtocol($protocol);

    /**
     * @param $apiPath
     * @return mixed
     */
    function setApiPath($apiPath);

    /**
     * @param $apiClientId
     * @return mixed
     */
    function setApiClientId($apiClientId);

    /**
     * @param $apiClientSecret
     * @return mixed
     */
    function setApiClientSecret($apiClientSecret);
    
    /**
     * @param $params
     * @param bool $multipleProducts
     * @return mixed
     */
    function getDeliveryEstimations($params, $multipleProducts = false);

    /**
     * @param $ipAddress
     * @param $storeId
     * @param $zipcode
     * @param $countryCode
     * @param $price
     * @param $currency
     * @param $shippers
     * @return mixed
     */
    function getShipperQuotes($ipAddress, $storeId, $zipcode, $countryCode, $price, $currency, $shippers);


    /**
     * @return mixed
     */
    function weSupplyAccountCredentialsCheck();


    /**
     * @return mixed
     */
    function getReturnsList();


    /**
     * @param array $processedReturns
     * @return mixed
     */
    function notifyProcessedReturns($processedReturns);

    /**
     * @param $failedReturns
     * @return mixed
     */
    function notifyFailedReturns($failedReturns);

    /**
     * @param $serviceType
     * @return mixed
     */
    function checkServiceAvailability($serviceType);
    
    /**
     * @return mixed
     */
    function getWeSupplyAllowedCountries();
}
