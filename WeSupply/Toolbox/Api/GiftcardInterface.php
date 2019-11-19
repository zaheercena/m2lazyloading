<?php


namespace WeSupply\Toolbox\Api;

interface GiftcardInterface{

    function initData();

    function createAndDeliverGiftCard($giftCardAmount, $customerEmail, $customerName,$websiteId = 1);

    function getGeneratedCode();
}