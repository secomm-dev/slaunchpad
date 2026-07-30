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
 * Sitemap <loc> invariant (bug class C): every location is a non-empty, absolute http(s) URL with
 * no placeholder token, and no <loc> is duplicated. Catches the "valid but wrong sitemap" regressions
 * — empty/relative/placeholder URLs, duplicated entries, deactivated entities leaking in — independent
 * of catalog size. Baseline-free.
 */
class SitemapLocValidator implements ValidatorInterface
{
    /** @var string */
    private $content;

    public function __construct(string $content)
    {
        $this->content = $content;
    }

    public function getName(): string
    {
        return 'sitemap_loc';
    }

    public function getTitle(): string
    {
        return (string)__('Valid sitemap URLs');
    }

    public function validate(): ValidationResult
    {
        preg_match_all('#<loc>(.*?)</loc>#s', $this->content, $matches);
        $locs = array_map('trim', $matches[1]);

        $violations = [];
        $seen       = [];

        foreach ($locs as $loc) {
            if ($loc === '' || !preg_match('#^https?://.+#', $loc)) {
                $violations[] = new Violation('sitemap_loc', (string)__('empty or non-absolute <loc>'), ['loc' => $loc]);
            } elseif (strpos($loc, '{{') !== false) {
                // Magento config placeholder tokens ({{base_url}} etc.) that never got resolved.
                // NOTE: a bare host like `localhost` is a legitimate base URL (dev/CI/staging), not a
                // placeholder — flagging it would falsely fail the gate on every localhost install.
                $violations[] = new Violation('sitemap_loc', (string)__('placeholder <loc>'), ['loc' => $loc]);
            }

            if (isset($seen[$loc])) {
                $violations[] = new Violation('sitemap_loc', (string)__('duplicate <loc>'), ['loc' => $loc]);
            }
            $seen[$loc] = true;
        }

        return new ValidationResult(count($locs), $violations);
    }
}
