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



declare(strict_types=1);

namespace Mirasvit\Core\Console\Command;

use Mirasvit\Core\Service\GeoLocationService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GeoLocationDownloadCommand extends Command
{
    private $geoLocationService;


    public function __construct(GeoLocationService $geoLocationService)
    {
        $this->geoLocationService = $geoLocationService;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('mirasvit:geolocation:download')
            ->setDescription('Download or update GeoLite2-City database from MaxMind');

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->geoLocationService->isConfigured()) {
            $output->writeln('<error>MaxMind credentials not configured</error>');
            $output->writeln('Please configure Account ID and License Key in admin panel');
            $output->writeln('Stores > Configuration > Mirasvit > Core > GeoLocation');

            return Command::FAILURE;
        }

        $output->writeln('Checking for updates...');

        $info = $this->geoLocationService->getDatabaseInfo();
        if ($info) {
            $output->writeln(sprintf(
                'Current database: %s (%s)',
                $this->formatVersion($info['version']),
                $this->formatBytes($info['size'])
            ));
        }

        $result = $this->geoLocationService->downloadDatabase();

        if ($result['downloaded']) {
            $output->writeln('<info>' . $result['message'] . '</info>');

            $newInfo = $this->geoLocationService->getDatabaseInfo();
            if ($newInfo) {
                $output->writeln(sprintf('Version: %s', $this->formatVersion($newInfo['version'])));
                $output->writeln(sprintf('Size: %s', $this->formatBytes($newInfo['size'])));
            }

            return Command::SUCCESS;
        }

        // Check if database is up to date (not downloaded but database exists)
        if ($this->geoLocationService->isAvailable()) {
            $output->writeln('<info>' . $result['message'] . '</info>');

            if ($info) {
                $output->writeln(sprintf('Version: %s', $this->formatVersion($info['version'])));
                $output->writeln(sprintf('Size: %s', $this->formatBytes($info['size'])));
            }

            return Command::SUCCESS;
        }

        $output->writeln('<error>' . $result['message'] . '</error>');

        return Command::FAILURE;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        }

        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }

        return $bytes . ' bytes';
    }

    private function formatVersion(?string $version): string
    {
        if (!$version || strlen($version) !== 8) {
            return $version ?? 'unknown';
        }

        // Convert 20260120 to 2026-01-20
        return substr($version, 0, 4) . '-' . substr($version, 4, 2) . '-' . substr($version, 6, 2);
    }
}
