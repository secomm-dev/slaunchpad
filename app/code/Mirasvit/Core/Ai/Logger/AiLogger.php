<?php
/**
 * Mirasvit
 *
 * This source file is subject to the Mirasvit Software License, which is available at https://mirasvit.com/license/.
 * Do not edit or add to this file if you wish to upgrade the to newer versions in the future.
 * If you wish to customize this module for your needs.
 * Please refer to http://www.magentocommerce.com for more information.
 *
 * @category  Mirasvit
 * @package   mirasvit/module-core
 * @version   1.7.15
 * @copyright Copyright (C) 2026 Mirasvit (https://mirasvit.com/)
 */



declare(strict_types=1);

namespace Mirasvit\Core\Ai\Logger;

use Mirasvit\Core\Ai\Model\ConfigProvider;

class AiLogger extends \Monolog\Logger
{
    private $configProvider;

    private $debugStatus = null;

    public function __construct(
        string          $name,
        array           $handlers = [],
        array           $processors = [],
        ?ConfigProvider $configProvider = null
    ) {
        parent::__construct($name, $handlers, $processors);

        if ($configProvider) {
            $this->configProvider = $configProvider;
        }
    }

    public function forceDebugMode(?bool $status): void
    {
        $this->debugStatus = $status;
    }

    public function isDebugEnabled(): bool
    {
        if ($this->debugStatus !== null) {
            return $this->debugStatus;
        }

        if (isset($this->configProvider)) {
            return $this->configProvider->isDebugModeEnabled();
        }

        return false;
    }

    public function debug($message, array $context = []): void
    {
        if (!$this->isDebugEnabled()) {
            return;
        }

        $this->addRecord(static::DEBUG, $message, $context);
    }

    public function getDebugStatus(): ?bool
    {
        return $this->debugStatus;
    }
}
