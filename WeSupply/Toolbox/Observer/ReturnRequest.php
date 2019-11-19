<?php

namespace WeSupply\Toolbox\Observer;

use Magento\Framework\Event\ObserverInterface;


class ReturnRequest implements ObserverInterface
{


    /**
     * @var \WeSupply\Toolbox\Api\ReturnsInterface
     */
    private $returnsInterface;

    /**
     * @var \WeSupply\Toolbox\Api\WeSupplyApiInterface
     */
    private $weSupplyApiInterface;

    /**
     * @var \WeSupply\Toolbox\Helper\Data
     */
    private $helper;

    /**
     * @var \WeSupply\Toolbox\Api\Data\ReturnslistInterface
     */
    private $returnslist;

    /**
     * @var \Magento\Framework\App\ResourceConnection
     */
    protected $resource;

    /**
     * @var \Magento\Framework\DB\Adapter\AdapterInterface
     */
    protected $connection;

    /**
     * @var \WeSupply\Toolbox\Api\GiftcardInterface
     */
    protected $giftcard;


    /**
     * @var \WeSupply\Toolbox\Logger\Logger
     */
    protected $logger;


    /**
     * ReturnRequest constructor.
     * @param \Magento\Framework\App\Action\Context $context
     * @param \WeSupply\Toolbox\Helper\Data $helper
     * @param \WeSupply\Toolbox\Api\ReturnsInterface $returnsInterface
     * @param \WeSupply\Toolbox\Api\WeSupplyApiInterface $weSupplyApi
     * @param \WeSupply\Toolbox\Api\Data\ReturnslistInterface $returnslist
     * @param \Magento\Framework\App\ResourceConnection $resourceConnection
     * @param \WeSupply\Toolbox\Api\GiftcardInterface $giftcard
     * @param \WeSupply\Toolbox\Logger\Logger $logger\
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \WeSupply\Toolbox\Helper\Data $helper,
        \WeSupply\Toolbox\Api\ReturnsInterface $returnsInterface,
        \WeSupply\Toolbox\Api\WeSupplyApiInterface $weSupplyApi,
        \WeSupply\Toolbox\Api\Data\ReturnslistInterface $returnslist,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \WeSupply\Toolbox\Api\GiftcardInterface $giftcard,
        \WeSupply\Toolbox\Logger\Logger $logger

    )
    {
        //parent::__construct($context);
        $this->helper = $helper;
        $this->returnsInterface = $returnsInterface;
        $this->weSupplyApiInterface = $weSupplyApi;
        $this->returnslist = $returnslist;

        $this->resource = $resourceConnection;
        $this->connection = $resourceConnection->getConnection();

        $this->giftcard = $giftcard;
        $this->logger = $logger;

    }


    /**
     * @param \Magento\Framework\Event\Observer $observer
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        if($this->helper->getWeSupplyEnabled()) {

            $returnsList = $this->getOrderReturns();
            if (is_array($returnsList) && count($returnsList) > 0) {
                $this->returnsInterface->processReturnsList($returnsList);
                $processedReturns = $this->returnsInterface->getProcessedReturns();
                $notProcessedReturns = $this->returnsInterface->getNotProcessedReturns();


                if (count($processedReturns) > 0) {
                    $this->notifyProcessedReturns($processedReturns);
                    $this->saveProcessedReturns($processedReturns);
                }

                if(count($notProcessedReturns) > 0){
                    $this->notifyFailureReturns($notProcessedReturns);
                }
            }
        }
    }


    /**
     * @return mixed
     */
    public function getOrderReturns()
    {

        $apiPath = $this->helper->getWeSupplySubDomain() . '.' . $this->helper->getWeSupplyDomain() . '/api/';
        $this->weSupplyApiInterface->setProtocol($this->helper->getProtocol());
        $this->weSupplyApiInterface->setApiPath($apiPath);
        $this->weSupplyApiInterface->setApiClientId($this->helper->getWeSupplyApiClientId());
        $this->weSupplyApiInterface->setApiClientSecret($this->helper->getWeSupplyApiClientSecret());

        return $this->weSupplyApiInterface->getReturnsList();

    }

    /**
     * @param $processedReturns
     */
    public function notifyProcessedReturns($processedReturns){

        $this->weSupplyApiInterface->notifyProcessedReturns($processedReturns);
    }


    /**
     * @param $notProcessedReturns
     */
    public function notifyFailureReturns($notProcessedReturns){

        $this->weSupplyApiInterface->notifyFailedReturns($notProcessedReturns);
    }


    /**
     * @param $processedReturns
     */
    private function saveProcessedReturns($processedReturns){

        $processedToSave = array();
        foreach ($processedReturns as $returnId=>$data){

            $processedToSave[] = ['return_id' => $returnId];
        }

        $tableName = $this->returnslist->getResource()->getMainTable();
        $this->insertMultiple($tableName, $processedToSave);
    }


    /**
     * @param $table
     * @param $data
     * @return int
     */
    private function insertMultiple($table, $data)
    {
        try {
            $tableName = $this->resource->getTableName($table);
            return $this->connection->insertMultiple($tableName, $data);
        } catch (\Exception $e) {
            $this->logger->error('Wesupply saving returns to database error : '.$e->getMessage());
        }
    }

}





