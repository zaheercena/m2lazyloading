<?php
/**
 * Created by PhpStorm.
 * User: adminuser
 * Date: 14.06.2019
 * Time: 11:32
 */

namespace WeSupply\Toolbox\Model;

use WeSupply\Toolbox\Api\GiftcardInterface;

class Giftcard implements GiftcardInterface
{


    private $giftCardAccountInterface = NULL;

    private $giftCardAccountRepository = NULL;

    private $emailManagement = NULL;

    private $objectManager;

    private $logger;

    private $generatedCode;


    public function __construct(
        \WeSupply\Toolbox\Logger\Logger $logger
    )
    {
       $this->objectManager = \Magento\Framework\App\ObjectManager::getInstance();
       $this->logger = $logger;
    }

    public function initData()
    {

        if(is_null($this->giftCardAccountInterface)){
            $this->giftCardAccountInterface = $this->objectManager->create(\Magento\GiftCardAccount\Api\Data\GiftCardAccountInterface::class);
        }

        if(is_null($this->giftCardAccountRepository))
        {
            $this->giftCardAccountRepository = $this->objectManager->create(\Magento\GiftCardAccount\Api\GiftCardAccountRepositoryInterface::class);
        }

        if(is_null($this->emailManagement)){
            $this->emailManagement = $this->objectManager->create('Magento\GiftCardAccount\Model\EmailManagement');
        }

    }


    public function createAndDeliverGiftCard($giftCardAmount, $customerEmail, $customerName, $websiteId = 1)
    {

        $this->initData();

        $expirationDate = date('Y-m-d', strtotime('+1 year'));

        $card = array();
        $card['website_id'] = $websiteId;
        $card['balance'] = $giftCardAmount;
        $card['date_expires'] = $expirationDate;
        $card['status'] = 1;
        $card['is_redeemable'] = 1;
        $card['recipient_email'] = $customerEmail;
        $card['recipient_name'] = $customerName;

        try {

            $this->giftCardAccountInterface->setData($card);
            $this->giftCardAccountRepository->save($this->giftCardAccountInterface);
            $this->generatedCode = $this->giftCardAccountInterface->getCode();
            $emailSent = $this->emailManagement->sendEmail($this->giftCardAccountInterface);
            return $emailSent;

        }catch(\Exception $e){
            $this->logger->error('Error when trying to create gift card refund with message: '.$e->getMessage());
            return FALSE;
        }

    }


    public function getGeneratedCode(){
        return $this->generatedCode;
    }

}