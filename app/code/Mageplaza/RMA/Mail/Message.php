<?php
/**
 * Mageplaza
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the mageplaza.com license that is
 * available through the world-wide-web at this URL:
 * https://www.mageplaza.com/LICENSE.txt
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category    Mageplaza
 * @package     Mageplaza_RMA
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\RMA\Mail;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\Mail\MailMessageInterface;
use Magento\Framework\Mail\Message as MailMessage;
use Magento\Framework\Mail\MessageInterface;
use Mageplaza\RMA\Helper\Data as HelperData;
use Zend_Mime;

/**
 * Class Message
 * @package Mageplaza\RMA\Mail
 */
class Message extends MailMessage
{
    /**
     * @var Zend\Mime\PartFactory
     */
    protected $partFactory;

    /**
     * @var Zend\Mime\MessageFactory
     */
    protected $mimeMessageFactory;

    /**
     * @var Message
     */
    protected $zendMessage;

    /**
     * @var Zend\Mime\Part[]
     */
    protected $parts = [];

    /**
     * @var ObjectManager
     */
    protected $_objectManager;

    /**
     * @var HelperData
     */
    protected $_helperData;

    /**
     * Message constructor.
     *
     * @param string $charset
     */
    public function __construct(
        $charset = 'utf-8'
    ) {
        $this->_objectManager = ObjectManager::getInstance();

        parent::__construct($charset);
    }

    /**
     * @return Message|mixed
     */
    public function getZendMessage()
    {
        if (!$this->zendMessage) {
            $this->zendMessage = $this->_objectManager->create('Zend\Mail\Message');
            $this->zendMessage->setEncoding('utf-8');
        }

        return $this->zendMessage;
    }

    /**
     * @return HelperData|mixed
     */
    public function getHelperData()
    {
        if (!$this->_helperData) {
            $this->_helperData = $this->_objectManager->get(HelperData::class);
        }

        return $this->_helperData;
    }

    /**
     * @param string $content
     * @param null $charset
     * @param string $encoding
     *
     * @return $this|MailMessageInterface|MailMessage|MessageInterface
     */
    public function setBodyText($content, $charset = null, $encoding = Zend_Mime::ENCODING_QUOTEDPRINTABLE): MailMessage
    {
        if ($this->getHelperData()->versionCompare('2.2.8')) {
            $textPart = $this->_objectManager->create('Zend\Mime\Part');
            $textPart->setContent($content)
                ->setType(Zend_Mime::TYPE_TEXT)
                ->setCharset('utf-8');
            $this->parts[] = $textPart;

            return $this;
        }

        return parent::setBodyText($content);
    }

    /**
     * @param string $content
     * @param null $charset
     * @param string $encoding
     *
     * @return $this|MailMessageInterface|MailMessage|MessageInterface
     */
    public function setBodyHtml($content, $charset = null, $encoding = Zend_Mime::ENCODING_QUOTEDPRINTABLE): MailMessage
    {
        if ($this->getHelperData()->versionCompare('2.2.8')) {
            $htmlPart = $this->_objectManager->create('Zend\Mime\Part');
            $htmlPart->setContent($content)
                ->setType(Zend_Mime::TYPE_HTML)
                ->setCharset('utf-8');
            $this->parts[] = $htmlPart;

            return $this;
        }

        return parent::setBodyHtml($content);
    }

    /**
     * Add the attachment mime part to the message.
     *
     * @param string $content
     * @param string $fileName
     * @param string $fileType
     *
     * @return $this
     */
    public function setBodyAttachment($content, $fileName, $fileType)
    {
        $attachmentPart = $this->_objectManager->create('Zend\Mime\Part');
        $attachmentPart->setContent($content)
            ->setType($fileType)
            ->setFileName($fileName)
            ->setDisposition(Zend_Mime::DISPOSITION_ATTACHMENT)
            ->setEncoding(Zend_Mime::ENCODING_BASE64);
        $this->parts[] = $attachmentPart;

        return $this;
    }

    /**
     * Set parts to Zend message body.
     *
     * @return $this
     */
    public function setPartsToBody()
    {
        $mimeMessage = $this->_objectManager->create('Zend\Mime\Message');
        $mimeMessage->setParts($this->parts);
        $this->getZendMessage()->setBody($mimeMessage);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setBody($body): MailMessage
    {
        if ($this->getHelperData()->versionCompare('2.2.8')) {
            return $this;
        }

        return parent::setBody($body);
    }

    /**
     * {@inheritdoc}
     */
    public function setSubject($subject): MailMessage
    {
        if ($this->getHelperData()->versionCompare('2.2.8')) {
            $this->getZendMessage()->setSubject($subject);

            return $this;
        }

        return parent::setSubject($subject);
    }

    /**
     * {@inheritdoc}
     */
    public function getSubject(): ?string
    {
        if ($this->getHelperData()->versionCompare('2.2.8')) {
            return $this->getZendMessage()->getSubject();
        }

        return parent::getSubject();
    }

    /**
     * {@inheritdoc}
     */
    public function getBody(): \Symfony\Component\Mime\Part\AbstractPart
    {
        if ($this->getHelperData()->versionCompare('2.2.8')) {
            return $this->getZendMessage()->getBody();
        }

        return parent::getBody();
    }

    /**
     * {@inheritdoc}
     */
    public function setFrom($fromAddress, $name = null): MailMessage
    {
        if ($this->getHelperData()->versionCompare('2.2.8')) {
            $this->getZendMessage()->setFrom($fromAddress, $name);

            return $this;
        }

        return parent::setFrom($fromAddress);
    }

    /**
     * {@inheritdoc}
     */
    public function addTo($toAddress, $name = ''): MailMessage
    {
        if ($this->getHelperData()->versionCompare('2.2.8')) {
            $this->getZendMessage()->addTo($toAddress, $name);

            return $this;
        }

        return parent::addTo($toAddress);
    }

    /**
     * {@inheritdoc}
     */
    public function addCc($ccAddress, $name = ''): MailMessage
    {
        if ($this->getHelperData()->versionCompare('2.2.8')) {
            $this->getZendMessage()->addCc($ccAddress);

            return $this;
        }

        return parent::addCc($ccAddress);
    }

    /**
     * {@inheritdoc}
     */
    public function addBcc($bccAddress): MailMessage
    {
        if ($this->getHelperData()->versionCompare('2.2.8')) {
            $this->getZendMessage()->addBcc($bccAddress);

            return $this;
        }

        return parent::addBcc($bccAddress);
    }

    /**
     * {@inheritdoc}
     */
    public function setReplyTo($replyToAddress, $name = null): MailMessage
    {
        if ($this->getHelperData()->versionCompare('2.2.8')) {
            $this->getZendMessage()->setReplyTo($replyToAddress, $name);

            return $this;
        }

        return parent::setReplyTo($replyToAddress);
    }

    /**
     * {@inheritdoc}
     */
    public function getRawMessage(): string
    {
        if ($this->getHelperData()->versionCompare('2.2.8')) {
            return $this->getZendMessage()->toString();
        }

        return parent::getRawMessage();
    }

    /**
     * @inheritDoc
     */
    public function setMessageType($type): MailMessage
    {
        if ($this->getHelperData()->versionCompare('2.2.8')) {
            return $this;
        }

        return parent::setMessageType($type);
    }

    /**
     * {@inheritdoc}
     * @param string $fromAddress
     * @param null $fromName
     *
     * @return Message
     */

    public function setFromAddress($fromAddress, $fromName = null): MailMessage
    {
        if ($this->getHelperData()->versionCompare('2.2.8')) {
            $this->getZendMessage()->setFrom($fromAddress, $fromName);

            return $this;
        }

        return $this;
    }
}
