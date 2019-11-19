<?php
    
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace WeSupply\Toolbox\Observer;

use Magento\Cms\Model\Page;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseFactory;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Layout;
use WeSupply\Toolbox\Helper\Data as Helper;

/**
 * Class CmsPageView
 * @package WeSupply\Toolbox\Observer
 */

class CmsPageView implements ObserverInterface
{
    /**
     * @var UrlInterface
     */
    protected $_urlInterface;
    
    /**
     * @var ResponseFactory
     */
    protected $_responseFactory;
    
    /**
     * @var RequestInterface
     */
    protected $_request;
    
    /**
     * @var Page
     */
    protected $_page;
    
    /**
     * @var Helper
     */
    protected $_helper;

    /**
     * @var Layout
     */
    protected $layout;

    /**
     * CmsPageView constructor.
     * @param UrlInterface $urlInterface
     * @param ResponseFactory $responseFactory
     * @param RequestInterface $request
     * @param Layout $layout
     * @param Page $page
     * @param Helper $helper
     */
    public function __construct(
        UrlInterface $urlInterface,
        ResponseFactory $responseFactory,
        RequestInterface $request,
        Layout $layout,
        Page $page,
        Helper $helper
    ) {
        $this->_urlInterface = $urlInterface;
        $this->_responseFactory = $responseFactory;
        $this->_request = $request;
        $this->layout = $layout;
        $this->_page = $page;
        $this->_helper = $helper;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     * @return $this|void
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        if (
            $this->_request->getRouteName() == 'cms' &&
            $this->_request->getControllerName() == 'page' &&
            $this->_request->getActionName() == 'view'
        ) {
            if ( // exit if is not a store cms page
                $this->isStorePage($this->_helper->getStoreLocatorIdentifier()) === false &&
                $this->isStorePage($this->_helper->getStoreDetailsIdentifier()) === false
            ) {
                return $this;
            }
            if (!$this->_helper->getWeSupplyEnabled()) { // load 404 if module is disabled
                $this->_page->setContent($this->setNotFound());
                return $this;
            }
            $storeParams = '';
            if ($this->isStorePage($this->_helper->getStoreDetailsIdentifier()) !== false) {
                /**
                 * store-details page of wesupply expects exactly 5 store params
                 * show 'not found page' if store param is not set or it does not have all the required parts
                 */
                $params = $this->_request->getParams();
                if (!isset($params['store']) || !$this->hasAllParams($params['store'])) {
                    $this->_page->setContent($this->setNotFound());
                    return $this;
                }
                $storeParams = '/' . $params['store'];
            }
            
            $this->_page->setContent($this->insertIframe($this->buildIframe($storeParams)));
        }
        
        return $this;
    }
    
    /**
     * @param $storeParams
     * @return bool
     */
    private function hasAllParams($storeParams)
    {
        $storeParamsArr = explode('/', $storeParams);
        if (count($storeParamsArr) != 5) {
            return false;
        }
        
        return true;
    }
    
    /**
     * @param $iframe
     * @return string
     */
    private function insertIframe($iframe)
    {
        $needle = '<div class="embedded-iframe-container">';
        $pos = strpos($this->_page->getContent(), $needle);
        if ($pos !== false) {
            return substr_replace($this->_page->getContent(), $iframe, $pos + strlen($needle), 0);
        }
        
        return $iframe;
    }

    /**
     * @param $storeParams
     * @return string
     */
    private function buildIframe($storeParams)
    {
        $iframe = '';
        
        $iframe .= '<iframe class="embedded-iframe" style="width: 100%" width="100%" frameborder="0" allowfullscreen allow="geolocation" scrolling="yes" ';
        $iframe .= 'src="' . $this->buildIframeUrl($storeParams) . '">';
        $iframe .= '</iframe>';
        
        return $iframe;
    }

    /**
     * @param string $storeParams
     * @return string
     */
    private function buildIframeUrl($storeParams)
    {
        $iframeUrl  = $this->_helper->getWesupplyFullDomain();
        $iframeUrl .= $this->isStorePage($this->_helper->getStoreLocatorIdentifier()) !== false ? 'stores-map' : 'store-details';
        $iframeUrl .= $storeParams . '?platformType=' . $this->_helper->getPlatform();

        return $iframeUrl;
    }

    /**
     * @param $identifier
     * @return false|int
     */
    private function findInPath($identifier)
    {
        $currentPage = $this->_page->getIdentifier();
        if (strpos($currentPage, $identifier) === false) {
            return strpos($identifier, $currentPage);
        }

        return strpos($currentPage, $identifier);
    }

    /**
     * @param $identifier
     * @return bool
     */
    private function isStorePage($identifier)
    {
        return $this->_page->getIdentifier() == $identifier;
    }

    /**
     * @return mixed
     */
    private function setNotFound()
    {
        return $this->layout
            ->createBlock('Magento\Framework\View\Element\Template')
            ->setPageTitle($this->_page->getTitle())
            ->setTemplate('WeSupply_Toolbox::embedded/not_found.phtml')
            ->toHtml();
    }
}
