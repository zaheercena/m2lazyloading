<?php

/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
    
namespace WeSupply\Toolbox\Block\Estimations;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\Template;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use WeSupply\Toolbox\Helper\Estimates as EstimatesHelper;
use WeSupply\Toolbox\Api\WeSupplyApiInterface;
use WeSupply\Toolbox\Helper\Data as Helper;
use WeSupply\Toolbox\Logger\Logger as Logger;

/**
 * Class Delivery
 * @package WeSupply\Toolbox\Block\Estimations
 */

class Delivery extends Template
{
    /**
     * @var ProductRepositoryInterface
     */
    protected $productRepository;
    
    /**
     * @var EstimatesHelper
     */
    protected $estimatesHelper;
    
    /**
     * @var Helper
     */
    protected $helper;
    
    /**
     * @var Logger
     */
    protected $logger;
    
    /**
     * @var $params
     */
    private $request;
    
    /**
     * @var $params
     */
    private $params;
    
    /**
     * @var WeSupplyApiInterface
     */
    private $weSupplyApi;
    
    /**
     * @var $product
     * currently loaded or selected product
     */
    private $product;
    
    /**
     * @var $configParent
     * the config parent of selected associated simple
     */
    private $configParent;
    
    /**
     * @var bool
     * flag to identify estimates request for multiple products
     */
    private $multiple = FALSE;
    
    /**
     * Delivery constructor.
     * @param Template\Context $context
     * @param ProductRepositoryInterface $productRepository
     * @param EstimatesHelper $estimatesHelper
     * @param WeSupplyApiInterface $weSupplyApi
     * @param Logger $logger
     * @param Helper $helper
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        ProductRepositoryInterface $productRepository,
        EstimatesHelper $estimatesHelper,
        WeSupplyApiInterface $weSupplyApi,
        Helper $helper,
        Logger $logger,
        array $data = []
    )
    {
        $this->request = $context->getRequest();
        
        $this->productRepository = $productRepository;
        $this->estimatesHelper = $estimatesHelper;
        $this->weSupplyApi = $weSupplyApi;
        $this->helper = $helper;
        $this->logger = $logger;
        
        parent::__construct($context, $data);
    }
    
    /**
     * @param array $products
     * @return mixed
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getEstimations($products = [])
    {
        $requestParams = $this->buildApiRequestParams($products);
        
        $this->setApiConnectionDetails();
        $response = $this->weSupplyApi->getDeliveryEstimations($requestParams, $this->multiple);
        
        return $response;
    }
    
    /**
     * @param array $products
     * @return array
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function buildApiRequestParams($products = [])
    {
        $this->configParent = null;
        $requestParams = $this->estimatesHelper->buildApiRequestCommonParams();
        
        if (!$products) {
            $this->prepareSingleProduct($requestParams);
            
            return $requestParams;
        }
        
        $this->prepareMultipleProducts($requestParams, $products);
        
        return $requestParams ?? [];
    }
    
    /**
     * Remove empty estimates of multiple products request
     * @param $response
     * @return mixed
     */
    public function removeEmptyEstimates(&$response)
    {
        if (!isset($response['estimates'])) {
            // something went wrong - check log files
            $response['estimates'] = [];
            return $response;
        }
        
        foreach ($response['estimates'] as $shipper => $methods) {
            if (!$methods['methods']) {
                unset($response['estimates'][$shipper]);
                continue;
            }
            
            foreach ($methods['methods'] as $code => $estimates) {
                $canBeRemoved = 0;
                $productsCount = count($estimates['estimated_delivery_date']);
                foreach ($estimates['estimated_delivery_date'] as $productId => $estimation) {
                    if ($estimation == '-') {
                        unset($response['estimates'][$shipper]['methods'][$code]['estimated_delivery_date'][$productId]);
                        $canBeRemoved++;
                        continue;
                    }
                }
                
                if ($canBeRemoved == $productsCount) {
                    unset($response['estimates'][$shipper]['methods'][$code]);
                    continue;
                }
            }
        }
        
        return $response;
    }
    
    /**
     * @param $requestParams
     * @return bool
     * @throws NoSuchEntityException
     */
    private function prepareSingleProduct(&$requestParams)
    {
        $this->loadSingleProduct();
    
        if (!$this->product) {
            $this->logger->addError('Requested product not found!');
            return false;
        }
    
        $requestParams['product'] = $this->estimatesHelper->buildApiRequestProductParams($this->product, $this->configParent);
    
        return $requestParams;
    }
    
    /**
     * @param $requestParams
     * @param $products
     * @return mixed
     * @throws NoSuchEntityException
     */
    private function prepareMultipleProducts(&$requestParams, $products)
    {
        $this->setIsMultiple();
        $requestParams['products'] = [];
        foreach ($products as $item) {
            if ($item->getProductType() == 'configurable') {
                $this->configParent = $this->productRepository->getById($item->getProductId());
                continue;
            }
    
            $this->product = $this->productRepository->getById($item->getProductId());
            $requestParams['products'][$item->getProductId()] = $this->estimatesHelper->buildApiRequestProductParams($this->product, $this->configParent);
        
            $this->configParent = null;
        }
    
        $requestParams['shippers'] = $this->estimatesHelper->buildApiRequestShippingParams();
        
        return $requestParams;
    }
    
    /**
     * Get details of currently loaded product and details of the simple associated product
     * that is selected from the options of configurable product
     * @return $this
     * @throws NoSuchEntityException
     */
    private function loadSingleProduct()
    {
        $this->params = $this->request->getParams();
        $this->product = $this->productRepository->getById($this->params['product_id']);
        
        if ($this->product->getTypeId() == 'configurable') {
            $this->configParent = $this->product;
            $this->product = $this->productRepository->getById($this->params['selected_product']);
        }
        
        return $this;
    }
    
    /**
     * Set flag for multiple products estimations
     */
    private function setIsMultiple()
    {
        $this->multiple = TRUE;
    }
    
    /**
     * Set WeSupply API credentials
     * @return $this
     */
    private function setApiConnectionDetails()
    {
        $this->weSupplyApi->setProtocol($this->helper->getProtocol());
        $this->weSupplyApi->setApiPath($this->getApiPath());
        $this->weSupplyApi->setApiClientId($this->helper->getWeSupplyApiClientId());
        $this->weSupplyApi->setApiClientSecret($this->helper->getWeSupplyApiClientSecret());
        
        return $this;
    }
    
    /**
     * @return string
     */
    private function getApiPath()
    {
        return $this->helper->getWeSupplySubDomain() . '.' . $this->helper->getWeSupplyDomain() . '/api/';
    }
}