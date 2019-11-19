<?php
namespace WeSupply\Toolbox\Controller\Adminhtml\MassUpdater;

class Index extends \WeSupply\Toolbox\Controller\Adminhtml\MassUpdater
{
    /**
     * @return \Magento\Framework\View\Result\Page
     */
    public function execute()
    {
        return  $resultPage = $this->resultPageFactory->create();
    }
}