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
 * @package   mirasvit/module-seo
 * @version   2.12.8
 * @copyright Copyright (C) 2026 Mirasvit (https://mirasvit.com/)
 */


declare(strict_types=1);

namespace Mirasvit\SeoMarkup\Controller\Adminhtml\Extender;

use Exception;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Mirasvit\SeoMarkup\Api\Data\ExtenderInterface;
use Mirasvit\SeoMarkup\Api\Data\ExtenderInterfaceFactory;
use Mirasvit\SeoMarkup\Repository\ExtenderRepository;

class Save extends Action
{
    public const ADMIN_RESOURCE = 'Mirasvit_SeoAutolink::seomarkup_extender';

    private $factory;

    private $extenderRepository;

    private $dataPersistor;

    private $serializer;

    public function __construct(
        Context                  $context,
        ExtenderInterfaceFactory $factory,
        ExtenderRepository       $extenderRepository,
        DataPersistorInterface   $dataPersistor,
        Json                     $serializer
    ) {
        parent::__construct($context);

        $this->factory            = $factory;
        $this->extenderRepository = $extenderRepository;
        $this->dataPersistor      = $dataPersistor;
        $this->serializer         = $serializer;
    }

    /**
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function execute(): ResultInterface
    {
        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();
        $data           = $this->getRequest()->getPostValue();

        if (!$data) {
            $this->messageManager->addErrorMessage(__('No data to save.'));

            return $resultRedirect->setPath('*/*/');
        }

        if (isset($data[ExtenderInterface::RULE][ExtenderInterface::CONDITIONS])) {
            $conditions                                     = $this->prepareConditionsArray(
                $data[ExtenderInterface::RULE][ExtenderInterface::CONDITIONS]
            );
            $conditionSerialized                            = $this->serializer->serialize($conditions);
            $data[ExtenderInterface::CONDITIONS_SERIALIZED] = $conditionSerialized;
        }

        $this->dataPersistor->set(ExtenderInterface::DATA_PERSISTOR_KEY, $data);

        $extenderId = $this->getRequest()->getParam(ExtenderInterface::EXTENDER_ID);
        $extender   = $extenderId ? $this->extenderRepository->get((int)$extenderId) : $this->factory->create();

        if (!($extender->getId()) && $extenderId) {
            $this->messageManager->addErrorMessage(__('Rich snippet extender no longer exists.'));

            return $resultRedirect->setPath('*/*/');
        }

        try {
            if (!isset($data[ExtenderInterface::SNIPPET]) || !$this->isJson($data[ExtenderInterface::SNIPPET])) {
                throw new LocalizedException(__('Incorrect JSON format'));
            }

            $extender->setName($data[ExtenderInterface::NAME] ?? '');
            $extender->setIsActive((bool)$data[ExtenderInterface::IS_ACTIVE]);
            $extender->setEntityTypeId((string)$data[ExtenderInterface::ENTITY_TYPE_ID]);
            $extender->setSnippet($data[ExtenderInterface::SNIPPET]);
            $extender->setOverride((bool)$data[ExtenderInterface::OVERRIDE]);
            $extender->setStoreIds(
                isset($data[ExtenderInterface::STORE_IDS]) && is_array($data[ExtenderInterface::STORE_IDS])
                    ? $data[ExtenderInterface::STORE_IDS]
                    : [0]
            );

            if (!empty($conditionSerialized)) {
                $extender->setConditionsSerialized($conditionSerialized);
            }

            $extender = $this->extenderRepository->save($extender);

            $this->messageManager->addSuccessMessage(__('Rich snippet extender has been saved.'));
            $this->_getSession()->setFormData(false);
            $this->dataPersistor->clear(ExtenderInterface::DATA_PERSISTOR_KEY);

            if ($this->getRequest()->getParam('back')) {
                return $resultRedirect->setPath(
                    '*/*/edit',
                    [ExtenderInterface::REQUEST_PARAM_ID => $extender->getId()]
                );
            } elseif ($this->getRequest()->getParam('redirect_to_new')) {
                return $resultRedirect->setPath('*/*/new');
            }
        } catch (Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            $this->_getSession()->setFormData($data);

            return $resultRedirect->setPath(
                '*/*/edit',
                [
                    ExtenderInterface::REQUEST_PARAM_ID
                    => (int)$this->getRequest()->getParam(ExtenderInterface::EXTENDER_ID),
                ]
            );
        }

        return $resultRedirect->setPath('*/*/');
    }

    private function prepareConditionsArray(array $conditionsFormData): array
    {
        $conditions = [ExtenderInterface::CONDITIONS => []];
        foreach ($conditionsFormData as $key => $condition) {
            if (1 === $key) {
                $conditions = $condition;
                continue;
            }

            $conditions[ExtenderInterface::CONDITIONS][] = $condition;
        }

        return $conditions;
    }

    private function isJson(string $string): bool
    {
        json_decode($string);

        return json_last_error() === JSON_ERROR_NONE;
    }
}
