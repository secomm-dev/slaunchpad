<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\FulfillmentCore\Model\Export;

use Secomm\FulfillmentCore\Api\OrderExporterInterface;

/**
 * DI-assembled map of service_code => OrderExporterInterface.
 * Adapters append items in their etc/di.xml; core ships an empty list.
 */
class ExporterPool
{
    /**
     * @param OrderExporterInterface[] $exporters
     */
    public function __construct(
        private readonly array $exporters = []
    ) {
    }

    /**
     * @return OrderExporterInterface[] keyed by service_code
     */
    public function getExporters(): array
    {
        $map = [];
        foreach ($this->exporters as $exporter) {
            if (!$exporter instanceof OrderExporterInterface) {
                continue;
            }
            $map[$exporter->getServiceCode()] = $exporter;
        }

        return $map;
    }

    public function getExporter(string $serviceCode): ?OrderExporterInterface
    {
        return $this->getExporters()[$serviceCode] ?? null;
    }
}
