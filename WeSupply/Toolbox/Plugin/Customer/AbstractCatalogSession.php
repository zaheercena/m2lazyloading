<?php
/**
 * Created by PhpStorm.
 * User: adminuser
 * Date: 03.10.2018
 * Time: 16:28
 */

namespace WeSupply\Toolbox\Plugin\Customer;

class AbstractCatalogSession
{
    private $catalogSession;

    public function __construct(
        \Magento\Catalog\Model\Session $catalogSession
    ){

        $this->catalogSession = $catalogSession;
    }


    /**
     * unset the estimations data session
     */
    public function unsetEstimationsData()
    {
        $this->catalogSession->unsEstimationsData();
    }

    /**
     * unset the generated tokens data session
     */
    public function unsetTokenData()
    {
        $this->catalogSession->unsGeneratedToken();
    }

}