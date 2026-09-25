<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Launchpad\MageplazaTableRate\Test\Unit\Plugin\ResourceModel;

use Magento\Framework\Model\AbstractModel;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Launchpad\MageplazaTableRate\Model\Adminhtml\SettingsCapture;
use Launchpad\MageplazaTableRate\Model\Adminhtml\SettingsPersister;
use Launchpad\MageplazaTableRate\Plugin\ResourceModel\MethodResourcePlugin;

/**
 * TASK-SEC-C1 — the resource seam persists against the JUST-SAVED model's own id (the
 * canonical identity: concurrent creates each carry their own object) and cascades
 * extension-row deletion on method delete.
 */
class MethodResourcePluginTest extends TestCase
{
    private SettingsCapture $capture;

    private SettingsPersister&MockObject $persister;

    private MethodResourcePlugin $plugin;

    protected function setUp(): void
    {
        $this->capture = new SettingsCapture();
        $this->persister = $this->createMock(SettingsPersister::class);
        $this->plugin = new MethodResourcePlugin($this->capture, $this->persister);
    }

    private function methodObject(int $id): AbstractModel
    {
        $object = $this->createMock(AbstractModel::class);
        $object->method('getId')->willReturn($id);

        return $object;
    }

    public function testCreatePersistsAgainstTheNewlyAssignedId(): void
    {
        // CREATE: the id only exists on the object AFTER save (here: id 42, request had none).
        $this->capture->capture(['use_as_fallback' => '1']);
        $this->persister->expects($this->once())->method('saveMethodSettings')->with(42, ['use_as_fallback' => '1']);

        $this->plugin->afterSave(null, null, $this->methodObject(42));
    }

    public function testUpdatePersistsTheSameWay(): void
    {
        $this->capture->capture(['show_to_customer' => '0', 'members' => []]);
        $this->persister->expects($this->once())->method('saveMethodSettings')->with(7, ['show_to_customer' => '0', 'members' => []]);

        $this->plugin->afterSave(null, null, $this->methodObject(7));
    }

    public function testNoPayloadNeverTouchesThePersister(): void
    {
        $this->persister->expects($this->never())->method('saveMethodSettings');

        $this->plugin->afterSave(null, null, $this->methodObject(7));
    }

    public function testPayloadIsConsumedExactlyOnce(): void
    {
        $this->capture->capture(['use_as_fallback' => '1']);
        $this->persister->expects($this->once())->method('saveMethodSettings');

        $this->plugin->afterSave(null, null, $this->methodObject(1));
        // A second save in the same request without a new capture must NOT persist.
        $this->plugin->afterSave(null, null, $this->methodObject(2));
    }

}
