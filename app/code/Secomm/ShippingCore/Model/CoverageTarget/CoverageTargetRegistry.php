<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model\CoverageTarget;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Phrase;
use Secomm\ShippingCore\Api\CoverageTarget\CoverageTargetIdentity;
use Secomm\ShippingCore\Api\CoverageTarget\CoverageTargetInterface;
use Secomm\ShippingCore\Api\CoverageTarget\CoverageTargetType;

/**
 * TASK-WY6WP5 — registry of targets that expose the shared Shipping Coverage admin surface
 * (generalizes TASK-G3K9V2's CarrierRegistry to (type, code) identities). Target modules
 * opt in from THEIR OWN module (dependency direction Ghn → ShippingCore) by contributing
 * one di.xml array item — ShippingCore never hardcodes a target identity here, and
 * Magento's stock shipping carriers are NOT auto-discovered (explicit opt-in only).
 *
 * Registration means only "this target supports Shipping Coverage configuration" — it
 * NEVER creates a coverage config (directive §4). With no explicit config the runtime
 * keeps its documented defaults (missing destination_scope → ALL) and the admin shows the
 * target as Not Configured.
 *
 * Registration shape (per target; `type` may be omitted for P1 carrier entries — it
 * normalizes to CARRIER):
 *   <type name="Secomm\ShippingCore\Model\CoverageTarget\CoverageTargetRegistry">
 *       <arguments><argument name="targets" xsi:type="array">
 *           <item name="secomm_ghn" xsi:type="array">
 *               <item name="type" xsi:type="string">CARRIER</item>
 *               <item name="code" xsi:type="string">secomm_ghn</item>
 *               <item name="label" xsi:type="string" translate="true">GHN (Giao Hàng Nhanh)</item>
 *           </item>
 *       </argument></arguments>
 *   </type>
 */
final class CoverageTargetRegistry
{
    /** @var array<string, CoverageTargetInterface> keyed by identity key */
    private array $targets = [];

    /**
     * @param array<string, mixed> $targets di array items keyed by target code, each {type?, code, label}
     */
    public function __construct(array $targets = [])
    {
        foreach ($targets as $key => $entry) {
            $entry = (array) $entry;
            $code = (string) ($entry['code'] ?? $key);
            if ($code === '') {
                continue;
            }
            $type = (string) ($entry['type'] ?? CoverageTargetType::CARRIER);
            // Fail fast on a DI typo — an unknown type must never surface as silent data.
            if (!CoverageTargetType::exists($type)) {
                throw new \InvalidArgumentException(
                    sprintf('Unknown coverage target type "%s" for target "%s".', $type, $code)
                );
            }
            $identity = CoverageTargetIdentity::create($type, $code);
            $this->targets[$identity->key()] = new CoverageTarget(
                $identity,
                (string) ($entry['label'] ?? $code)
            );
        }
        ksort($this->targets);
    }

    /**
     * All registered targets, deterministically ordered by identity key (type, then code).
     *
     * @return array<int, CoverageTargetInterface>
     */
    public function getAll(): array
    {
        return array_values($this->targets);
    }

    /**
     * @return array<int, CoverageTargetInterface>
     */
    public function getAllByType(string $type): array
    {
        return array_values(array_filter(
            $this->targets,
            static fn (CoverageTargetInterface $target): bool => $target->getIdentity()->type() === $type
        ));
    }

    public function has(CoverageTargetIdentity $identity): bool
    {
        return isset($this->targets[$identity->key()]);
    }

    /**
     * @throws NoSuchEntityException target not registered
     */
    public function get(CoverageTargetIdentity $identity): CoverageTargetInterface
    {
        if (!isset($this->targets[$identity->key()])) {
            throw new NoSuchEntityException(new Phrase(
                'Coverage target "%1" of type "%2" is not registered.',
                [$identity->code(), $identity->type()]
            ));
        }

        return $this->targets[$identity->key()];
    }

    public function getLabel(CoverageTargetIdentity $identity): string
    {
        return isset($this->targets[$identity->key()])
            ? $this->targets[$identity->key()]->getLabel()
            : $identity->code();
    }
}
