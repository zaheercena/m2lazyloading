<?php

namespace WeSupply\Toolbox\Controller\Orders;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\Session\SessionManagerInterface;
use WeSupply\Toolbox\Helper\Data as Helper;

class View extends Action
{
    /**
     * @var PageFactory
     */
    protected $_pageFactory;

    /**
     * @var SessionManagerInterface
     */
    protected $_session;

    /**
     * @var Helper
     */
    protected $_helper;

    /**
     * View constructor.
     * @param Context $context
     * @param Helper $helper
     * @param PageFactory $pageFactory
     * @param SessionManagerInterface $session
     */
    public function __construct(
        Context $context,
        Helper $helper,
        PageFactory $pageFactory,
        SessionManagerInterface $session
    )
    {
        $this->_helper = $helper;
        $this->_pageFactory = $pageFactory;
        $this->_session = $session;

        parent::__construct($context);
    }

    /**
     * @return ResponseInterface|ResultInterface|Page
     */
    public function execute()
    {
        if (!$this->_session->getSessionAuthToken()) {
            return $this->_redirect($this->_helper->getTrackingInfoUri());
        }

        // load orders iframe
        $resultPage = $this->_pageFactory->create();
        $resultPage->getConfig()->getTitle()->set(__('Orders View'));

        return $resultPage;
    }
}