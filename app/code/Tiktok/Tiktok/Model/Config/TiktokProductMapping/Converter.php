<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Model\Config\TiktokProductMapping;

use DOMDocument;
use Magento\Framework\Config\ConverterInterface;

/**
 * Product Mapping Converter Class
 * @SuppressWarnings(PHPMD.CyclomaticComplexity)
 * @SuppressWarnings(PHPMD.NPathComplexity)
 */
class Converter implements ConverterInterface
{
    /**
     * Convert the XML source to an array format.
     *
     * @param DOMDocument $source
     * @return array
     */
    public function convert($source): array
    {
        $result = [];

        // Ensure we are starting from the root element of the document
        $rootElement = $source->documentElement;

        if ($rootElement && $rootElement->nodeName === 'config') {
            // Get the product_headers element
            foreach ($rootElement->getElementsByTagName('product_headers') as $productHeaders) {
                // Iterate through each header element inside product_headers
                foreach ($productHeaders->getElementsByTagName('header') as $header) {
                    $name = $header->getAttribute('name');

                    // Initialize the result array for this header
                    $result[$name] = [];

                    // Handle optional attributes: csv_name, json_name, export_type
                    $csvName = $header->getAttribute('csv_name');
                    $jsonName = $header->getAttribute('json_name');
                    $exportType = $header->getAttribute('export_type');

                    if ($csvName) {
                        $result[$name]['csv_name'] = $csvName;
                    }

                    if ($jsonName) {
                        $result[$name]['json_name'] = $jsonName;
                    }

                    $result[$name]['export_type'] = $exportType ?: 'both';

                    // Get the attribute elements (can be multiple)
                    $attributeNodes = $header->getElementsByTagName('attribute');
                    foreach ($attributeNodes as $attribute) {
                        $result[$name]['attribute'][] = $attribute->nodeValue;
                    }

                    // Get the formatter element if it exists
                    $formatterNode = $header->getElementsByTagName('formatter')->item(0);
                    if ($formatterNode) {
                        $result[$name]['formatter'] = $formatterNode->nodeValue;
                    }

                    // Get the default element if it exists
                    $defaultNode = $header->getElementsByTagName('default')->item(0);
                    if ($defaultNode) {
                        $result[$name]['default'] = $defaultNode->nodeValue;
                    }

                    // Get the required element and handle enumerated values
                    $requiredNode = $header->getElementsByTagName('required')->item(0);
                    if ($requiredNode) {
                        $requiredValue = $requiredNode->nodeValue;
                        // Ensure it matches the allowed values: csv, json, or both
                        if (in_array($requiredValue, ['csv', 'json', 'both'])) {
                            $result[$name]['required'] = $requiredValue;
                        }
                    }

                    // Get the parent element if it exists
                    $parentNode = $header->getElementsByTagName('parent')->item(0);
                    if ($parentNode) {
                        $result[$name]['parent'] = $parentNode->nodeValue;
                    }
                }
            }
        }

        return $result;
    }
}
