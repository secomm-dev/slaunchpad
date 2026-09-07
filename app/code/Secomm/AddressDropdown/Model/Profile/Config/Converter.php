<?php
declare(strict_types=1);
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Model\Profile\Config;

use DOMDocument;
use DOMElement;
use Magento\Framework\Config\ConverterInterface;
use Magento\Framework\Exception\LocalizedException;

/**
 * FEAT-2PZQKJ / TASK-NW66H9 — converts merged etc/address_profiles.xml DOM into a plain array:
 *
 * ['profiles' => [
 *     '<code>' => ['code' => ..., 'label' => ..., 'country_id' => ..., 'levels' => [
 *         ['entity_type' => ..., 'depth' => int, 'label' => ..., 'placeholder' => ...,
 *          'sort_order' => int, 'required' => bool, 'translate' => ...],
 *     ]],
 * ]]
 *
 * Config faults fail LOUD here (developer error): a profile must declare exactly one level per
 * (entity_type, depth) — merged files that collide on the same slot throw instead of silently
 * doubling a dropdown level.
 */
class Converter implements ConverterInterface
{
    private const DEFAULT_SORT_ORDER = 100;
    private const DEFAULT_REQUIRED = true;
    private const DEFAULT_PLACEHOLDER = '';
    private const DEFAULT_DEPTH = 0;

    public function convert($source): array
    {
        if (!$source instanceof DOMDocument) {
            throw new LocalizedException(__('Address profile config source must be a DOMDocument.'));
        }

        $profiles = [];
        /** @var DOMElement $profileNode */
        foreach ($source->getElementsByTagName('profile') as $profileNode) {
            $code = (string)$profileNode->getAttribute('code');
            if ($code === '') {
                throw new LocalizedException(__('Address profile declaration is missing its "code" attribute.'));
            }

            $profile = [
                'code' => $code,
                'label' => $profileNode->getAttribute('label') ?: null,
                'country_id' => $profileNode->getAttribute('country') ?: null,
                'levels' => [],
            ];

            $seenSlots = [];
            /** @var DOMElement $levelNode */
            foreach ($profileNode->getElementsByTagName('level') as $levelNode) {
                $entityType = (string)$levelNode->getAttribute('entity_type');
                $depth = $levelNode->hasAttribute('depth')
                    ? (int)$levelNode->getAttribute('depth')
                    : self::DEFAULT_DEPTH;
                $slot = $entityType . ':' . $depth;
                if (isset($seenSlots[$slot])) {
                    throw new LocalizedException(
                        __('Profile "%1" declares more than one level for "%2" — check merged address_profiles.xml files.', $code, $slot)
                    );
                }
                $seenSlots[$slot] = true;

                if ($entityType === 'city' && $depth < 1) {
                    throw new LocalizedException(
                        __('Profile "%1": city levels must declare depth >= 1 (depth is counted from region).', $code)
                    );
                }

                $profile['levels'][] = [
                    'entity_type' => $entityType,
                    'depth' => $depth,
                    'label' => (string)$levelNode->getAttribute('label'),
                    'placeholder' => $levelNode->hasAttribute('placeholder')
                        ? (string)$levelNode->getAttribute('placeholder')
                        : self::DEFAULT_PLACEHOLDER,
                    'sort_order' => $levelNode->hasAttribute('sort_order')
                        ? (int)$levelNode->getAttribute('sort_order')
                        : self::DEFAULT_SORT_ORDER,
                    'required' => $levelNode->hasAttribute('required')
                        ? filter_var($levelNode->getAttribute('required'), FILTER_VALIDATE_BOOL)
                        : self::DEFAULT_REQUIRED,
                    'translate' => $levelNode->getAttribute('translate') ?: null,
                ];
            }

            if (empty($profile['levels'])) {
                throw new LocalizedException(__('Profile "%1" declares no levels.', $code));
            }

            $profiles[$code] = $profile;
        }

        return ['profiles' => $profiles];
    }
}
