<?php
namespace Magezon\Core\Model;

interface UserConfigHandlerInterface
{
    /**
     * @param array $config
     * @return array
     */
    public function process(array $config);
}