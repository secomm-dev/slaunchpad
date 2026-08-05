<?php
namespace Magezon\Core\Model;

use Magezon\Core\Model\UserConfigHandlerInterface;

class UserConfigProcessor implements UserConfigProcessorInterface
{
    /**
     * @var array
     */
    protected $read;

    /**
     * @var array
     */
    protected $update;

    /**
     * @param array $read
     * @param array $update
     */
    public function __construct(
       $read = array(),
       $update = array(),
    ) {
        $this->read = $read;
        $this->update = $update;
    }

    private function process($processors, $config)
    {
        foreach ($processors as $name => $processor) {
            if (!($processor instanceof UserConfigHandlerInterface)) {
                throw new \InvalidArgumentException(
                    sprintf('Processor %s must implement %s interface.', $name, UserConfigHandlerInterface::class)
                );
            }
            $config = $processor->process($config);
        }

        return $config;
    }

    /**
     * @param array $config
     * @return array
     */
    public function processUpdateConfig(array $config)
    {
        return $this->process($this->update, $config);
    }

    /**
     * @param array $config
     * @return array
     */
    public function processReadConfig(array $config)
    {
        return $this->process($this->read, $config);
    }
}