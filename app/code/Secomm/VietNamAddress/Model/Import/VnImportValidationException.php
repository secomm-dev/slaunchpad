<?php
declare(strict_types=1);
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\VietNamAddress\Model\Import;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;

/**
 * TASK-ADT94K — dataset validation failure (STOP_ON_ERROR): nothing is written.
 * Carries the row-addressed error list for CLI output / setup failure reporting.
 *
 * The rendered message embeds the first self::EMBEDDED_ERROR_LIMIT errors so
 * `setup:upgrade` (which only surfaces the exception message) still shows the actual
 * validation reasons instead of a bare error count.
 */
class VnImportValidationException extends LocalizedException
{
    private const EMBEDDED_ERROR_LIMIT = 10;

    /** @var array<int, string> */
    private readonly array $errors;

    /**
     * @param array<int, string> $errors
     */
    public function __construct(Phrase $phrase, array $errors)
    {
        $this->errors = $errors;
        parent::__construct($this->embedErrors($phrase, $errors));
    }

    /**
     * @return array<int, string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @param array<int, string> $errors
     */
    private function embedErrors(Phrase $phrase, array $errors): Phrase
    {
        if ($errors === []) {
            return $phrase;
        }

        $shown = array_slice($errors, 0, self::EMBEDDED_ERROR_LIMIT);
        $remaining = count($errors) - count($shown);
        $details = array_map(static fn (string $error): string => '  - ' . $error, $shown);
        if ($remaining > 0) {
            $details[] = sprintf(
                '  (+%d more — run "bin/magento secomm:vietnam-address:import --dry-run" for the full list)',
                $remaining
            );
        }

        return new Phrase($phrase->render() . "\nDetails:\n" . implode("\n", $details));
    }
}
