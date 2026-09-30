<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Model\Catalog\Export\Formatter;

/**
 * Product JSON Export Formatter
 */
class JsonExportFormatter extends AbstractProductExportFormatter
{
    /**
     * @inheritDoc
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function formatAdditionalImages($productData = null): array|string
    {
        if (empty($productData['media_gallery']['images'])) {
            return [];
        }

        return array_map(function ($image) {
            return $this->getImageUrl($image['file'] ?? '');
        }, $productData['media_gallery']['images']);
    }

    /**
     * @inheritDoc
     */
    public function getFormattedPrice($productData = null): string
    {
        return $productData ? number_format((float)$productData, 2) : '';
    }

    /**
     * @inheritDoc
     */
    public function getFormattedSalePrice($specialPrice = null, $price = null): string
    {
        if ($specialPrice !== null) {
            return number_format((float)$specialPrice, 2);
        }

        if ($price !== null) {
            return number_format((float)$price, 2);
        }

        return '';
    }

    /**
     * Format sales price effective date
     *
     * @param mixed $startDate
     * @param mixed $endDate
     *
     * @return array
     */
    protected function formatSalePriceEffectiveDate(mixed $startDate, mixed $endDate): array
    {
        return [
            'start_date' => date('Y-m-d\TH:i:s', strtotime($startDate)),
            'end_date' => date('Y-m-d\TH:i:s', strtotime($endDate)),];
    }

    /**
     * @inheritDoc
     */
    public function getAvailabilityStatus($productData = null): string
    {
        if (empty($productData)) {
            return 'IN_STOCK';
        }

        return isset($productData['is_in_stock']) && $productData['is_in_stock'] ? 'IN_STOCK' : 'OUT_OF_STOCK';
    }
}
