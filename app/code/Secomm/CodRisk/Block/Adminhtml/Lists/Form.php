<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\CodRisk\Block\Adminhtml\Lists;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\WebsiteFactory;
use Secomm\CodRisk\Model\CodRiskList;
use Secomm\CodRisk\Model\Source\ActivationStatus;
use Secomm\CodRisk\Model\Source\ListTypes;
use Secomm\CodRisk\Model\Source\ReasonCodes;

/**
 * Phone list record form (new/edit). Rendered as a standard admin form page —
 * the mockup modal maps to this page (SPEC-TASK-YPWH9B §6).
 *
 * @method CodRiskList|null getRecord()
 */
class Form extends Template
{
    public function __construct(
        Context $context,
        private readonly Registry $registry,
        private readonly ListTypes $listTypes,
        private readonly ReasonCodes $reasonCodes,
        private readonly ActivationStatus $activationStatus,
        private readonly WebsiteFactory $websiteFactory,
        private readonly TimezoneInterface $localeDate,
        private readonly UrlInterface $urlBuilder,
        array $data = [],
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Render a stored UTC timestamp as the website's local "Y-m-d H:i:s" for the
     * calendar input. Uses the SAME scope (website) as ListManager::toDateTime()
     * on save — asymmetric scopes would drift the value 7h per edit-save cycle.
     * Deliberately NOT TimezoneInterface::date(): its DateTimeImmutable branch
     * (vendor Timezone.php:189) re-wraps the value WITHOUT converting timezone.
     */
    public function formatEffective(string $utcValue, int $websiteId = 0): string
    {
        if ($utcValue === '' || $utcValue === '0000-00-00 00:00:00') {
            return '';
        }

        $date = new \DateTime($utcValue, new \DateTimeZone('UTC'));
        $date->setTimezone(new \DateTimeZone($this->localeDate->getConfigTimezone(
            ScopeInterface::SCOPE_WEBSITE,
            (string)$websiteId
        )));

        return $date->format('Y-m-d H:i:s');
    }

    public function getRecord(): ?CodRiskList
    {
        $record = $this->registry->registry('secomm_codrisk_list');

        return $record instanceof CodRiskList ? $record : null;
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    public function getListTypeOptions(): array
    {
        return $this->listTypes->toOptionArray();
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    public function getReasonOptions(): array
    {
        return $this->reasonCodes->toOptionArray();
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    public function getWebsiteOptions(): array
    {
        $options = [['label' => (string)__('All Websites'), 'value' => '0']];
        foreach ($this->websiteFactory->create()->getCollection() as $website) {
            $options[] = [
                'label' => (string)$website->getName(),
                'value' => (string)(int)$website->getId(),
            ];
        }

        return $options;
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    public function getStatusOptions(): array
    {
        return $this->activationStatus->toOptionArray();
    }

    public function getSaveUrl(): string
    {
        $record = $this->getRecord();

        return $this->urlBuilder->getUrl('codrisk/lists/save', [
            'list_id' => $record !== null ? (int)$record->getId() : null,
        ]);
    }

    public function getBackUrl(): string
    {
        return $this->urlBuilder->getUrl('codrisk/lists/index');
    }
}
