<?php
namespace WeSupply\Toolbox\Api;


interface ReturnsInterface
{

    /**
     * @param array $returnsList
     * @return mixed
     */
    function processReturnsList($returnsList);


    /**
     * @return mixed
     */
    function getProcessedReturns();

    /**
     * @return mixed
     */
    function getNotProcessedReturns();
}