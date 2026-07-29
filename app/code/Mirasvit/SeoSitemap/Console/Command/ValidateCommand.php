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

namespace Mirasvit\SeoSitemap\Console\Command;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Sitemap\Model\ResourceModel\Sitemap\CollectionFactory;
use Mirasvit\Core\Service\DegradationReporter;
use Mirasvit\SeoSitemap\Validate\SitemapLocValidator;
use Mirasvit\SeoSitemap\Validate\ValidatorInterface;
use Mirasvit\SeoSitemap\Validate\Violation;
use Mirasvit\SeoSitemap\Validate\WellFormedValidator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `mirasvit:seositemap:validate [--format=json]` — asserts golden-output invariants against the
 * generated sitemap file(s) and exits non-zero on a violation (bug class C, Step 6).
 *
 * A read-only diagnostic: it inspects the sitemap(s) already written to disk, so a customer can
 * catch a "valid but wrong" sitemap (malformed XML, an empty/relative/placeholder/duplicate <loc>,
 * a deactivated entity leaking in) that raises no exception, and CI runs it fail-fast after
 * generation. The same validator classes back this command and the headless golden test, so gate
 * and diagnostic never drift.
 *
 * Self-contained and module-local — no shared cross-module base. It only *consumes* the existing
 * module-core DegradationReporter API (no change to core).
 */
class ValidateCommand extends Command
{
    public const OPTION_FORMAT = 'format';

    public const FORMAT_TEXT = 'text';
    public const FORMAT_JSON = 'json';

    /** @var CollectionFactory */
    private $sitemapCollectionFactory;

    /** @var DirectoryList */
    private $directoryList;

    /** @var File */
    private $fileDriver;

    /** @var DegradationReporter */
    private $degradationReporter;

    /** @var SerializerInterface */
    private $serializer;

    public function __construct(
        CollectionFactory $sitemapCollectionFactory,
        DirectoryList $directoryList,
        File $fileDriver,
        DegradationReporter $degradationReporter,
        SerializerInterface $serializer
    ) {
        $this->sitemapCollectionFactory = $sitemapCollectionFactory;
        $this->directoryList            = $directoryList;
        $this->fileDriver               = $fileDriver;
        $this->degradationReporter      = $degradationReporter;
        $this->serializer               = $serializer;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('mirasvit:seositemap:validate')
            ->setDescription('Validate generated sitemap output against golden-output invariants (exits non-zero on a violation)')
            ->addOption(self::OPTION_FORMAT, null, InputOption::VALUE_REQUIRED, 'Output format: text|json', self::FORMAT_TEXT);

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $coverage   = [];
        $violations = [];

        foreach ($this->getSitemapValidators() as $sitemap) {
            foreach ($sitemap['validators'] as $validator) {
                $result = $validator->validate();

                $coverage[] = [
                    'sitemap_id'   => $sitemap['id'],
                    'sitemap_name' => $sitemap['name'],
                    'check'        => $validator->getName(),
                    'title'        => $validator->getTitle(),
                    'checked'      => $result->getCheckedCount(),
                    'failed'       => count($result->getViolations()),
                ];

                foreach ($result->getViolations() as $violation) {
                    $violations[] = [
                        'sitemap_id'   => $sitemap['id'],
                        'sitemap_name' => $sitemap['name'],
                        'title'        => $validator->getTitle(),
                        'violation'    => $violation,
                    ];
                }
            }
        }

        foreach ($violations as $row) {
            $this->degradationReporter->report(
                'seositemap',
                'seositemap.validate.' . $row['violation']->getCheck(),
                sprintf('sitemap #%d "%s": %s', $row['sitemap_id'], $row['sitemap_name'], $row['violation']->getMessage()),
                ['sitemap_id' => $row['sitemap_id']] + $row['violation']->getContext()
            );
        }

        $json = $input->getOption(self::OPTION_FORMAT) === self::FORMAT_JSON;
        $output->writeln($json
            ? $this->renderJson($coverage, $violations)
            : $this->renderText($coverage, $violations));

        return $violations === [] ? 0 : 1;
    }

    /**
     * The validators to run, grouped per sitemap so every coverage row and violation can be reported
     * against the sitemap it came from (id + filename) — otherwise a failure in one of several
     * generated sitemaps is unattributable.
     *
     * @return array<int, array{id: int, name: string, validators: ValidatorInterface[]}>
     */
    private function getSitemapValidators(): array
    {
        $pubPath  = rtrim($this->directoryList->getPath(DirectoryList::PUB), '/');
        $sitemaps = [];

        foreach ($this->sitemapCollectionFactory->create() as $sitemap) {
            $relative = ltrim((string)$sitemap->getSitemapPath(), '/') . $sitemap->getSitemapFilename();
            $path     = $pubPath . '/' . $relative;

            if (!$this->fileDriver->isExists($path)) {
                continue; // sitemap not generated yet — nothing to validate for it
            }

            $content = (string)$this->fileDriver->fileGetContents($path);

            $sitemaps[] = [
                'id'         => (int)$sitemap->getId(),
                'name'       => (string)$sitemap->getSitemapFilename(),
                'validators' => [
                    new WellFormedValidator($content),
                    new SitemapLocValidator($content),
                ],
            ];
        }

        return $sitemaps;
    }

    /**
     * @param array<int, array{sitemap_id: int, sitemap_name: string, check: string, title: string, checked: int, failed: int}> $coverage
     * @param array<int, array{sitemap_id: int, sitemap_name: string, title: string, violation: Violation}>                      $violations
     */
    private function renderText(array $coverage, array $violations): string
    {
        $lines          = [];
        $currentSitemap = null;
        foreach ($coverage as $row) {
            if ($row['sitemap_id'] !== $currentSitemap) {
                $currentSitemap = $row['sitemap_id'];
                $lines[]        = sprintf('<comment>sitemap #%d "%s"</comment>', $row['sitemap_id'], $row['sitemap_name']);
            }
            $mark    = $row['failed'] === 0 ? '<info>OK</info>' : '<error>FAIL</error>';
            $note    = $row['checked'] === 0 ? ' <comment>(checked 0 — vacuous)</comment>' : '';
            $lines[] = sprintf('  [%s] %s — checked %d, %d failed%s', $mark, $row['title'], $row['checked'], $row['failed'], $note);
        }

        if ($coverage === []) {
            $lines[] = '<comment>No generated sitemaps to validate.</comment>';
        }

        if ($violations === []) {
            $lines[] = '<info>Golden-output invariants passed.</info>';

            return implode(PHP_EOL, $lines);
        }

        $lines[] = sprintf('<error>%d invariant violation(s):</error>', count($violations));
        foreach ($violations as $row) {
            $lines[] = sprintf(
                '  - sitemap #%d "%s" [%s] %s',
                $row['sitemap_id'],
                $row['sitemap_name'],
                $row['title'],
                $row['violation']->getMessage()
            );
        }

        return implode(PHP_EOL, $lines);
    }

    /**
     * @param array<int, array{sitemap_id: int, sitemap_name: string, check: string, title: string, checked: int, failed: int}> $coverage
     * @param array<int, array{sitemap_id: int, sitemap_name: string, title: string, violation: Violation}>                      $violations
     */
    private function renderJson(array $coverage, array $violations): string
    {
        return (string)$this->serializer->serialize([
            'status'     => $violations === [] ? 'ok' : 'violations',
            'coverage'   => $coverage,
            'violations' => array_map(static function (array $row): array {
                return [
                    'sitemap_id'   => $row['sitemap_id'],
                    'sitemap_name' => $row['sitemap_name'],
                    'title'        => $row['title'],
                ] + $row['violation']->toArray();
            }, $violations),
        ]);
    }
}
