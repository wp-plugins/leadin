<?php

include('ConfigFileReaderTrait.php');

abstract class ConfigReaderInterface extends ConfigFileReaderTrait
{
    /**
     * @param string $lookupString
     * @return array
     */
    public function lookup($lookupString)
    {
    	
    }
}
