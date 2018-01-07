<?php


/**
 * Interface for config writers
 *
 * Specifies the methods that a config writer must implement
 *
 * @package Mamuph Config
 * @author  Mamuph Team
 * @copyright  (c) 2008-2017 Mamuph Team
 */
interface Core_Config_Contract_Writer extends Core_Config_Contract_Source
{


    /**
     * Write and merge the configuration.
     *
     * @example
     *
     *      $config->write($group, $key, $config);
     *
     * @param   string $group
     * @param   string $key
     * @param   array $config
     * @uses    Apprunner::findFile to find file
     * @uses    Apprunner::includes to include file
     * @return  void
     */
    public function write(string $group, string $key, array $config) : void;

}