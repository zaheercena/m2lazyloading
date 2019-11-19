<?php

namespace WeSupply\Toolbox\Controller\Autoconnect;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Setup\Exception;
use WeSupply\Toolbox\Api\Authorize;
use WeSupply\Toolbox\Helper\Data as Helper;

class Save extends Action
{
    /**
     * @var Authorize
     */
    protected $_auth;

    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var WriterInterface
     */
    protected $configWriter;

    /**
     * @var TypeListInterface
     */
    protected $cacheTypeList;

    /**
     * @var Helper
     */
    protected $_helper;

    /**
     * @var $params
     */
    private $params;

    /**
     * @var $response
     */
    protected $response;

    /**
     * Index constructor.
     * @param Context $context
     * @param WriterInterface $configWriter
     * @param TypeListInterface $cacheTypeList
     * @param Helper $helper
     * @param Authorize $authorize
     * @param JsonFactory $resultJsonFactory
     */
    public function __construct(
        Context $context,
        WriterInterface $configWriter,
        TypeListInterface $cacheTypeList,
        Helper $helper,
        Authorize $authorize,
        JsonFactory $resultJsonFactory
    )
    {
        $this->configWriter = $configWriter;
        $this->cacheTypeList = $cacheTypeList;
        $this->_helper = $helper;
        $this->_auth = $authorize;
        $this->resultJsonFactory = $resultJsonFactory;

        parent::__construct($context);
    }

    /**
     * @return ResponseInterface|\Magento\Framework\Controller\Result\Json|ResultInterface
     */
    public function execute()
    {
        $resultJson = $this->resultJsonFactory->create();

        if (!$this->validateParams()) {
            return $resultJson->setData($this->response);
        }

        if ($this->params['ClientName'] !== $this->_helper->getClientName()) {
            return $resultJson->setData($this->setError(__('WeSupply SubDomain does not match.')));
        }

        if ($this->params['guid'] !== $this->_helper->getGuid()) {
            return $resultJson->setData($this->setError(__('Access Key does not match.')));
        }

        $authResponse = $this->_auth->authorize($this->params['guid'], $this->params['connection']);
        if (!$authResponse) {
            return $resultJson->setData($this->setError($this->_auth->error));
        }

        try {
            $clientId = $this->getClientIdFromAuth($authResponse);
            $clientSecret = $this->getClientSecretFromAuth($authResponse);

            $this->setConfig('wesupply_api/integration/wesupply_enabled',  1);
//            $this->setConfig('wesupply_api/step_1/wesupply_subdomain',  $this->params['ClientName']);
            $this->setConfig('wesupply_api/step_1/wesupply_client_id', $clientId);
            $this->setConfig('wesupply_api/step_1/wesupply_client_secret', $clientSecret);

            $this->flushCache(['config','layout', 'full_page']);
        }
        catch (Exception $e) {
            return $resultJson->setData($this->setError($e->getMessage()));
        }

        return $resultJson->setData(['response' => 200]);
    }

    /**
     * @return bool
     */
    private function validateParams()
    {
        $this->params = $this->getRequest()->getParams();

        if (
            !isset($this->params['guid']) ||
            !isset($this->params['ClientName']) ||
            !isset($this->params['connection'])
        ) {
            $this->setError(__('Missing required param(s)'));

            return false;
        }

        return true;
    }

    /**
     * @param $message
     * @return array
     */
    private function setError($message)
    {
        $this->response = [
            'response' => 503,
            'error' => $message
        ];

        return $this->response;
    }

    /**
     * @param $authResponse
     * @return string
     */
    private function getClientIdFromAuth($authResponse)
    {
        $params = $this->extractParams($authResponse);

        return isset($params['id']) ? $params['id'] : '';
    }

    /**
     * @param $authResponse
     * @return string
     */
    private function getClientSecretFromAuth($authResponse)
    {
        $params = $this->extractParams($authResponse);

        return isset($params['secret']) ? $params['secret'] : '';
    }

    /**
     * @param $authResponse
     * @return mixed
     */
    private function extractParams($authResponse)
    {
        parse_str($authResponse, $output);

        return $output;
    }

    /**
     * @param $configPath
     * @param $value
     */
    private function setConfig($configPath, $value)
    {
        $this->configWriter->save($configPath,  $value, $scope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT, $scopeId = 0);
    }

    /**
     * @param $cacheTypes
     */
    private function flushCache($cacheTypes)
    {
        foreach ($cacheTypes as $type) {
            $this->cacheTypeList->cleanType($type);
        }
    }
}