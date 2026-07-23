<?php
/**
 * Mageplaza
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Mageplaza.com license that is
 * available through the world-wide-web at this URL:
 * https://www.mageplaza.com/LICENSE.txt
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category    Mageplaza
 * @package     Mageplaza_AbandonedCart
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\AbandonedCart\Model\Sms;

use Mageplaza\AbandonedCart\Helper\Sms;
use Psr\Log\LoggerInterface;
use Twilio\Exceptions\ConfigurationException;
use Twilio\Exceptions\TwilioException;
use Twilio\Rest\Client;

/**
 * Class Twilio
 * @package Mageplaza\AbandonedCart\Model\Sms
 */
class Twilio
{
    /**
     * @var Sms
     */
    protected $smsHelper;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Twilio constructor.
     *
     * @param Sms $smsHelper
     * @param LoggerInterface $logger
     */
    public function __construct(
        Sms $smsHelper,
        LoggerInterface $logger
    ) {
        $this->smsHelper = $smsHelper;
        $this->logger    = $logger;
    }

    /**
     * @param string|null $body
     * @param string|null $recipient
     *
     * @return $this
     * @throws ConfigurationException
     */
    public function send($body, $recipient)
    {
        if (!$body) {
            return $this;
        }
        $sid    = $this->smsHelper->getTwilioSID();
        $token  = $this->smsHelper->getTwilioToken();
        $sender = $this->smsHelper->getSenderPhoneNumber();

        if (!$sid || !$token || !$sender) {
            return $this;
        }

        $client = new Client($sid, $token);
        try {
            $client->messages->create(
                $recipient,
                [
                    'from' => $sender,
                    'body' => $body
                ]
            );
        } catch (TwilioException $e) {
            $this->logger->critical($e->getMessage());
        }

        return $this;
    }
}
