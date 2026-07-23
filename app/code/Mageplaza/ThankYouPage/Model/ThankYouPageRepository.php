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
 * @package     Mageplaza
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\ThankYouPage\Model;

use Exception;
use Magento\Framework\Api\Search\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Api\SearchResultsInterfaceFactory;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Phrase;
use Magento\Framework\Reflection\DataObjectProcessor;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Framework\View\Result\Layout;
use Magento\Framework\View\Result\LayoutFactory;
use Magento\Newsletter\Model\Subscriber;
use Magento\Newsletter\Model\SubscriberFactory;
use Magento\SalesRule\Api\Data\ConditionInterfaceFactory;
use Magento\SalesRule\Model\Data\Condition;
use Mageplaza\ThankYouPage\Api\Data\ThankYouPageInterface;
use Mageplaza\ThankYouPage\Api\ThankYouPageRepositoryInterface;
use Mageplaza\ThankYouPage\Block\OrderSuccess;
use Mageplaza\ThankYouPage\Block\Subscribe;
use Mageplaza\ThankYouPage\Controller\Subscribe\NewAction;
use Mageplaza\ThankYouPage\Helper\Data;
use Mageplaza\ThankYouPage\Model\Config\Source\NewsletterPageStyle;
use Mageplaza\ThankYouPage\Model\Config\Source\OrderPageStyle;
use Mageplaza\ThankYouPage\Model\Config\Source\Pagetype;
use Mageplaza\ThankYouPage\Model\ResourceModel\Template as ResourceModel;
use Mageplaza\ThankYouPage\Model\ResourceModel\Template\Collection;
use Mageplaza\ThankYouPage\Model\ResourceModel\Template\CollectionFactory;

/**
 * Class ThankYouPageRepository
 * @package Mageplaza\ThankYouPage\Model
 */
class ThankYouPageRepository implements ThankYouPageRepositoryInterface
{
    /**
     * @var Data
     */
    protected $helperData;

    /**
     * @var CollectionProcessorInterface
     */
    protected $collectionProcessor;

    /**
     * @var SearchResultsInterfaceFactory
     */
    protected $searchResultsFactory;

    /**
     * @var SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;

    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;

    /**
     * @var TemplateFactory
     */
    protected $templateFactory;

    /**
     * @var ResourceModel
     */
    protected $resourceModel;

    /**
     * @var ConditionInterfaceFactory
     */
    protected $conditionDataFactory;

    /**
     * @var DataObjectProcessor
     */
    protected $dataObjectProcessor;

    /**
     * @var NewAction
     */
    protected $newActionController;

    /**
     * Subscriber factory
     *
     * @var SubscriberFactory
     */
    protected $_subscriberFactory;

    /**
     * @var LayoutFactory
     */
    protected $resultLayoutFactory;

    /**
     * @var SessionManagerInterface
     */
    protected $session;

    /**
     * @var State
     */
    protected $appState;

    /**
     * ThankYouPageRepository constructor.
     *
     * @param Data $helperData
     * @param CollectionProcessorInterface $collectionProcessor
     * @param SearchResultsInterfaceFactory $searchResultsFactory
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param CollectionFactory $collectionFactory
     * @param TemplateFactory $templateFactory
     * @param ResourceModel $resourceModel
     * @param ConditionInterfaceFactory $conditionDataFactory
     * @param DataObjectProcessor $dataObjectProcessor
     * @param NewAction $newActionController
     * @param SubscriberFactory $subscriberFactory
     * @param LayoutFactory $resultLayoutFactory
     * @param SessionManagerInterface $session
     * @param State $appState
     */
    public function __construct(
        Data $helperData,
        CollectionProcessorInterface $collectionProcessor,
        SearchResultsInterfaceFactory $searchResultsFactory,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        CollectionFactory $collectionFactory,
        TemplateFactory $templateFactory,
        ResourceModel $resourceModel,
        ConditionInterfaceFactory $conditionDataFactory,
        DataObjectProcessor $dataObjectProcessor,
        NewAction $newActionController,
        SubscriberFactory $subscriberFactory,
        LayoutFactory $resultLayoutFactory,
        SessionManagerInterface $session,
        State $appState
    ) {
        $this->helperData            = $helperData;
        $this->collectionProcessor   = $collectionProcessor;
        $this->searchResultsFactory  = $searchResultsFactory;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->collectionFactory     = $collectionFactory;
        $this->templateFactory       = $templateFactory;
        $this->resourceModel         = $resourceModel;
        $this->conditionDataFactory  = $conditionDataFactory;
        $this->dataObjectProcessor   = $dataObjectProcessor;
        $this->newActionController   = $newActionController;
        $this->_subscriberFactory    = $subscriberFactory;
        $this->resultLayoutFactory   = $resultLayoutFactory;
        $this->session               = $session;
        $this->appState              = $appState;
    }

    /**
     * @inheritDoc
     */
    public function save(ThankYouPageInterface $template)
    {
        if (!$this->helperData->isEnabled()) {
            throw new NoSuchEntityException(__('The module is disabled'));
        }

        /** @var Template $model */
        $model  = $this->templateFactory->create();
        $ruleId = $template->getId();
        if ($ruleId) {
            $this->resourceModel->load($model, $ruleId);
            if ((int) $model->getId() !== $ruleId) {
                throw new NoSuchEntityException();
            }
        }

        $modelData = $model->getData();
        $data      = $this->dataObjectProcessor->buildOutputDataArray(
            $template,
            ThankYouPageInterface::class
        );

        if (isset($data['static_block1'])) {
            $data['static_block_1'] = $data['static_block1'];
        }
        if (isset($data['static_block2'])) {
            $data['static_block_2'] = $data['static_block2'];
        }

        $mergedData     = array_merge($modelData, $data);
        $validateResult = $this->validateData($mergedData);
        if ($validateResult !== true) {
            $text = '';
            /** @var Phrase $errorMessage */
            foreach ($validateResult as $errorMessage) {
                $text .= $errorMessage->getText();
                $text .= '; ';
            }
            throw new InputException(new Phrase($text));
        }

        $model->setData($mergedData);
        $condition = $template->getCondition();
        if ($condition) {
            $array = $this->dataModelToArray($condition);
            $model->getConditions()->setConditions([])->loadArray($array);
        }
        $this->resourceModel->save($model);

        return $this->getById($model->getId());
    }

    /**
     * @inheritDoc
     */
    public function getList(?SearchCriteriaInterface $searchCriteria = null)
    {
        if (!$this->helperData->isEnabled()) {
            throw new NoSuchEntityException(__('The module is disabled'));
        }

        if ($searchCriteria === null) {
            $searchCriteria = $this->searchCriteriaBuilder->create();
        }
        /** @var Collection $collection */
        $collection = $this->collectionFactory->create();
        $this->collectionProcessor->process($searchCriteria, $collection);
        foreach ($collection->getItems() as $item) {
            /** @var Template $item */
            $this->mapConditions($item);
        }
        /** @var SearchResultsInterface $searchResult */
        $searchResult = $this->searchResultsFactory->create();
        $searchResult->setSearchCriteria($searchCriteria);
        $searchResult->setItems($collection->getItems());
        $searchResult->setTotalCount($collection->getSize());

        return $searchResult;
    }

    /**
     * @inheritDoc
     */
    public function getById($ruleId)
    {
        if (!$this->helperData->isEnabled()) {
            throw new NoSuchEntityException(__('The module is disabled'));
        }
        /** @var Template $model */
        $model = $this->templateFactory->create();
        $this->resourceModel->load($model, $ruleId);

        if ((int) $model->getId() !== (int) $ruleId) {
            throw new NoSuchEntityException(__('Rule with ID %1 doesn\'t exist', $ruleId));
        }
        $this->mapConditions($model);

        return $model;
    }

    /**
     * @inheritDoc
     */
    public function delete($ruleId)
    {
        if (!$this->helperData->isEnabled()) {
            throw new NoSuchEntityException(__('The module is disabled'));
        }
        $model = $this->getById($ruleId);
        try {
            $this->resourceModel->delete($model);

            return true;
        } catch (Exception $e) {
            throw new CouldNotSaveException(__('The template data is invalid. Verify the data and try again.'));
        }
    }

    /**
     * @inheritDoc
     */
    public function getSubsPage($email, $storeId = null, $customerGroup = null)
    {
        if (!$this->helperData->isEnabled() || !$this->helperData->isNewsletterSuccessPageEnable()) {
            throw new NoSuchEntityException(__('The module is disabled'));
        }
        if (!$this->helperData->isNewsletterSuccessPageEnable($storeId)) {
            throw new NoSuchEntityException(__('The subscribe success page is disabled'));
        }

        $this->newActionController->validateEmail($email);
        $subscriber = $this->_subscriberFactory->create()->loadByEmail($email);
        if ($subscriber->getId()
            && (int) $subscriber->getSubscriberStatus() === Subscriber::STATUS_SUBSCRIBED
        ) {
            throw new LocalizedException(
                __('This email address is already subscribed.')
            );
        }

        $status   = $this->_subscriberFactory->create()->subscribe($email);
        $template = $this->helperData->getTemplateData(Pagetype::NEWSLETTER, $storeId, $customerGroup);

        if ($status && $template) {
            if ($template->getEnableCoupon()) {
                $couponCode = $this->helperData->getCouponCode(Pagetype::NEWSLETTER, $storeId, $customerGroup);
                $this->session->setData('coupon_code', $couponCode);
            }
            $layout = $this->resultLayoutFactory->create();
            $html   = $this->emulateArea(
                $template,
                $layout,
                [
                    'class'        => Subscribe::class,
                    'name'         => 'mpthankyoupage.subscribe.success',
                    'templateHtml' => 'Mageplaza_ThankYouPage::subscribe/demo/' . $template->getStyle() . '.phtml',
                    'storeId'      => $storeId
                ]
            );
            $css    = $this->emulateArea(
                $template,
                $layout,
                [
                    'class'        => Subscribe::class,
                    'name'         => 'mpthankyoupage.newsletter.design',
                    'templateHtml' => 'Mageplaza_ThankYouPage::subscribe/design.phtml',
                    'storeId'      => $storeId
                ]
            );
            $html   = '<div class="mpthankyoupage newsletter">' . $html . '</div>' . $css;

            /** @var Subscribe $block */
            $block = $layout->getLayout()
                ->createBlock(Subscribe::class)->setTemplateData($template);

            $template->setTitle($this->minifyHtml($block->getPageTitle()));
            $template->setSubTitle($this->minifyHtml($block->getPageSubTitle()));
            $template->setDescription($this->minifyHtml($block->getPageDescription()));
            $template->setCouponLabel($this->minifyHtml($block->getCouponHtml()));
            $template->setCustomCss($this->minifyHtml($template->getCustomCss()));
            $template->setCustomHtml($this->minifyHtml($template->getCustomHtml()));
            $template->setHtml($this->minifyHtml($html));

            return $template;
        }

        return [];
    }

    /**
     * @inheritDoc
     */
    public function getOrderPage($orderId)
    {
        if (!$this->helperData->isEnabled()) {
            throw new NoSuchEntityException(__('The module is disabled'));
        }

        if (!$orderId) {
            throw new InputException(__('An Order ID is needed. Set the Order ID and try again.'));
        }

        $layout       = $this->resultLayoutFactory->create();
        $templateData = $this->appState->emulateAreaCode(
            Area::AREA_FRONTEND,
            function () use ($layout, $orderId) {
                /** @var OrderSuccess $block */
                $block = $this->getBlock(
                    $layout,
                    OrderSuccess::class,
                    'mpthankyoupage.order.success',
                    'Mageplaza_ThankYouPage::order/success.phtml',
                    null,
                    $orderId
                );

                $templateData = $block->getTemplateData();
                if (!$templateData) {
                    return false;
                }
                if (!$block->isEnable()) {
                    return false;
                }
                $simpleTemplate   = $this->getBlock(
                    $layout,
                    OrderSuccess::class,
                    'mpthankyoupage.order.success.simple',
                    'Mageplaza_ThankYouPage::order/simple.phtml',
                    null,
                    $orderId
                );
                $complexTemplate  = $this->getBlock(
                    $layout,
                    OrderSuccess::class,
                    'mpthankyoupage.order.success.complex',
                    'Mageplaza_ThankYouPage::order/complex.phtml',
                    null,
                    $orderId
                );
                $additionTemplate = $this->getBlock(
                    $layout,
                    OrderSuccess::class,
                    'mpthankyoupage.order.additional',
                    'Mageplaza_ThankYouPage::order/additional.phtml',
                    null,
                    $orderId
                );
                $detailTemplate   = $this->getBlock(
                    $layout,
                    OrderSuccess::class,
                    'mpthankyoupage.order.detail',
                    'Mageplaza_ThankYouPage::order/detail.phtml',
                    null,
                    $orderId
                );

                $block->setChild('simple', $simpleTemplate);
                $block->setChild('complex', $complexTemplate);
                $block->setChild('additional', $additionTemplate);
                $block->setChild('order_detail', $detailTemplate);

                $templateData->setTitle($this->minifyHtml($block->getPageTitle()));
                $templateData->setSubTitle($this->minifyHtml($block->getPageSubTitle()));
                $templateData->setDescription($this->minifyHtml($block->getPageDescription()));
                $templateData->setCouponLabel($this->minifyHtml($block->getCouponHtml()));
                $templateData->setCustomCss($this->minifyHtml($templateData->getCustomCss()));
                $templateData->setCustomHtml($this->minifyHtml($templateData->getCustomHtml()));
                $templateData->setHtml($this->minifyHtml($block->toHtml()));

                return $templateData;
            }
        );
        if (!$templateData) {
            throw new NoSuchEntityException(
                __("The template that was requested doesn't exist or the order success page is disabled. Verify the orderId and try again.")
            );
        }
        $css = $this->appState->emulateAreaCode(
            Area::AREA_FRONTEND,
            function () use ($layout, $orderId) {
                /** @var Subscribe $block */
                $block = $this->getBlock(
                    $layout,
                    OrderSuccess::class,
                    'mpthankyoupage.design',
                    'Mageplaza_ThankYouPage::design.phtml',
                    null,
                    $orderId
                );

                return $block->toHtml();
            }
        );
        $templateData->setHtml($this->minifyHtml($templateData->getHtml() . $css));

        return $templateData;
    }

    /**
     * @param Layout $layout
     * @param $class
     * @param string $name
     * @param string $templateHtml
     * @param null $template
     * @param null $orderId
     *
     * @return mixed
     */
    public function getBlock($layout, $class, $name, $templateHtml, $template = null, $orderId = null)
    {
        $block = $layout->getLayout()
            ->createBlock($class, $name);
        if ($template) {
            $block->setTemplateData($template);
        }
        if ($orderId) {
            $block->setOrder($orderId);
        }
        $block->setTemplate($templateHtml);

        return $block;
    }

    /**
     * @param Template $template
     * @param Layout $layout
     * @param array $data
     *
     * @return mixed
     * @throws Exception
     */
    public function emulateArea($template, $layout, $data = [])
    {
        $html = $this->appState->emulateAreaCode(
            Area::AREA_FRONTEND,
            function () use ($template, $layout, $data) {
                $block = $this->getBlock(
                    $layout,
                    $data['class'],
                    $data['name'],
                    $data['templateHtml'],
                    $template
                );
                $block->setStoreId($data['storeId']);

                return $block->toHtml();
            }
        );

        return $html;
    }

    /**
     * @param Template $ruleModel
     *
     * @return $this
     */
    public function mapConditions(Template $ruleModel)
    {
        $conditionSerialized = $ruleModel->getConditionsSerialized();
        if ($conditionSerialized) {
            $conditionArray     = $this->helperData->unserialize($conditionSerialized);
            $conditionDataModel = $this->arrayToConditionDataModel($conditionArray);
            $ruleModel->setCondition($conditionDataModel);
        } else {
            $ruleModel->setCondition();
        }

        return $this;
    }

    /**
     * Convert recursive array into condition data model
     *
     * @param array $input
     *
     * @return Condition
     */
    public function arrayToConditionDataModel(array $input)
    {
        /** @var Condition $conditionDataModel */
        $conditionDataModel = $this->conditionDataFactory->create();
        foreach ($input as $key => $value) {
            switch ($key) {
                case 'type':
                    $conditionDataModel->setConditionType($value);
                    break;
                case 'attribute':
                    $conditionDataModel->setAttributeName($value);
                    break;
                case 'operator':
                    $conditionDataModel->setOperator($value);
                    break;
                case 'value':
                    $conditionDataModel->setValue($value);
                    break;
                case 'aggregator':
                    $conditionDataModel->setAggregatorType($value);
                    break;
                case 'conditions':
                    $conditions = [];
                    foreach ($value as $condition) {
                        $conditions[] = $this->arrayToConditionDataModel($condition);
                    }
                    $conditionDataModel->setConditions($conditions);
                    break;
                default:
            }
        }

        return $conditionDataModel;
    }

    /**
     * @param Condition $condition
     *
     * @return array
     */
    public function dataModelToArray(Condition $condition)
    {
        $output              = [];
        $output['type']      = $condition->getConditionType();
        $output['value']     = $condition->getValue();
        $output['attribute'] = $condition->getAttributeName();
        $output['operator']  = $condition->getOperator();

        if ($condition->getAggregatorType()) {
            $output['aggregator'] = $condition->getAggregatorType();
        }
        if ($condition->getConditions()) {
            $conditions = [];
            foreach ($condition->getConditions() as $subCondition) {
                $conditions[] = $this->dataModelToArray($subCondition);
            }
            $output['conditions'] = $conditions;
        }

        return $output;
    }

    /**
     * Validate data input
     *
     * @param array $data
     *
     * @return bool|string[] - return true if validation passed successfully. Array with errors description otherwise
     */
    public function validateData($data)
    {
        $result         = [];
        $requiredFields = [
            ThankYouPageInterface::NAME,
            ThankYouPageInterface::STATUS,
            ThankYouPageInterface::STORE_IDS,
            ThankYouPageInterface::CUSTOMER_GROUP_IDS,
            ThankYouPageInterface::PAGE_TYPE,
            ThankYouPageInterface::STYLE
        ];
        foreach ($requiredFields as $requiredField) {
            if (!isset($data[$requiredField]) || $data[$requiredField] === '' || $data[$requiredField] === null) {
                $result[] = __('Missing field: ') . $requiredField;
            }
        }

        if (isset($data[ThankYouPageInterface::STATUS])) {
            $status = (int) $data[ThankYouPageInterface::STATUS];
            if ($status !== 0 && $status !== 1) {
                $result[] = __('The status value must have a value of 0 or 1');
            }
        }
        if (isset($data[ThankYouPageInterface::PAGE_TYPE])) {
            $pageType = $data[ThankYouPageInterface::PAGE_TYPE];
            if ($pageType !== Pagetype::ORDER && $pageType !== Pagetype::NEWSLETTER) {
                $result[] = __('page_type value must have a value of order or newsletter');
            }

            if (isset($data[ThankYouPageInterface::STYLE])) {
                $style = $data[ThankYouPageInterface::STYLE];
                if ($pageType === Pagetype::ORDER
                    && !in_array($style, [OrderPageStyle::SIMPLE, OrderPageStyle::COMPLEX], true)
                ) {
                    $result[] = __('style value for order success page must have a value of simple or complex');
                }
                if ($pageType === Pagetype::NEWSLETTER
                    && !in_array(
                        $style,
                        [NewsletterPageStyle::STYLE1, NewsletterPageStyle::STYLE2, NewsletterPageStyle::STYLE3],
                        true
                    )
                ) {
                    $result[] = __('style value for subscribe success page must have a value of style1 or style2 or style3');
                }
            }

            if (isset($data[ThankYouPageInterface::CUSTOM_STYLE])
                || ($pageType === Pagetype::ORDER && !isset($data[ThankYouPageInterface::CUSTOM_STYLE]))
            ) {
                $customStyle = (int) $data[ThankYouPageInterface::CUSTOM_STYLE];
                if ($customStyle !== 0 && $customStyle !== 1) {
                    $result[] = __('custom_style value must have a value of 0 or 1');
                }
            } elseif (!isset($data[ThankYouPageInterface::CUSTOM_STYLE]) && $pageType === Pagetype::ORDER) {
                $result[] = __('Missing custom_style field.');
            }
        }

        return !empty($result) ? $result : true;
    }

    /**
     * @param string $content
     *
     * @return string
     */
    public function minifyHtml($content)
    {
        $search = [
            '/\>[^\S ]+/s',     // strip whitespaces after tags, except space
            '/[^\S ]+\</s',     // strip whitespaces before tags, except space
            '/(\s)+/s',         // shorten multiple whitespace sequences
            '/<!--(.|\s)*?-->/', // Remove HTML comments
            '/(")/', // Remove HTML comments
        ];

        $replace = ['>', '<', '\\1', '', "'"];

        return preg_replace($search, $replace, $content);
    }
}
