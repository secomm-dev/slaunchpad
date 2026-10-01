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
 * @package     Mageplaza_RMA
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\RMA\Mail\Template;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\ValidatorException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\Read;
use Magento\Framework\Mail\MessageInterface;
use Magento\Framework\Mail\Template\FactoryInterface;
use Magento\Framework\Mail\Template\SenderResolverInterface;
use Magento\Framework\Mail\Template\TransportBuilder as MailTransportBuilder;
use Magento\Framework\Mail\TransportInterfaceFactory;
use Magento\Framework\ObjectManagerInterface;
use Mageplaza\Core\Helper\AbstractData;
use Mageplaza\RMA\Mail\Message;
use Zend_Mime;

/**
 * Class TransportBuilder
 * @package Mageplaza\RMA\Mail\Template
 */
class TransportBuilder extends MailTransportBuilder
{
    /**
     * @var Filesystem
     */
    protected $_filesystem;

    /**
     * @var AbstractData
     */
    protected $_helperData;

    /**
     * @var Message
     */
    protected $rmaMessage;

    /**
     * TransportBuilder constructor.
     *
     * @param FactoryInterface $templateFactory
     * @param MessageInterface $message
     * @param SenderResolverInterface $senderResolver
     * @param ObjectManagerInterface $objectManager
     * @param TransportInterfaceFactory $mailTransportFactory
     * @param Filesystem $filesystem
     * @param AbstractData $helperData
     * @param Message $rmaMessage
     */
    public function __construct(
        FactoryInterface $templateFactory,
        MessageInterface $message,
        SenderResolverInterface $senderResolver,
        ObjectManagerInterface $objectManager,
        TransportInterfaceFactory $mailTransportFactory,
        Filesystem $filesystem,
        AbstractData $helperData,
        Message $rmaMessage
    ) {
        $this->_filesystem = $filesystem;
        $this->_helperData = $helperData;
        $this->rmaMessage = $rmaMessage;

        parent::__construct(
            $templateFactory,
            $message,
            $senderResolver,
            $objectManager,
            $mailTransportFactory
        );
    }

    /**
     * @return $this|MailTransportBuilder
     * @throws LocalizedException
     */
    protected function prepareMessage()
    {
        parent::prepareMessage();
        if ($this->_helperData->versionCompare('2.2.8') && $this->_helperData->versionCompare('2.3.2', '<')) {
            $this->message->setPartsToBody();
        }

        return $this;
    }

    /**
     * @param string $filePath
     * @param array $file
     *
     * @return $this
     * @throws ValidatorException
     * @throws FileSystemException
     */
    public function addAttachment($filePath, $file)
    {
        /** @var Read $mediaDirectory */
        $mediaDirectory = $this->_filesystem->getDirectoryRead(DirectoryList::MEDIA);
        if (!empty($filePath) && $mediaDirectory->isFile($filePath)) {
            if ($this->_helperData->versionCompare('2.2.8')) {
                $this->rmaMessage->setBodyAttachment(
                    $mediaDirectory->readFile($filePath),
                    $file['name'],
                    $file['type']
                );
            } else {
                $this->message->createAttachment(
                    $mediaDirectory->readFile($filePath),
                    $file['type'],
                    Zend_Mime::DISPOSITION_ATTACHMENT,
                    Zend_Mime::ENCODING_BASE64,
                    $file['name']
                );
            }
        }

        return $this;
    }
}
