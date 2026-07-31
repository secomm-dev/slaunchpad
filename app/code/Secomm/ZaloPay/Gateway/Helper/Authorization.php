<?php
/************************************************************
 * *
 *  * Copyright © Secomm. All rights reserved.
 *  * See COPYING.txt for license details.
 *  *
 *  * @author    Secomm Teams
 * *  @project   ZaloPay
 */

namespace Secomm\ZaloPay\Gateway\Helper;

use Secomm\ZaloPay\Gateway\Request\AbstractDataBuilder;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Payment\Gateway\ConfigInterface;

class Authorization
{
    /**
     * @var string
     */
    protected string $params;

    /**
     * @var ConfigInterface
     */
    private ConfigInterface $config;

    /**
     * @var EncryptorInterface
     */
    private EncryptorInterface $encryptor;

    /**
     * Authorization constructor.
     *
     * @param ConfigInterface $config
     * @param EncryptorInterface $encryptor
     */
    public function __construct(
        ConfigInterface    $config,
        EncryptorInterface $encryptor
    ) {
        $this->config = $config;
        $this->encryptor = $encryptor;
    }

    /**
     * Get Mac string
     *
     * @param array $params
     * @return string
     */
    public function getMac(array $params): string
    {
        $strParams = hash_hmac('sha256', implode('|', $params), $this->getKey1());
        $this->setParameter($strParams);
        return $strParams;
    }

    /**
     * Get Mac By Key 2
     *
     * @param string $transData
     * @return string
     */
    public function getMacKey2(string $transData): string
    {
        return hash_hmac('sha256', $transData, $this->getKey2());
    }

    /**
     * @return array
     */
    public function getMacData(): array
    {
        return [
            AbstractDataBuilder::APP_ID,
            AbstractDataBuilder::APP_TRANS_ID,
            AbstractDataBuilder::APP_USER,
            AbstractDataBuilder::AMOUNT,
            AbstractDataBuilder::APP_TIME,
            AbstractDataBuilder::EMBED_DATA,
            AbstractDataBuilder::ITEM
        ];
    }

    /**
     * @return string
     */
    public function getParameter(): string
    {
        return $this->params;
    }


    /**
     * @param $params
     * @return $this
     */
    public function setParameter($params): static
    {
        $this->params = $params;
        return $this;
    }

    /**
     * Get Header
     *
     * @return array
     */
    public function getHeaders(): array
    {
        return [
            'Content-Type: application/x-www-form-urlencoded'
        ];
    }

    /**
     * Get Key 1 (decrypted if backend_model encrypted)
     *
     * @return string
     */
    public function getKey1(): string
    {
        $value = $this->config->getValue(AbstractDataBuilder::KEY_1);
        if (!$value) {
            return $value;
        }
        try {
            return $this->encryptor->decrypt($value);
        } catch (\Exception $e) {
            // Fallback to plain value if decrypt fails (already plaintext)
            return $value;
        }
    }

    /**
     * Get Key 2 (decrypted if backend_model encrypted)
     *
     * @return string
     */
    public function getKey2(): string
    {
        $value = $this->config->getValue(AbstractDataBuilder::KEY_2);
        if (!$value) {
            return $value;
        }
        try {
            return $this->encryptor->decrypt($value);
        } catch (\Exception $e) {
            // Fallback to plain value if decrypt fails (already plaintext)
            return $value;
        }
    }
}
