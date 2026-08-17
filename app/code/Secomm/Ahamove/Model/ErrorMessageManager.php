<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\Ahamove\Model;

use Magento\Framework\Session\Config\ConfigInterface;
use Magento\Framework\Session\SaveHandlerInterface;
use Magento\Framework\Session\SessionStartChecker;
use Magento\Framework\Session\SidResolverInterface;
use Magento\Framework\Session\StorageInterface;
use Magento\Framework\Session\ValidatorInterface;

class ErrorMessageManager extends \Magento\Framework\Session\SessionManager
{
    const SESSION_NAME = 'ahamove_error_message';

    public function __construct(
        \Magento\Framework\App\Request\Http                    $request,
        SidResolverInterface                                   $sidResolver,
        ConfigInterface                                        $sessionConfig,
        SaveHandlerInterface                                   $saveHandler,
        ValidatorInterface                                     $validator,
        StorageInterface                                       $storage,
        \Magento\Framework\Stdlib\CookieManagerInterface       $cookieManager,
        \Magento\Framework\Stdlib\Cookie\CookieMetadataFactory $cookieMetadataFactory,
        \Magento\Framework\App\State                           $appState,
        SessionStartChecker                                    $sessionStartChecker = null
    ) {
        parent::__construct(
            $request,
            $sidResolver,
            $sessionConfig,
            $saveHandler,
            $validator,
            $storage,
            $cookieManager,
            $cookieMetadataFactory,
            $appState,
            $sessionStartChecker
        );
        parent::start();
    }

    /**
     * Set session data
     * @param string $message
     * @return void
     */
    public function setErrorMessage(string $message): void
    {
        $this->storage->setData(self::SESSION_NAME, $message);
    }

    /**
     * Get session data
     *
     * @param string $sessionName
     * @return string
     */
    public function getErrorMessage(string $sessionName = self::SESSION_NAME): string
    {
        return $this->storage->getData($sessionName);
    }

    /**
     * @param string $sessionName
     * @return bool
     */
    public function hasError(string $sessionName = self::SESSION_NAME): bool
    {
        return (bool)$this->storage->getData($sessionName);
    }

    /**
     * Unset session data — do NOT destroy the session as it would
     * affect the customer's active session on storefront.
     */
    public function __destruct()
    {
        $this->storage->unsetData(self::SESSION_NAME);
    }
}
