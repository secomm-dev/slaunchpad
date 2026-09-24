<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\CodRisk\Block\Adminhtml;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Store\Model\WebsiteFactory;
use Secomm\CodRisk\Api\Data\CodRiskDecisionInterface;
use Secomm\CodRisk\Model\Service\PhoneInspector;

/**
 * Phone Inspector page block (mockup Flow B).
 *
 * @method CodRiskDecisionInterface|array|null getResult()
 */
class Inspector extends Template
{
    public function __construct(
        Context $context,
        private readonly PhoneInspector $phoneInspector,
        private readonly WebsiteFactory $websiteFactory,
        array $data = [],
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return array|null Inspect result, null when no phone submitted.
     */
    public function getResult(): ?array
    {
        return $this->phoneInspector->inspect(
            $this->getRequest()->getParam('phone'),
            $this->resolveWebsiteId()
        );
    }

    public function getSubmittedPhone(): string
    {
        return (string)$this->getRequest()->getParam('phone');
    }

    public function getSelectedWebsiteId(): string
    {
        return (string)$this->getRequest()->getParam('website_id', '');
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    public function getWebsiteOptions(): array
    {
        $options = [['label' => (string)__('All Websites'), 'value' => '']];
        foreach ($this->websiteFactory->create()->getCollection() as $website) {
            $options[] = [
                'label' => (string)$website->getName(),
                'value' => (string)(int)$website->getId(),
            ];
        }

        return $options;
    }

    private function resolveWebsiteId(): ?int
    {
        $param = $this->getRequest()->getParam('website_id', '');

        return $param === '' || $param === null ? null : (int)$param;
    }
}
