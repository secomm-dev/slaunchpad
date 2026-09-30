<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\AddressDropdown\Test\Unit\Controller;

use PHPUnit\Framework\TestCase;

/**
 * TASK-SEC-A3 — destructive delete controllers must be POST-only: no
 * HttpGetActionInterface on the class and an explicit isPost() guard before any
 * mutation path. The form Delete buttons must route through dataPost (POST + form key),
 * never plain setLocation navigation.
 */
class DeleteSecurityTest extends TestCase
{
    private const CONTROLLERS = [
        '/Controller/Adminhtml/City/Delete.php',
        '/Controller/Adminhtml/Region/Delete.php',
        '/Controller/Adminhtml/City/MassDelete.php',
        '/Controller/Adminhtml/Region/MassDelete.php',
    ];

    private const FORM_BUTTONS = [
        '/Block/Form/City/Delete.php',
        '/Block/Form/Region/Delete.php',
    ];

    public function testDeleteControllersArePostOnlyWithExplicitGuard(): void
    {
        foreach (self::CONTROLLERS as $relativePath) {
            $code = (string) file_get_contents(self::moduleDir() . $relativePath);

            $this->assertStringNotContainsString(
                'HttpGetActionInterface',
                $code,
                "$relativePath must not accept GET for a destructive action"
            );
            $this->assertStringContainsString(
                'HttpPostActionInterface',
                $code,
                "$relativePath must declare POST as its only dispatched method"
            );
            $this->assertStringContainsString(
                'isPost()',
                $code,
                "$relativePath must guard mutations behind an explicit isPost() check"
            );
        }
    }

    public function testFormDeleteButtonsRouteThroughPost(): void
    {
        foreach (self::FORM_BUTTONS as $relativePath) {
            $code = (string) file_get_contents(self::moduleDir() . $relativePath);

            $this->assertStringContainsString(
                "{data: {}}",
                $code,
                "$relativePath must pass {data: {}} so deleteConfirm uses dataPost() (POST + form key)"
            );
        }
    }

    private static function moduleDir(): string
    {
        return __DIR__ . '/../../../';
    }
}
