<?php

declare(strict_types=1);

namespace Secomm\EInvoiceMisa\Setup\Patch\Data;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Ensures USD stores can convert to VND for MeInvoice (MainCurrency).
 */
class EnsureUsdVndCurrencyRate implements DataPatchInterface
{
    private const DEFAULT_USD_TO_VND = 25400.0;

    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly WriterInterface $configWriter
    ) {
    }

    public function apply(): void
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('directory_currency_rate');

        $this->ensureRate($connection, $table, 'USD', 'VND', self::DEFAULT_USD_TO_VND);
        $this->ensureRate($connection, $table, 'VND', 'USD', round(1 / self::DEFAULT_USD_TO_VND, 12));

        $this->configWriter->save(
            'currency/options/allow',
            'USD,VND',
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT
        );
    }

    private function ensureRate(
        \Magento\Framework\DB\Adapter\AdapterInterface $connection,
        string $table,
        string $from,
        string $to,
        float $rate
    ): void {
        $select = $connection->select()
            ->from($table, ['rate'])
            ->where('currency_from = ?', $from)
            ->where('currency_to = ?', $to);

        $existing = $connection->fetchOne($select);
        if ($existing !== false && (float) $existing > 0) {
            return;
        }

        $connection->insertOnDuplicate(
            $table,
            [
                'currency_from' => $from,
                'currency_to' => $to,
                'rate' => $rate,
            ],
            ['rate']
        );
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
