<?php
namespace Magezon\Core\Model;

interface UserConfigProcessorInterface
{
    /**
     * @param array $config
     * @return array
     */
    public function processUpdateConfig(array $config);

    /**
     * @param array $config
     * @return array
     */
    public function processReadConfig(array $config);
}