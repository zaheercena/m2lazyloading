<?php
namespace WeSupply\Toolbox\Model;


use WeSupply\Toolbox\Api\GiftcardInterface;
use WeSupply\Toolbox\Api\ReturnsInterface;

class Returns implements ReturnsInterface
{

    /**
     * @var \Magento\Sales\Model\Order\Email\Sender\CreditmemoSender
     */
    private $creditmemoSender;

    /**
     * @var \Magento\Sales\Controller\Adminhtml\Order\CreditmemoLoader
     */
    private $creditmemoLoader;

    /**
     * @var \Magento\Sales\Model\OrderRepository
     */
    private $orderRepository;

    /**
     * @var \Magento\Framework\Registry
     */
    private $registry;

    /**
     * @var \Magento\Sales\Model\Order\Invoice
     */
    private $invoice;

    /**
     * @var \Magento\Sales\Api\CreditmemoManagementInterface
     */
    private $creditmemoManagement;

    /**
     * @var array
     */
    private $processedReturns = [];


    private $successMessage = '';

    /**
     * @var array
     */
    private $notProcessedReturns = [];

    /**
     * @var ResourceModel\Returnslist\Collection
     */
    private $returnslistCollection;

    /**
     * @var \Magento\Framework\App\ProductMetadataInterface
     */
    private $productMetadata;

    /**
     * @var \WeSupply\Toolbox\Logger\Logger
     */
    private $logger;

    /**
     * @var GiftcardInterface
     */
    private $giftcardInterface;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $storeManager;

    private $isEnterprise = FALSE;


    /**
     * Returns constructor.
     * @param \Magento\Sales\Model\Order\Email\Sender\CreditmemoSender $creditmemoSender
     * @param \Magento\Sales\Controller\Adminhtml\Order\CreditmemoLoader $creditmemoLoader
     * @param \Magento\Sales\Model\OrderRepository $orderRepository
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Sales\Model\Order\Invoice $invoice
     * @param \Magento\Sales\Api\CreditmemoManagementInterface $creditmemoManagement
     * @param ResourceModel\Returnslist\Collection $returnslistCollection
     * @param GiftcardInterface $giftcardInterface
     * @param \Magento\Framework\App\ProductMetadataInterface $productMetadata
     * @param \WeSupply\Toolbox\Logger\Logger $logger
     */
    public function __construct(
        \Magento\Sales\Model\Order\Email\Sender\CreditmemoSender $creditmemoSender,
        \Magento\Sales\Controller\Adminhtml\Order\CreditmemoLoader $creditmemoLoader,
        \Magento\Sales\Model\OrderRepository $orderRepository,
        \Magento\Framework\Registry $registry,
        \Magento\Sales\Model\Order\Invoice $invoice,
        \Magento\Sales\Api\CreditmemoManagementInterface $creditmemoManagement,
        \WeSupply\Toolbox\Model\ResourceModel\Returnslist\Collection $returnslistCollection,
        \WeSupply\Toolbox\Api\GiftcardInterface $giftcardInterface,
        \Magento\Framework\App\ProductMetadataInterface $productMetadata,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \WeSupply\Toolbox\Logger\Logger $logger
    )
    {
        $this->creditmemoSender = $creditmemoSender;
        $this->creditmemoLoader = $creditmemoLoader;
        $this->orderRepository = $orderRepository;
        $this->invoice = $invoice;
        $this->creditmemoManagement = $creditmemoManagement;
        $this->registry = $registry;
        $this->returnslistCollection = $returnslistCollection;
        $this->productMetadata = $productMetadata;
        $this->giftcardInterface = $giftcardInterface;
        $this->storeManager = $storeManager;
        $this->logger = $logger;

        $this->checkEnterprise();
    }


    /**
     * @param array $returnsList
     * @return mixed|void
     */
    public function processReturnsList($returnsList)
    {
        foreach ($returnsList as $key => $return) {

            /** reseting the success message */
            $this->successMessage = '';

            $orderId = '';
            $returnId = '';
            //by default, we are doing offline refund 1 = true
            $doOffline = 1;
            $returnReason = '';
            $shippingAmount = 0;
            $returnItems = [];
            $sendEmail = false;
            $adjustmentPositive = 0;
            $adjustmentNegative = 0;
            $storeCreditAmount = 0;
            $giftCardAmount = 0;

            if (isset($return['return_number'])) {
                $returnId = $return['return_number'];
            }

            if (empty($returnId)) {
                continue;
            } else {
                $alreadyProcessed = $this->returnWasProcessed($returnId);

                if ($alreadyProcessed) {
                    $this->logger->error('Return id ' . $returnId . ' already processed');
                    continue;
                }
            }

            if (isset($return['order'])) {
                $orderId = preg_replace("/^" . OrderInfoBuilder::PREFIX . '/', '', $return['order']['number_int']);
            }

            if (isset($return['return_comment'])) {

                $returnReason .= !empty($return['return_comment']) ? 'Return Comment: ' . $return['return_comment'] . PHP_EOL . PHP_EOL : '';
            }


            if (isset($return['refund_control'])) {

                if (isset($return['refund_control']['refund_shipping_fee_value'])) {
                    $shippingAmount = $return['refund_control']['refund_shipping_fee_value'];
                }

                if (isset($return['refund_control']['refund_cost_value'])) {
                    $adjustmentNegative = $return['refund_control']['refund_cost_value'];
                }

                if (isset($return['refund_control']['refund_type']['type'])) {

                    /** if not enterprise version, we can only have online or offline refund */
                    if(!$this->isEnterprise){

                        if ($return['refund_control']['refund_type']['type'] === 'refund') {  // online refund
                            $doOffline = 0;
                        }else{   // offline refund
                            $doOffline = 1;
                        }

                       /**  if enterprise version, we cah have multiple refund types */
                    }else{

                        switch($return['refund_control']['refund_type']['type']){
                            case 'refund' :  //online
                                    $doOffline = 0;
                                    break;

                            case 'credit' :   // offline with store credit
                                    $doOffline = 1;
                                    $storeCreditAmount = $return['refund_control']['refund_type']['total_value'];
                                    break;


                            case 'gift_card' :     // offline with auto generated gift card
                                $doOffline = 1;
                                $giftCardAmount = $return['refund_control']['refund_type']['total_value'];
                                break;

                            case 'offline' :     // offline
                                $doOffline = 1;
                                break;
                        }


                    }


                }
            }

            if (isset($return['items'])) {

                foreach ($return['items'] as $itemKey => $itemData) {

                    $returnItems[$itemData['id']] = [
                        'qty' => $itemData['qty'],
                        'back_to_stock' => $itemData['restock_item']
                    ];

                    $returnReason .= 'SKU: ' . $itemData['sku'] . ' ---- ' . $itemData['return_reason'] . PHP_EOL;
                }
            }


            $memoCreated = $this->createCreditMemo($returnId, $orderId, $doOffline, $shippingAmount, $adjustmentPositive, $adjustmentNegative, $returnReason, $sendEmail, $returnItems, $storeCreditAmount, $giftCardAmount);

            if ($memoCreated) {
                $this->processedReturns[$returnId] = ['message' => $this->successMessage] ;
            }
        }
    }


    /**
     * @return array|mixed
     */
    public function getProcessedReturns()
    {
        return $this->processedReturns;
    }


    public function getNotProcessedReturns()
    {
        return $this->notProcessedReturns;
    }


    /**
     * @param $returnId
     * @param $orderId
     * @param int $doOffline
     * @param int $shippingAmount
     * @param int $adjustmentPositive
     * @param int $adjustmentNegative
     * @param string $customerComments
     * @param int $sendEmail
     * @param $items
     * @param int $storeCreditAmount
     * @return bool
     */
    private function createCreditMemo($returnId, $orderId, $doOffline = 0, $shippingAmount = 0, $adjustmentPositive = 0, $adjustmentNegative = 0, $customerComments = '', $sendEmail = 0, $items, $storeCreditAmount = 0, $giftCardAmount = 0)
    {

        $this->registry->unregister('current_creditmemo');

        try {
            $order = $this->orderRepository->get($orderId);
        } catch (\Exception $e) {
            $this->logger->error('Return Id ' . $returnId . ' with order id ' . $orderId . ' message :' . $e->getMessage());
            $this->notProcessedReturns[$returnId] = ['message' => $e->getMessage()];
            return FALSE;
        }


        $invoiceincrementid = '';

        $invoices = $order->getInvoiceCollection();
        foreach ($invoices as $invoice) {
            $invoiceincrementid = $invoice->getIncrementId();
        }

        if (empty($invoiceincrementid)) {
            $this->logger->error('Return Id ' . $returnId . ' with order id ' . $orderId . ' Invoice not found!');
            $this->notProcessedReturns[$returnId] = ['message' => 'Invoice not found'];
            return FALSE;
        }

        $invoiceobj = $this->invoice->loadByIncrementId($invoiceincrementid);

        $creditMemoData = [];
        $creditMemoData['do_offline'] = $doOffline;
        $creditMemoData['shipping_amount'] = $shippingAmount;
        $creditMemoData['adjustment_positive'] = $adjustmentPositive;
        $creditMemoData['adjustment_negative'] = $adjustmentNegative;
        $creditMemoData['comment_text'] = $customerComments;
        $creditMemoData['send_email'] = $sendEmail;


        if ($storeCreditAmount > 0) {
            $creditMemoData['refund_customerbalance_return'] = $storeCreditAmount;
            $creditMemoData['refund_customerbalance_return_enable'] = 1;


        }


        foreach ($items as $itemId => $itemData) {
            $itemToCredit[$itemId] = ['qty' => $itemData['qty'], 'back_to_stock' => $itemData['back_to_stock']];
            $creditMemoData['items'] = $itemToCredit;
        }

        try {


            $this->creditmemoLoader->setOrderId($orderId); //pass order id
            $this->creditmemoLoader->setCreditmemo($creditMemoData);

            $creditmemo = $this->creditmemoLoader->load();
            if ($creditmemo) {
                if (!$creditmemo->isValidGrandTotal()) {
                    throw new \Magento\Framework\Exception\LocalizedException(
                        __('The credit memo\'s total must be positive.')
                    );
                }


                if (!empty($creditMemoData['comment_text'])) {
                    $creditmemo->addComment(
                        $creditMemoData['comment_text'],
                        isset($creditMemoData['comment_customer_notify']),
                        isset($creditMemoData['is_visible_on_front'])
                    );

                    $creditmemo->setCustomerNote($creditMemoData['comment_text']);
                    $creditmemo->setCustomerNoteNotify(isset($creditMemoData['comment_customer_notify']));
                }


                $creditmemo->getOrder()->setCustomerNoteNotify(!empty($creditMemoData['send_email']));

                $creditmemo->setInvoice($invoiceobj);

                // false means do online
                $cm = $this->creditmemoManagement->refund($creditmemo, (bool)$creditMemoData['do_offline']);

                if (!empty($creditMemoData['send_email'])) {
                    $this->creditmemoSender->send($creditmemo);
                }

                $this->successMessage = 'Refund Successfull';

                if($giftCardAmount > 0){
                    $this->generateGiftCard($orderId, $cm, $giftCardAmount);
                }



            } else {

                $this->notProcessedReturns[$returnId] = ['message' => 'Credit memo not created.'];
                $this->logger->error('Return Id ' . $returnId . ' with order id ' . $orderId . ' Credit memo not created.');
                return FALSE;
            }
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->notProcessedReturns[$returnId] = ['message' => $e->getMessage()];
            $this->logger->error('Return Id ' . $returnId . ' with order id ' . $orderId . ' message :' . $e->getMessage());
            return FALSE;
        } catch (\Exception $e) {
            $this->notProcessedReturns[$returnId] = ['message' => $e->getMessage()];
            $this->logger->error('Return Id ' . $returnId . ' with order id ' . $orderId . ' message :' . $e->getMessage());
            return FALSE;

        }

        return TRUE;

    }


    private function generateGiftCard($orderId, $cm, $giftCardAmount)
    {
        try {
            $order = $cm->getOrder();
            $orderHistoryComment = '';
            $customerEmail = $order->getCustomerEmail();
            $customerName = $order->getCustomerFirstname() . ' ' . $order->getCustomerLastname();
            $storeId = $order->getStoreId();
            $websiteId = 1;
            if ($storeId) {
                try {
                    $websiteId = $this->storeManager->getStore($storeId)->getWebsiteId();
                } catch (\Exception $e) {
                    $this->logger->error('Error when trying to get Wesite Id for order ' . $order->getId() . ' cm: ' . $cm->getIncrementId() . ' with message ' . $e->getMessage());
                    return;
                }
            }

            $result = $this->giftcardInterface->createAndDeliverGiftCard($giftCardAmount, $customerEmail, $customerName, $websiteId);
            $giftCardCode = $this->giftcardInterface->getGeneratedCode();

            if ($result) {
                $orderHistoryComment .= PHP_EOL . '<br/>Credit memo id: ' . $cm->getIncrementId() . '  Gift card Code ' . $giftCardCode . ' with amount ' . $giftCardAmount . ' succesfully generated and sent to customer';
            } else {
                if ($giftCardCode) {
                    $orderHistoryComment .= PHP_EOL . '<br/>Credit memo id: ' . $cm->getIncrementId() . ' Gift card Code ' . $giftCardCode . ' with amount ' . $giftCardAmount . ' succesfully generated but not sent to customer';
                } else {
                    $orderHistoryComment .= PHP_EOL . '<br/>Credit memo id: ' . $cm->getIncrementId() . ' We could not generate a gift card  with amount ' . $giftCardAmount;
                }
            }


            $order->addStatusHistoryComment($orderHistoryComment);
            $order->save();
            $this->successMessage = $orderHistoryComment;
        }catch(\Exception $e){
            $this->logger->error('Error when creating gift card for Credit memo  Id ' . $cm->getIncrementId() . ' with order id ' . $orderId . ' message :' . $e->getMessage());
        }

    }


    /**
     * @param $returnId
     * @return bool
     */
    private function returnWasProcessed($returnId)
    {

        $count = $this->returnslistCollection->countReturns($returnId);
        if ($count > 0) {
            return TRUE;
        }

        return FALSE;
    }


    private function checkEnterprise()
    {
        $edition = $this->productMetadata->getEdition();

        if($edition === 'Enterprise'){
            $this->isEnterprise = TRUE;
        }

    }

}