<?php
namespace WeSupply\Toolbox\Block\System\Config;


use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class CredentialsCheck extends Field
{
    /**
     * @var string
     */
    protected $_template = 'WeSupply_Toolbox::system/config/credentialscheck.phtml';

    /**
     * @param Context $context
     * @param array $data
     */
    public function __construct(
        Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Remove scope label
     *
     * @param  AbstractElement $element
     * @return string
     */
    public function render(AbstractElement $element)
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    /**
     * Return element html
     *
     * @param  AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        return $this->_toHtml();
    }


    /**
     * Return ajax url for test connection button
     *
     * @return string
     */
    public function getAjaxUrl()
    {
        return $this->getUrl('wesupply/system_config/testconnection');
    }

    /**
     * Generate collect button html
     *
     * @return string
     */
    public function getButtonHtml()
    {
        $button = $this->getLayout()->createBlock(
            'Magento\Backend\Block\Widget\Button'
        )->setData(
            [
                'id' => 'test_credentials',
                'label' => __('Test'),
            ]
        );

        return $button->toHtml();
    }
}