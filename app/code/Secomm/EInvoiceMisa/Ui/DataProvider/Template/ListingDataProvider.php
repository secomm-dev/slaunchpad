<?php

declare(strict_types=1);

namespace Secomm\EInvoiceMisa\Ui\DataProvider\Template;

use Magento\Framework\Api\Filter;
use Magento\Framework\App\RequestInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;
use Secomm\EInvoiceMisa\Model\Config\MisaConfig;
use Secomm\EInvoiceMisa\Model\Template\InvoiceTemplate;
use Secomm\EInvoiceMisa\Model\Template\InvoiceTemplateListProvider;

/**
 * Read-only data provider feeding the MeInvoice template admin grid.
 */
class ListingDataProvider extends AbstractDataProvider
{
    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param InvoiceTemplateListProvider $listProvider
     * @param MisaConfig $config
     * @param RequestInterface $request
     * @param array<string, mixed> $meta
     * @param array<string, mixed> $data
     */
    public function __construct(
        string $name,
        string $primaryFieldName,
        string $requestFieldName,
        private readonly InvoiceTemplateListProvider $listProvider,
        private readonly MisaConfig $config,
        private readonly RequestInterface $request,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    /**
     * @inheritdoc
     *
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        $storeParam = $this->request->getParam('store');
        $storeId = $storeParam !== null && $storeParam !== '' ? (int) $storeParam : null;

        $defaultId = $this->config->getInvoiceTemplateId($storeId);
        $items = [];
        foreach ($this->listProvider->getList($storeId)->getItems() as $index => $template) {
            $items[] = $this->mapRow($index, $template, $defaultId);
        }

        return [
            'totalRecords' => count($items),
            'items' => $items,
        ];
    }

    /**
     * No-op: this provider is backed by an API list, not a DB collection.
     *
     * @param Filter $filter
     * @return void
     */
    public function addFilter(Filter $filter): void
    {
    }

    /**
     * No-op: sorting is not supported for the API-backed list.
     *
     * @param string $field
     * @param string $direction
     * @return void
     */
    public function addOrder($field, $direction): void
    {
    }

    /**
     * No-op: paging is not applied to the API-backed list.
     *
     * @param int $offset
     * @param int $size
     * @return void
     */
    public function setLimit($offset, $size): void
    {
    }

    /**
     * Map a template value object to a grid row.
     *
     * @param int $index
     * @param InvoiceTemplate $template
     * @param string $defaultId
     * @return array<string, mixed>
     */
    private function mapRow(int $index, InvoiceTemplate $template, string $defaultId): array
    {
        return [
            'id' => $index + 1,
            'ip_template_id' => $template->getIpTemplateId(),
            'inv_series' => $template->getInvSeries(),
            'template_name' => $template->getTemplateName(),
            'status' => $template->isInactive() ? (string) __('Inactive') : (string) __('Active'),
            'is_default' => $template->getIpTemplateId() === $defaultId && $defaultId !== ''
                ? (string) __('Yes')
                : (string) __('No'),
        ];
    }
}
