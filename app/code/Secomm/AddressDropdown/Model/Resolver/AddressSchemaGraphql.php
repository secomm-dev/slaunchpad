<?php
declare(strict_types=1);
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Secomm\AddressDropdown\Api\AddressProfileResolverInterface;
use Secomm\AddressDropdown\Api\AddressSchemaProviderInterface;
use Secomm\AddressDropdown\Api\Data\SchemaLevelInterface;
use Secomm\AddressDropdown\Api\NoSuchProfileException;
use Secomm\AddressDropdown\Model\Profile\ProfilePool;

/**
 * FEAT-2PZQKJ / TASK-J49PRZ — schema endpoint for renderers.
 *
 * query { addressSchema(input: { country_id: "VN" }) { ... } }                 — profile from config
 * query { addressSchema(input: { country_id: "VN", profile_code: "vn_admin_pre_2025" }) { ... } } — explicit
 *
 * Labels/placeholders are translated server-side through the current store locale, so the
 * translation lives with the profile-declaring module's i18n (DEC-FEATJSZQV3-003) — frontends
 * render the returned strings verbatim and NEVER infer labels from depth.
 * Unmapped country => empty array => native Magento address fields.
 */
class AddressSchemaGraphql implements ResolverInterface
{
    public function __construct(
        private readonly AddressProfileResolverInterface $profileResolver,
        private readonly AddressSchemaProviderInterface $schemaProvider,
        private readonly ProfilePool $profilePool
    ) {
    }

    /**
     * @inheritDoc
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ): array {
        $input = $args['input'] ?? [];
        $countryId = (string)($input['country_id'] ?? '');
        $profileCode = isset($input['profile_code']) ? (string)$input['profile_code'] : null;

        if ($countryId === '') {
            throw new GraphQlInputException(__('"country_id" is required.'));
        }

        if ($profileCode !== null) {
            try {
                $profile = $this->profilePool->getProfile($profileCode);
            } catch (NoSuchProfileException $exception) {
                throw new GraphQlNoSuchEntityException(__($exception->getMessage()), $exception);
            }
        } else {
            $profile = $this->profileResolver->resolve($countryId);
            if ($profile === null) {
                return ['profile_code' => null, 'levels' => []]; // unmapped => native fallback
            }
        }

        return [
            'profile_code' => $profile->getCode(),
            'levels' => array_map(
                static fn (SchemaLevelInterface $level): array => [
                    'entity_type' => $level->getEntityType(),
                    'depth' => $level->getDepth(),
                    'label' => (string)__($level->getLabel()),
                    'placeholder' => $level->getPlaceholder() === null
                        ? null
                        : (string)__($level->getPlaceholder()),
                    'sort_order' => $level->getSortOrder(),
                    'required' => $level->isRequired(),
                ],
                $this->schemaProvider->getSchema($profile->getCode())
            ),
        ];
    }
}
