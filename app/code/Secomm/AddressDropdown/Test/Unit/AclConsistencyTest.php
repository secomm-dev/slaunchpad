<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\AddressDropdown\Test\Unit;

use PHPUnit\Framework\TestCase;

/**
 * TASK-SEC-A2 — ACL consistency: every resource referenced by system.xml (section
 * <resource>), menu.xml (resource attribute), ui_component grids (aclResource) and admin
 * controllers (ADMIN_RESOURCE constants) MUST be declared in acl.xml — no orphans, no
 * references that a restricted role could never be granted.
 */
class AclConsistencyTest extends TestCase
{
    private const MODULE_DIR = __DIR__ . '/../../';

    /** @var array<string, true> */
    private array $declared = [];

    public function testEveryReferencedResourceIsDeclared(): void
    {
        $this->collectDeclaredResources();
        $referenced = $this->collectReferencedResources();
        $menuResources = $this->collectMenuResources();

        $this->assertNotSame([], $this->declared, 'acl.xml must declare at least one resource');
        $this->assertNotSame([], $referenced, 'no resource references found — the scan is broken');

        $undeclared = array_diff_key($referenced, $this->declared);

        $this->assertSame(
            [],
            array_keys($undeclared),
            'Resources referenced but NOT declared in acl.xml (orphan references)'
        );

        // The menu root must itself be declared so the top menu node is grantable.
        $this->assertArrayHasKey('Secomm_AddressDropdown::AddressDropdown', $this->declared);
        // menu.xml resources are part of the referenced set — cross-check them explicitly.
        foreach ($menuResources as $resourceId) {
            $this->assertArrayHasKey(
                $resourceId,
                $this->declared,
                'menu.xml references undeclared resource ' . $resourceId
            );
        }
    }

    public function testLegacyResourcesKeepTheirIdentityForBackwardCompatibility(): void
    {
        $this->collectDeclaredResources();

        // Roles saved before the redesign reference exactly these two — they must never rename.
        $this->assertArrayHasKey('Secomm_AddressDropdown::listing', $this->declared);
        $this->assertArrayHasKey('Secomm_AddressDropdown::management', $this->declared);
    }

    private function collectDeclaredResources(): void
    {
        $xml = simplexml_load_file(self::MODULE_DIR . 'etc/acl.xml');
        self::assertNotFalse($xml);
        foreach ($xml->xpath('//resource') ?: [] as $node) {
            $id = (string) $node['id'];
            if (str_starts_with($id, 'Secomm_AddressDropdown::')) {
                $this->declared[$id] = true;
            }
        }
    }

    /**
     * @return array<string, true>
     */
    private function collectReferencedResources(): array
    {
        $refs = [];

        // system.xml section <resource>…</resource>
        $system = simplexml_load_file(self::MODULE_DIR . 'etc/adminhtml/system.xml');
        if ($system !== false) {
            foreach ($system->xpath('//resource') ?: [] as $node) {
                $id = trim((string) $node);
                if (str_starts_with($id, 'Secomm_AddressDropdown::')) {
                    $refs[$id] = true;
                }
            }
        }

        // ui_component grids: <aclResource>…</aclResource>
        foreach (glob(self::MODULE_DIR . 'view/adminhtml/ui_component/*.xml') ?: [] as $file) {
            $xml = simplexml_load_file($file);
            if ($xml === false) {
                continue;
            }
            foreach ($xml->xpath('//aclResource') ?: [] as $node) {
                $id = trim((string) $node);
                if (str_starts_with($id, 'Secomm_AddressDropdown::')) {
                    $refs[$id] = true;
                }
            }
        }

        // Admin controllers: ADMIN_RESOURCE constants + declared ACL tree sources.
        foreach (glob(self::MODULE_DIR . 'Controller/Adminhtml/*/*.php') ?: [] as $file) {
            $code = (string) file_get_contents($file);
            preg_match_all("/Secomm_AddressDropdown::[A-Za-z_]+/", $code, $m);
            foreach ($m[0] as $id) {
                $refs[$id] = true;
            }
        }

        // menu.xml resource attributes are collected separately but belong to the same set.
        foreach ($this->collectMenuResources() as $id) {
            $refs[$id] = true;
        }

        return $refs;
    }

    /**
     * @return string[]
     */
    private function collectMenuResources(): array
    {
        $ids = [];
        $menu = simplexml_load_file(self::MODULE_DIR . 'etc/adminhtml/menu.xml');
        if ($menu === false) {
            return $ids;
        }
        foreach ($menu->xpath('//add') ?: [] as $node) {
            $id = trim((string) $node['resource']);
            if (str_starts_with($id, 'Secomm_AddressDropdown::')) {
                $ids[] = $id;
            }
        }

        return $ids;
    }
}
