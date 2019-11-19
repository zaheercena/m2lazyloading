<?php
namespace WeSupply\Toolbox\Controller\Order;

use Magento\Framework\App\Response\Http;

class Fetch extends \Magento\Framework\App\Action\Action
{

    const  ALL_STORES = 'all';

    const MULTIPLE_STORE_ID_DELIMITER = ',';
    /**
     * maximum response xml file size allowed - expressed in MB
     */
    const MAX_FILE_SIZE_ALLOWED = '30';
    /**
     * @var string
     */
    protected $guid;

    /**
     * @var string
     */
    protected $startDate;

    /**
     * @var string
     */
    protected $endDate;

    /**
     * @var string
     */
    protected $storeIds;

    /**
     * @var \WeSupply\Toolbox\Helper\Data
     */
    protected $helper;

    /**
     * @var \Magento\Framework\Api\SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;

    /**
     * @var \Magento\Framework\Api\SortOrderBuilder
     */
    protected $sortOrderBuilder;

    /**
     * @var \WeSupply\Toolbox\Api\OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * Fetch constructor.
     * @param \Magento\Framework\App\Action\Context $context
     * @param \WeSupply\Toolbox\Helper\Data $helper
     * @param \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder
     * @param \Magento\Framework\Api\SortOrderBuilder $sortOrderBuilder
     * @param \WeSupply\Toolbox\Api\OrderRepositoryInterface $orderRepository
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \WeSupply\Toolbox\Helper\Data $helper,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        \Magento\Framework\Api\SortOrderBuilder $sortOrderBuilder,
        \WeSupply\Toolbox\Api\OrderRepositoryInterface $orderRepository
    )
    {
        parent::__construct($context);
        $this->helper = $helper;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->sortOrderBuilder = $sortOrderBuilder;
        $this->orderRepository = $orderRepository;
    }


    public function execute()
    {

        $response = '';
        $params = $this->getRequest()->getParams();
        $result = $this->_validateParams($params);

        if ($result) {
            /** Add the error response */
            //$response .= $this->addResponseStatus('true', 'ERROR', $result);
            $this->getResponse()
                ->setStatusCode(Http::STATUS_CODE_500)
                ->setContent($result);
            return;

        } else {
            /** Get the orders from the required interval */
            $xmlResponse = $this->fetchOrders();

            if(is_array($xmlResponse) && array_key_exists('error',$xmlResponse))
            {
                $errorMessage = $xmlResponse['message'] ?? 'Error';
                $this->getResponse()
                        ->setStatusCode($xmlResponse['error'])
                        ->setContent($errorMessage);
                return ;
            }else{
                $response .= $xmlResponse;
            }

            $response .= $this->addResponseStatus('false', 'SUCCESS', '');
        }

        $response = '<Orders>' . $response . '</Orders>';
        $xml = simplexml_load_string($response);  // Might be ignored this and just send the $response as result

        $this->getResponse()->setHeader('Content-type', 'text/xml; charset=utf-8');
        $this->getResponse()->setBody($xml->asXML());
    }

    protected function fetchOrders()
    {
        $ordersXml = '';
        $startDate = date('Y-m-d H:i:s', strtotime( $this->startDate));
        $endDate = date('Y-m-d H:i:s', strtotime( $this->endDate));

        $this->searchCriteriaBuilder->addFilter('updated_at', $startDate, 'gteq');
        $this->searchCriteriaBuilder->addFilter('updated_at', $endDate, 'lteq');

        /**
         * if storeId param has the all stores value, we are not filtering based on store id
         */
        if($this->storeIds <> self::ALL_STORES){
            $storeIds = array_filter(explode(SELF::MULTIPLE_STORE_ID_DELIMITER, $this->storeIds));
            $this->searchCriteriaBuilder->addFilter('store_id', $storeIds, 'in');
        }

        $this->sortOrderBuilder->setDirection(\Magento\Framework\Api\SortOrder::SORT_ASC);
        $this->sortOrderBuilder->setField('updated_at');
        $sortOrder = $this->sortOrderBuilder->create();
        $this->searchCriteriaBuilder->addSortOrder($sortOrder);

        $orders = $this->orderRepository->getList(
            $this->searchCriteriaBuilder->create()
        )->getItems();


        if (count($orders)) {
            foreach($orders as $item) {
                $orderXml = $item->getInfo();
                $ordersXml .= $orderXml;
                /**
                 * extra check for the rare cases where massive xml file sizes are created
                 */
                $xmlFileSizeBit = $this->helper->strbits($ordersXml);
                $xmlFileSize = $this->helper->formatSizeUnits($xmlFileSizeBit);
                if($xmlFileSize >= self::MAX_FILE_SIZE_ALLOWED ) {
                    return array('error' => Http::STATUS_CODE_504,
                                 'message' => 'XML File Size exceeds '.self::MAX_FILE_SIZE_ALLOWED
                        );
                }

            }
        }

        return $ordersXml;
    }



    /**
     * @param string $hasError
     * @param string $errorCode
     * @param string $errorDescription
     * @return string
     */
    protected function addResponseStatus($hasError, $errorCode, $errorDescription)
    {
        return "<Response>" .
            "<ResponseHasErrors>$hasError</ResponseHasErrors>" .
            "<ResponseCode>$errorCode</ResponseCode>" .
            "<ResponseDescription>$errorDescription</ResponseDescription>"
            . "</Response>";
    }

    /**
     * @param $params
     * @return bool|\Magento\Framework\Phrase
     */
    private function _validateParams($params)
    {
        $guid = isset($params['guid']) ? $params['guid'] : false;
        $clientName = isset($params['ClientName']) ? $params['ClientName'] : false;
        $startDate = isset($params['DateStart']) ? $params['DateStart'] : false;
        $endDate = isset($params['DateEnd']) ? $params['DateEnd'] : false;
        $storeIds = isset($params['AffiliateExternalId']) ? strtolower(trim($params['AffiliateExternalId'])) : false;
        $requiredGuid = $this->helper->getGuid();
        $requiredClientName = $this->helper->getClientName();

        if (!$guid) {
            return __('guid is required. Please specify it');
        }
        if (!$clientName) {
            return __('ClientName is required. Please specify it');
        }
        if ($guid != $requiredGuid) {
            return __('guid is invalid');
        }
        if ($clientName != $requiredClientName) {
            return __('ClientName is invalid');
        }
        if (!$startDate) {
            return __('DateStart is a required field. Please specify it');
        }
        if (!$endDate) {
            return __('DateEnd is a required field. Please specify it');
        }

        if (!$storeIds) {
            return __('Affiliate External Id is a required field. Please specify it.');
        }

        $this->storeIds = $storeIds;
        $this->guid = $guid;
        $this->startDate = $startDate;
        $this->endDate = $endDate;


        return false;
    }
}
