<?php


namespace WeSupply\Toolbox\Plugin\Customer;

class Session extends AbstractCatalogSession
{

    public function afterLogout(
        \Magento\Customer\Model\Session $subject,
        $result
    ) {

        if($result){
            $this->unsetEstimationsData();
            $this->unsetTokenData();
        }

        return $result;
    }
}


