<?php

/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
 
namespace WeSupply\Toolbox\Model;

use Magento\Config\Model\Config\CommentInterface;
use Magento\Framework\Phrase;
use WeSupply\Toolbox\Helper\Data as Helper;

/**
 * Class ClientNameComment
 * @package WeSupply\Toolbox\Model
 */

class ClientNameComment implements CommentInterface
{
    /**
     * @var Helper
     */
    public $helper;
    
    
    /**
     * ClientNameComment constructor.
     * @param Helper $helper
     */
    public function __construct(
        Helper $helper
    )
    {
        $this->helper = $helper;
    }
    
    /**
     * @param string $elementValue
     * @return Phrase|string
     */
    public function getCommentText($elementValue)
    {
        $clientName = $this->helper->getClientName();
        if ($clientName) {
            return __('<strong>%1</strong><br/>Client Name of your WeSupply account, same as WeSupply SubDomain of <strong>Step 1 - Generate Magento Access Key</strong> configuration tab.', $clientName);
        }
    
        if ($clientName !== null) {
            return __('Please fill in and save <strong>WeSupply SubDomain</strong> field from <strong>Step 1 - Generate Magento Access Key</strong> Configuration tab, to define your Client Name!');
        }
        
        return __('Cannot get API Endpoint');
    }

}