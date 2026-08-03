<?php
namespace Magezon\Core\Model;

class RequestProcessor
{
    protected $resource;
    protected $request;

    public function __construct(
        \Magento\Framework\App\ResourceConnection $resource,
        \Magento\Framework\App\RequestInterface $request
    ) {
        $this->resource = $resource;
        $this->request = $request;
    }

    public function getSelectCountSql(\Magento\Framework\DB\Select $select, string $mainField)
    {
        $countSelect = clone $select;
        $countSelect->reset(\Magento\Framework\DB\Select::ORDER);
        $countSelect->reset(\Magento\Framework\DB\Select::LIMIT_COUNT);
        $countSelect->reset(\Magento\Framework\DB\Select::LIMIT_OFFSET);
        $countSelect->reset(\Magento\Framework\DB\Select::COLUMNS);

        $part = $select->getPart(\Magento\Framework\DB\Select::GROUP);
        if (!is_array($part) || !count($part)) {
            $countSelect->columns(new \Zend_Db_Expr('COUNT(*)'));
            return $countSelect;
        }

        $countSelect->reset(\Magento\Framework\DB\Select::GROUP);
        $group = $select->getPart(\Magento\Framework\DB\Select::GROUP);
        $countSelect->columns(new \Zend_Db_Expr(("COUNT(DISTINCT ".implode(", ", $group).")")));
        return $countSelect;
    }

    /**
     * Parse multipart/form-data manually (since $_FILES may not work for PUT)
     */
    public function getPost() {
        $rawData = file_get_contents('php://input');

        if (!$rawData) {
            return [];
        }
        
        $result = [];
        $boundary = substr($rawData, 0, strpos($rawData, "\r\n")); // Extract boundary
        $parts = explode($boundary, $rawData); // Split by boundary

        foreach ($parts as $part) {
            // Skip empty parts or the final boundary
            if (trim($part) === '' || trim($part) === '--') {
                continue;
            }

            // Extract Content-Disposition and value
            preg_match('/Content-Disposition: form-data; name="([^"]+)"\r\n\r\n(.*)\r\n/', $part, $matches);
            if (isset($matches[1]) && isset($matches[2])) {
                $name = $matches[1];
                $value = $matches[2];

                // Parse nested keys (e.g., meta[persisted_preferences][core/ai-content-generator][textSettings][language])
                $keys = [];
                preg_match_all('/\[([^\]]*)\]/', $name, $keyMatches);
                $baseKey = explode('[', $name)[0];
                $keys[] = $baseKey;
                if (!empty($keyMatches[1])) {
                    $keys = array_merge($keys, array_filter($keyMatches[1]));
                }

                // Build nested array
                $current = &$result;
                foreach ($keys as $key) {
                    if (!isset($current[$key])) {
                        $current[$key] = [];
                    }
                    $current = &$current[$key];
                }
                $current = $value;
            }
        }

        return $result;
        // $data = [];
        // $rawInput = file_get_contents('php://input');
        // $contentType = $_SERVER['CONTENT_TYPE'];
        
        // // Extract boundary from Content-Type
        // preg_match('/boundary=(.*)$/', $contentType, $matches);
        // $boundary = $matches[1] ?? null;

        // if (!$boundary) {
        //     return $data;
        // }

        // // Split the raw input by boundary
        // $parts = array_filter(explode('--' . $boundary, $rawInput));

        // foreach ($parts as $part) {
        //     if (trim($part) === '' || trim($part) === '--') {
        //         continue;
        //     }

        //     // Extract headers and content
        //     if (preg_match('/Content-Disposition:.*name="([^"]+)"(?:; filename="([^"]+)")?.*?\r\n\r\n([\s\S]*?)(?=\r\n--|$)/s', $part, $matches)) {
        //         $fieldName = $matches[1];
        //         $fileName = $matches[2] ?? null;
        //         $content = $matches[3];

        //         if ($fileName) {
        //             // Handle file
        //             $tmpPath = sys_get_temp_dir() . '/' . uniqid() . '_' . $fileName;
        //             file_put_contents($tmpPath, $content);
        //             $_FILES[$fieldName] = [
        //                 'name' => $fileName,
        //                 'tmp_name' => $tmpPath,
        //                 'size' => strlen($content),
        //                 'error' => UPLOAD_ERR_OK
        //             ];
        //         } else {
        //             // Handle regular field
        //             $data[$fieldName] = trim($content);
        //         }
        //     }
        // }

        // return $data;
    }

    public function processListRequestSelect(array $request, \Magento\Framework\DB\Select $select, string $mainField, string $titleField = '')
    {
        $order = isset($request['order']) ? $request['order'] : null;
        $orderby = isset($request['orderby']) ? $request['orderby'] : null;

        $search = isset($request['search']) ? trim($request['search']) : '';
        if ($search && !empty($titleField)) {
            $searchRaw = str_replace(['_', '%', '\\'], ['\\_', '\\%', '\\\\'], $search);
            $select->where("$titleField LIKE ?", "%$searchRaw%");
        }

        if ($orderby) {
            $select->orderBy("$orderby $order");
        }

        $perPage = (int) ($request['per_page'] ?? 50) ?: 50;
        $page = (int) ($request['page'] ?? 1) ?: 1;
        $select->limitPage(($page - 1) * $perPage, $perPage);

        $connection = $this->resource->getConnection();

        // Get total records count
        $countSql = $this->getSelectCountSql($select, $mainField)->limit(1)->assemble();
        $totalRecords = $connection->fetchOne($countSql);

        $items = $connection->fetchAll($select->assemble());

        $totalPages = ceil($totalRecords / $perPage);

        return [
            'items' => $items,
            'totalPages' => $totalPages,
            'totalRecords' => $totalRecords
        ];

    }

    public function processListRequestSelect2(array $request, \Magento\Framework\Data\Collection\AbstractDb $collection, string $mainField, string $titleField = '')
    {
        $order = isset($request['order']) ? $request['order'] : null;
        $orderby = isset($request['orderby']) ? $request['orderby'] : null;

        $search = isset($request['search']) ? trim($request['search']) : '';
        if ($search && !empty($titleField)) {
            $searchRaw = str_replace(['_', '%', '\\'], ['\\_', '\\%', '\\\\'], $search);
            $collection->getSelect()->where("$titleField LIKE ?", "%$searchRaw%");
        }

        if ($orderby) {
            $collection->getSelect()->orderBy("$orderby $order");
        }

        $perPage = (int) ($request['per_page'] ?? 50) ?: 50;
        $page = (int) ($request['page'] ?? 1) ?: 1;
        $collection->getSelect()->limitPage(($page - 1) * $perPage, $perPage);

        $connection = $this->resource->getConnection();

        // Get total records count
        $countSql = $this->getSelectCountSql($collection->getSelect(), $mainField)->limit(1)->assemble();
        $totalRecords = $connection->fetchOne($countSql);

        $items = [];

        foreach ($collection as $item) {
            $items[] = [
                'id' => (int) $item->getId(),
                'name' => $item->getData($titleField)
            ];
        }

        $totalPages = ceil($totalRecords / $perPage);

        return [
            'items' => $items,
            'totalPages' => $totalPages,
            'totalRecords' => $totalRecords
        ];

    }
}