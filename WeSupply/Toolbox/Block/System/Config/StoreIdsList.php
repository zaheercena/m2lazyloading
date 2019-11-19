<?php
namespace WeSupply\Toolbox\Block\System\Config;


use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class StoreIdsList extends Field
{

    /**
     * @var \Magento\Framework\Escaper
     */
    protected $_escaper;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var string
     */
    protected $_template = 'WeSupply_Toolbox::system/config/storeslist.phtml';


    /**
     * StoreIdsList constructor.
     * @param \Magento\Store\Model\StoreRepository $storeRepository
     * @param Context $context
     * @param array $data
     */
    public function __construct(
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Escaper $escaper,
        Context $context,
        array $data = []
    ) {
        $this->_escaper = $escaper;
        $this->storeManager = $storeManager;
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
     * @param $content
     * @return array|string
     */
    public function htmlEscape($content)
    {
        return $this->_escaper->escapeHtml($content);
    }

    /**
     * @return \Magento\Store\Api\Data\WebsiteInterface[]
     */
    public function getWebsites()
    {
        return $this->storeManager->getWebsites();
    }

}