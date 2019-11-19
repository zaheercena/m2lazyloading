<?php
/**
 * Created by PhpStorm.
 * User: adminuser
 * Date: 13.06.2019
 * Time: 15:02
 */

namespace WeSupply\Toolbox\Logger;

use Monolog\Logger;

class Handler extends \Magento\Framework\Logger\Handler\Base
{

    /**
     * Logging level
     * @var int
     */
    protected $loggerType = Logger::INFO;

    /**
     * File name
     * @var string
     */
    protected $fileName = '/var/log/wesupply.log';

}