<?php

namespace WeSupply\Toolbox\Block;

use Magento\Store\Model\StoreManagerInterface as StoreManager;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\View\Element\Template;
use WeSupply\Toolbox\Helper\Data as Helper;
use Magento\Framework\App\Response\Http;

class Orders extends Template
{
    /**
     * @var SessionManagerInterface
     */
    protected $_session;

    /**
     * @var Helper
     */
    protected $_helper;

    /**
     * @var Http
     */
    protected $_response;

    /**
     * @var StoreManager
     */
    protected $_storeManager;

    /**
     * Orders constructor.
     * @param Context $context
     * @param Helper $helper
     * @param StoreManager $storeManager
     * @param SessionManagerInterface $session
     * @param Http $response
     */
    public function __construct(
        Context $context,
        Helper $helper,
        StoreManager $storeManager,
        SessionManagerInterface $session,
        Http $response
    )
    {
        $this->_isScopePrivate = true;

        $this->_helper = $helper;
        $this->_storeManager = $storeManager;
        $this->_session = $session;
        $this->_response = $response;
        parent::__construct($context);
    }

    /**
     * @return Http|\Magento\Framework\App\Response\HttpInterface|string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getIframeUrl()
    {
        $wesupplyUrl = $this->_helper->getWesupplyFullDomain();

        if ($this->isFirstLoginAttempt()) {

            if (!$this->getAuthSearchBy()) {
                return $this->_response->setRedirect($this->_helper->getTrackingInfoPageUrl());
            }

            $searchByKey = (array_keys($this->getAuthSearchBy()))[0];
            $searchByVal = ($this->getAuthSearchBy())[$searchByKey];

            return $wesupplyUrl . '?token=' . $this->getAuthToken() . '&' . $searchByKey . '=' . $searchByVal . '&platformType=' . $this->_helper->getPlatform();
        }

        return $wesupplyUrl . 'account/orders?platformType=' . $this->_helper->getPlatform();
    }

    /**
     * @return bool
     */
    private function isFirstLoginAttempt()
    {
        if ($this->_session->getFirstAttempt()) {
            $this->_session->unsFirstAttempt();

            return true;
        }

        return false;
    }

    /**
     * @return mixed
     */
    private function getAuthToken()
    {
        return $this->_session->getSessionAuthToken();
    }

    /**
     * @return mixed
     */
    private function getAuthSearchBy()
    {
        return $this->_session->getSessionAuthSearchBy();
    }
}