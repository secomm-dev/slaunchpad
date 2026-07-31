<?php
/**
 * Module-wide MoMo config reader.
 *
 * Reads payment/momo_payment/* values, decrypts the encrypted secret/access keys
 * and resolves the MoMo API endpoint by sandbox mode.
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2024 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Gateway\Config;

use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Payment\Gateway\ConfigInterface;

class Config
{
    /**#@+
     * MoMo v2 gateway endpoints
     */
    public const ENDPOINT_LIVE = 'https://payment.momo.vn';
    public const ENDPOINT_SANDBOX = 'https://test-payment.momo.vn';
    public const PATH_CREATE = '/v2/gateway/api/create';
    public const PATH_REFUND = '/v2/gateway/api/refund';
    public const PATH_QUERY = '/v2/gateway/api/query';
    /**#@-*/

    /**#@+
     * Config keys
     */
    public const KEY_PARTNER_CODE = 'partner_code';
    public const KEY_ACCESS_KEY = 'access_key';
    public const KEY_SECRET_KEY = 'secret_key';
    public const KEY_SANDBOX = 'sandbox';
    public const KEY_RETURN_URL = 'return_url';
    public const KEY_NOTIFY_URL = 'notify_url';
    public const KEY_PAYMENT_ACTION = 'momo_payment_action';
    public const KEY_REQUEST_TYPE = 'captureWallet';
    /**#@-*/

    /**
     * @var ConfigInterface
     */
    private ConfigInterface $config;

    /**
     * @var EncryptorInterface
     */
    private EncryptorInterface $encryptor;

    /**
     * Constructor
     *
     * @param ConfigInterface $config
     * @param EncryptorInterface $encryptor
     */
    public function __construct(
        \Magento\Payment\Gateway\ConfigInterface $config,
        EncryptorInterface $encryptor
    ) {
        $this->config = $config;
        $this->encryptor = $encryptor;
    }

    /**
     * Read a raw config value.
     *
     * @param string $key
     * @return mixed
     */
    public function getValue(string $key): mixed
    {
        return $this->config->getValue($key);
    }

    /**
     * Whether sandbox mode is enabled.
     *
     * @return bool
     */
    public function isSandbox(): bool
    {
        return (bool)$this->config->getValue(self::KEY_SANDBOX);
    }

    /**
     * Partner code (plaintext credential).
     *
     * @return string
     */
    public function getPartnerCode(): string
    {
        return (string)$this->config->getValue(self::KEY_PARTNER_CODE);
    }

    /**
     * Access key — decrypted if stored encrypted.
     *
     * @return string
     */
    public function getAccessKey(): string
    {
        return $this->decrypt((string)$this->config->getValue(self::KEY_ACCESS_KEY));
    }

    /**
     * Secret key (HMAC signing key) — decrypted if stored encrypted.
     *
     * @return string
     */
    public function getSecretKey(): string
    {
        return $this->decrypt((string)$this->config->getValue(self::KEY_SECRET_KEY));
    }

    /**
     * Admin-configured browser return URL.
     *
     * @return string
     */
    public function getReturnUrl(): string
    {
        return (string)$this->config->getValue(self::KEY_RETURN_URL);
    }

    /**
     * Admin-configured IPN notify URL.
     *
     * @return string
     */
    public function getNotifyUrl(): string
    {
        return (string)$this->config->getValue(self::KEY_NOTIFY_URL);
    }

    /**
     * Resolved MoMo base endpoint for the active mode.
     *
     * @return string
     */
    public function getBaseEndpoint(): string
    {
        return $this->isSandbox() ? self::ENDPOINT_SANDBOX : self::ENDPOINT_LIVE;
    }

    /**
     * Full URL for a given API path.
     *
     * @param string $path One of the PATH_* constants.
     * @return string
     */
    public function getEndpointUrl(string $path): string
    {
        return $this->getBaseEndpoint() . $path;
    }

    /**
     * Decrypt a stored value, falling back to the plain value if it is not encrypted.
     *
     * @param string $value
     * @return string
     */
    private function decrypt(string $value): string
    {
        if ($value === '') {
            return $value;
        }
        try {
            return $this->encryptor->decrypt($value);
        } catch (\Exception $e) {
            return $value;
        }
    }
}
