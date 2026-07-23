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
 * @copyright   Copyright (c) Mageplaza (http://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\AbandonedCart\Controller\Adminhtml\Sms;

use Exception;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Mageplaza\AbandonedCart\Helper\Sms;
use Twilio\Exceptions\ConfigurationException;
use Twilio\Exceptions\TwilioException;
use Twilio\Rest\Client;

/**
 * Class Test
 * @package Mageplaza\AbandonedCart\Controller\Adminhtml\Sms
 */
class Test extends Action
{
    /**
     * @var JsonFactory
     */
    private $resultJsonFactory;

    /**
     * @var Sms
     */
    private $smsHelper;

    /**
     * Test constructor.
     *
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param Sms $smsHelper
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        Sms $smsHelper
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->smsHelper = $smsHelper;

        parent::__construct($context);
    }

    /**
     * @return ResponseInterface|Json|ResultInterface
     */
    public function execute()
    {
        $data = $this->getRequest()->getParams();

        $resultJson = $this->resultJsonFactory->create();
        try {
            $this->testTwilio($data);

            return $resultJson->setData([
                'type'    => 'success',
                'message' => __('The Test SMS has been sent successfully.')
            ]);
        } catch (Exception $e) {
            return $resultJson->setData(['type' => 'error', 'message' => __('Unable to send Test SMS')]);
        }
    }

    /**
     * @param array $data
     *
     * @throws ConfigurationException
     * @throws TwilioException
     */
    public function testTwilio($data)
    {
        $twilioSID   = $data['twilio_sid'];
        $twilioToken = $data['twilio_token'];

        if ($twilioToken === '******') {
            $twilioToken = $this->smsHelper->getTwilioToken();
        }

        $client = new Client($twilioSID, $twilioToken);
        $client->messages->create(
            $data['recipient_phone'],
            [
                'from' => $data['sender_phone'],
                'body' => 'This message is sent from from Twilio'
            ]
        );
    }
}
