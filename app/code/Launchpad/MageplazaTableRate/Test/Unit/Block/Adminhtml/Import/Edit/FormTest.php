<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Launchpad\MageplazaTableRate\Test\Unit\Block\Adminhtml\Import\Edit;

use Launchpad\MageplazaTableRate\Block\Adminhtml\Import\Edit\Form;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * TASK-RT50KH — the import modal's sample link must point at the Launchpad import template
 * (which carries the City / Area columns the importer validates), never back at Mageplaza's
 * Google Drive folder sample (schema predates the City / Area columns).
 */
class FormTest extends TestCase
{
    private Form $form;

    protected function setUp(): void
    {
        $this->form = $this->getMockBuilder(Form::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getUrl'])
            ->getMock();
        $this->form->method('getUrl')->willReturn(
            'http://localhost/admin_w1275xl/launchpad_mptablerate/city/importTemplate/key/abc/'
        );
        // escapeHtmlAttr reads the escaper injected by the skipped constructor.
        $escaper = $this->createMock(\Magento\Framework\Escaper::class);
        $escaper->method('escapeHtmlAttr')->willReturnCallback(
            static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES)
        );
        $escaperProp = new \ReflectionProperty($this->form, '_escaper');
        $escaperProp->setAccessible(true);
        $escaperProp->setValue($this->form, $escaper);
    }

    private function sampleHtml(): string
    {
        $ref = new \ReflectionMethod($this->form, '_getDownloadSampleFileHtml');
        $ref->setAccessible(true);

        return (string) $ref->invoke($this->form);
    }

    public function testSampleLinkPointsAtTheLaunchpadImportTemplate(): void
    {
        $html = $this->sampleHtml();

        $this->assertStringContainsString('launchpad_mptablerate/city/importTemplate', $html);
        $this->assertStringContainsString('Download Import Template', $html);
    }

    public function testSampleLinkNeverPointsAtGoogleDrive(): void
    {
        $this->assertStringNotContainsString('drive.google', $this->sampleHtml());
    }
}
