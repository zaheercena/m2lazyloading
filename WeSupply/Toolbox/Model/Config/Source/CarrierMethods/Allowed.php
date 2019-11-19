<?php

namespace WeSupply\Toolbox\Model\Config\Source\CarrierMethods;

use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Shipping\Model\CarrierFactoryInterface;
use Magento\Config\Model\Config\Structure\Element\Field;
use Magento\Framework\Option\ArrayInterface;

class Allowed implements ArrayInterface
{
    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var CarrierFactoryInterface
     */
    private $carrierFactory;

    /**
     * @var Field
     */
    private $fieldElement;

    /**
     * Allowed constructor.
     * @param ScopeConfigInterface $scopeConfig
     * @param CarrierFactoryInterface $carrierFactory
     * @param Field $field
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        CarrierFactoryInterface $carrierFactory,
        Field $field
    )
    {
        $this->scopeConfig = $scopeConfig;
        $this->carrierFactory = $carrierFactory;
        $this->fieldElement = $field;
    }

    /**
     * @return array
     */
    public function toOptionArray()
    {
        $options = [];
        $carrierCode = $this->getCarrierCode();

        if (
            !$carrierCode ||
            !$this->scopeConfig->getValue('carriers/' . $carrierCode . '/active', ScopeInterface::SCOPE_STORE) ||
            !$this->checkAllowedMethods($carrierCode)
        ) {
            return $options;
        }

        $carrier = $this->carrierFactory->create($carrierCode);
        if (is_callable([$carrier, 'getAllowedMethods'])) {
            $allowedMethodsArr = $carrier->getAllowedMethods();
            foreach ($allowedMethodsArr as $code => $method) {
                $options[] = ['value' => $code, 'label' => $method];
            }
        }

        return $options;
    }

    /**
     * @return mixed
     */
    protected function getCarrierCode()
    {
        $configPathArray = array_slice(explode('_', $this->fieldElement->getId()), -1);

        return array_pop($configPathArray);
    }

    /**
     * @param $carrierCode
     * @return bool
     */
    protected function checkAllowedMethods($carrierCode)
    {
        switch ($carrierCode) {
            case 'dhl':
                if (
                    $this->scopeConfig->getValue('carriers/' . $carrierCode . '/doc_methods', ScopeInterface::SCOPE_STORE) ||
                    $this->scopeConfig->getValue('carriers/' . $carrierCode . '/nondoc_methods', ScopeInterface::SCOPE_STORE)
                ) {
                    return true;
                }
                break;
            default:
                if ($this->scopeConfig->getValue('carriers/' . $this->getCarrierCode() . '/allowed_methods', ScopeInterface::SCOPE_STORE)) {
                    return true;
                }
                break;
        }

        return false;
    }
}