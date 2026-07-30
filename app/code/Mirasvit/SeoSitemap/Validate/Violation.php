<?php
/**
 * Mirasvit
 *
 * This source file is subject to the Mirasvit Software License, which is available at https://mirasvit.com/license/.
 * Do not edit or add to this file if you wish to upgrade the to newer versions in the future.
 * If you wish to customize this module for your needs.
 * Please refer to http://www.magentocommerce.com for more information.
 *
 * @category  Mirasvit
 * @package   mirasvit/module-seo
 * @version   2.12.8
 * @copyright Copyright (C) 2026 Mirasvit (https://mirasvit.com/)
 */



declare(strict_types=1);

namespace Mirasvit\SeoSitemap\Validate;

/**
 * A single invariant breach found while validating a generated sitemap — the unit the
 * `mirasvit:seositemap:validate` command reports and exits non-zero on.
 */
class Violation
{
    /** @var string */
    private $check;

    /** @var string */
    private $message;

    /** @var array<string, mixed> */
    private $context;

    /**
     * @param array<string, mixed> $context
     */
    public function __construct(string $check, string $message, array $context = [])
    {
        $this->check   = $check;
        $this->message = $message;
        $this->context = $context;
    }

    public function getCheck(): string
    {
        return $this->check;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'check'   => $this->check,
            'message' => $this->message,
            'context' => $this->context,
        ];
    }
}
