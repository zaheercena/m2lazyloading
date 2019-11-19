<?php
namespace WeSupply\Toolbox\Plugin\Customer;

class Authentication extends AbstractCatalogSession
{

    public function afterAuthenticate(
        \Magento\Customer\Model\Authentication $subject,
        $result
    ) {
        if($result){
            $this->unsetEstimationsData();
            $this->unsetTokenData();

        }
    }
}
