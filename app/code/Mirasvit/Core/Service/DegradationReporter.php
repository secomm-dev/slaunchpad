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
 * @package   mirasvit/module-core
 * @version   1.7.15
 * @copyright Copyright (C) 2026 Mirasvit (https://mirasvit.com/)
 */



namespace Mirasvit\Core\Service;

use Magento\Framework\App\Filesystem\DirectoryList;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

/**
 * Reports a degradation — a point where the code had to drop, skip, or fall back
 * on some behaviour — to a diagnostic log, so a failure that would otherwise be
 * silent becomes visible and queryable.
 *
 * One file per module: every report for a module goes to var/log/mirasvit/<module>.log
 * (e.g. module "search" -> var/log/mirasvit/search.log). The $event names the specific
 * degradation and is prefixed into each line as "[<event>]", so a single module file
 * stays greppable by event.
 *
 * Call it instead of returning silently at a drop-guard:
 *
 *     if ($results === null) {
 *         $this->degradationReporter->report(
 *             'search',                 // module -> var/log/mirasvit/search.log
 *             'report.missing_count',   // event  -> "[report.missing_count]" in the line
 *             'search total count missing at response time; query not logged',
 *             ['query' => $query]
 *         );
 *         return;
 *     }
 */
class DegradationReporter
{
    /**
     * @var DirectoryList
     */
    private $directoryList;

    /**
     * @var LoggerInterface[] one logger per module, built lazily and cached
     */
    private $loggers = [];

    public function __construct(DirectoryList $directoryList)
    {
        $this->directoryList = $directoryList;
    }

    /**
     * Report a degradation.
     *
     * @param string $module the module/feature this belongs to; selects the log file
     *                       var/log/mirasvit/<module>.log (e.g. "search", "email")
     * @param string $event  short dotted event key (e.g. "report.missing_count") —
     *                       prefixed into the line so one module file stays greppable
     * @param string $reason human, one-line: what was dropped and why it matters
     * @param array  $context structured detail (query, ids, engine, …) — never secrets
     *
     * Kept cheap — one log write to a cached logger — so it is safe on hot paths
     * (report() can fire per-record during a reindex); do not add a per-call DB insert here.
     */
    public function report(string $module, string $event, string $reason, array $context = []): void
    {
        $this->resolveLogger($this->fileStem($module))->warning(
            sprintf('[degradation] [%s] %s', $event, $reason),
            $context
        );
    }

    /**
     * Sanitise the module name to a safe file stem; falls back to "diagnostic".
     */
    private function fileStem(string $module): string
    {
        $stem = strtolower((string)preg_replace('/[^A-Za-z0-9_]/', '_', $module));

        return $stem !== '' ? $stem : 'diagnostic';
    }

    private function resolveLogger(string $module): LoggerInterface
    {
        if (!isset($this->loggers[$module])) {
            $file   = $this->directoryList->getPath(DirectoryList::LOG) . '/mirasvit/' . $module . '.log';
            $logger = new Logger('mirasvit.' . $module);
            // Default handler level passes everything; report() only ever logs warnings.
            $logger->pushHandler(new StreamHandler($file));
            $this->loggers[$module] = $logger;
        }

        return $this->loggers[$module];
    }
}
