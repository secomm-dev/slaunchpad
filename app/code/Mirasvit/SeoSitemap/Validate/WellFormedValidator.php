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
 * Structural invariant (validate-schema): the generated sitemap is well-formed XML. A malformed
 * sitemap is a class-C defect that ships silently — search engines reject the file, not the store.
 * Baseline-free, so it guards a customer's live sitemap where there is no committed golden.
 */
class WellFormedValidator implements ValidatorInterface
{
    /** @var string */
    private $content;

    public function __construct(string $content)
    {
        $this->content = $content;
    }

    public function getName(): string
    {
        return 'well_formed';
    }

    public function getTitle(): string
    {
        return (string)__('Valid sitemap XML');
    }

    public function validate(): ValidationResult
    {
        $previous = libxml_use_internal_errors(true);
        $doc      = simplexml_load_string($this->content);
        $errors   = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($doc === false) {
            $reason = $errors !== [] ? trim($errors[0]->message) : (string)__('XML parse error');

            return new ValidationResult(1, [
                new Violation('well_formed', 'sitemap XML is not well-formed: ' . $reason),
            ]);
        }

        return new ValidationResult(1, []);
    }
}
