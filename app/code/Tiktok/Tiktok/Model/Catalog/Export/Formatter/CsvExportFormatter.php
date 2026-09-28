<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Model\Catalog\Export\Formatter;

use Magento\Framework\Exception\NoSuchEntityException;
use Tiktok\Tiktok\Model\Catalog\Export\Formatter\AbstractProductExportFormatter;

/**
 * Catalog Product CSV Export Formatter
 */
class CsvExportFormatter extends AbstractProductExportFormatter
{
    /**
     * @inheritDoc
     */
    public function formatAdditionalImages($productData = null): array|string
    {
        if (empty($productData['media_gallery']['images'])) {
            return '';
        }

        return implode(',', array_map(function ($image) {
            return $this->getImageUrl($image['file'] ?? '');
        }, $productData['media_gallery']['images']));
    }

    /**
     * @inheritDoc
     */
    public function getFormattedPrice($productData = null): string
    {
        return $productData ? number_format((float)$productData, 2) . ' ' . $this->storeManager->getStore()
                ->getCurrentCurrencyCode() : '';
    }

    /**
     * @inheritDoc
     */
    public function getFormattedSalePrice($specialPrice = null, $price = null): string
    {
        if ($specialPrice !== null) {
            return number_format((float)$specialPrice, 2)
                . ' '
                . $this->storeManager->getStore()
                    ->getCurrentCurrencyCode();
        }

        if ($price !== null) {
            return number_format((float)$price, 2)
                . ' '
                . $this->storeManager->getStore()->getCurrentCurrencyCode();
        }

        return '';
    }

    /**
     * Format sales price effective date
     *
     * @param mixed $startDate
     * @param mixed $endDate
     *
     * @return string
     */
    protected function formatSalePriceEffectiveDate(mixed $startDate, mixed $endDate): string
    {
        return date('Y-m-d\TH:i:s', strtotime($startDate)) . '/' . date('Y-m-d\TH:i:s', strtotime($endDate));
    }
}
