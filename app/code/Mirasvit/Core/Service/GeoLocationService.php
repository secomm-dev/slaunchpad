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

namespace Mirasvit\Core\Service;

use GeoIp2\Database\Reader;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Archive\Gz;
use Magento\Framework\Archive\Tar;
use Magento\Framework\Filesystem;
use Mirasvit\Core\Model\Config;
use Psr\Log\LoggerInterface;

class GeoLocationService
{
    private const DATABASE_FILENAME = 'GeoLite2-City.mmdb';
    private const HASH_FILENAME     = 'GeoLite2-City.mmdb.sha256';
    private const DATABASE_DIR      = 'mirasvit/geolocation';
    private const DOWNLOAD_URL      = 'https://download.maxmind.com/geoip/databases/GeoLite2-City/download';
    private const CACHE_PREFIX      = 'mst_geoloc_';
    private const CACHE_LIFETIME    = 86400;

    public const STATUS_NO_DATABASE       = 'no_database';
    public const STATUS_NO_AUTOUPDATE     = 'no_autoupdate';
    public const STATUS_FULLY_CONFIGURED  = 'fully_configured';

    private $config;

    private $filesystem;

    private $cache;

    private $logger;

    private $gz;

    private $tar;

    private $reader = null;

    public function __construct(
        Config          $config,
        Filesystem      $filesystem,
        CacheInterface  $cache,
        LoggerInterface $logger,
        Gz              $gz,
        Tar             $tar
    ) {
        $this->config     = $config;
        $this->filesystem = $filesystem;
        $this->cache      = $cache;
        $this->logger     = $logger;
        $this->gz         = $gz;
        $this->tar        = $tar;
    }

    public function downloadDatabase(): array
    {
        if (!$this->isConfigured()) {
            return [
                'downloaded' => false,
                'message'    => __('MaxMind credentials not configured'),
            ];
        }

        try {
            $remoteHash = $this->downloadRemoteHash();
            if (!$remoteHash) {
                return [
                    'downloaded' => false,
                    'message'    => __('Failed to download remote hash'),
                ];
            }

            $storedHash = $this->getStoredHash();

            if ($storedHash && $storedHash === $remoteHash) {
                return [
                    'downloaded' => false,
                    'message'    => __('Database is up to date'),
                ];
            }

            if (!$this->downloadAndExtractDatabase()) {
                return [
                    'downloaded' => false,
                    'message'    => __('Failed to download and extract database'),
                ];
            }

            $this->saveHash($remoteHash);

            return [
                'downloaded' => true,
                'message'    => __('Database updated successfully'),
            ];

        } catch (\Exception $e) {
            $this->logger->error(
                sprintf('GeoLocation database download failed: %s', $e->getMessage())
            );

            return [
                'downloaded' => false,
                'message'    => __('Download failed: %1', $e->getMessage()),
            ];
        }
    }

    public function isAvailable(): bool
    {
        return $this->hasDatabaseFile();
    }

    public function hasDatabaseFile(): bool
    {
        return file_exists($this->getDatabasePath());
    }

    public function isConfigured(): bool
    {
        $accountId  = $this->config->getGeoLocationAccountId();
        $licenseKey = $this->config->getGeoLocationLicenseKey();

        return !empty($accountId) && !empty($licenseKey);
    }

    public function isAutoUpdateEnabled(): bool
    {
        return $this->isConfigured();
    }

    public function getStatus(): string
    {
        if (!$this->hasDatabaseFile()) {
            return self::STATUS_NO_DATABASE;
        }

        if (!$this->isAutoUpdateEnabled()) {
            return self::STATUS_NO_AUTOUPDATE;
        }

        return self::STATUS_FULLY_CONFIGURED;
    }

    public function getDatabasePath(): string
    {
        $varDir = $this->filesystem->getDirectoryRead(DirectoryList::VAR_DIR)->getAbsolutePath();

        return $varDir . self::DATABASE_DIR . '/' . self::DATABASE_FILENAME;
    }

    public function getDatabaseInfo(): ?array
    {
        $path = $this->getDatabasePath();

        if (!file_exists($path)) {
            return null;
        }

        return [
            'version'       => $this->getDatabaseVersion(),
            'downloaded_at' => filemtime($path),
            'size'          => filesize($path),
        ];
    }

    public function getDatabaseVersion(): ?string
    {
        $varDir   = $this->filesystem->getDirectoryRead(DirectoryList::VAR_DIR)->getAbsolutePath();
        $hashPath = $varDir . self::DATABASE_DIR . '/' . self::HASH_FILENAME;

        if (!file_exists($hashPath)) {
            return null;
        }

        // Hash file format: "<hash>  GeoLite2-City_20260116.tar.gz"
        $content = trim(file_get_contents($hashPath));

        if (preg_match('/(\d{8})\.tar\.gz$/', $content, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public function lookup(string $ip): ?array
    {
        if (!$this->isAvailable()) {
            throw new \RuntimeException(
                'GeoLocation database is not available. Please download it in Stores > Configuration > Mirasvit > Developer > GeoLocation Database.'
            );
        }

        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return null;
        }

        $cacheKey = self::CACHE_PREFIX . hash('sha256', $ip);
        $cached   = $this->cache->load($cacheKey);

        if ($cached) {
            return SerializeService::decode($cached);
        }

        $reader = $this->getReader();
        if (!$reader) {
            throw new \RuntimeException('Failed to initialize GeoLocation database reader.');
        }

        try {
            $record = $reader->city($ip);
        } catch (\Exception $e) {
            $this->logger->warning(
                sprintf('GeoLocation lookup failed for IP %s: %s', $ip, $e->getMessage())
            );

            return null;
        }

        $result = [
            'country_iso'  => $record->country->isoCode ?? null,
            'country_name' => $record->country->name ?? null,
            'city'         => $record->city->name ?? null,
            'timezone'     => $record->location->timeZone ?? null,
            'latitude'     => $record->location->latitude ?? null,
            'longitude'    => $record->location->longitude ?? null,
            'continent'    => $record->continent->code ?? null,
            'subdivision'  => $record->mostSpecificSubdivision->isoCode ?? null,
        ];

        $this->cache->save(
            SerializeService::encode($result),
            $cacheKey,
            [self::CACHE_PREFIX],
            self::CACHE_LIFETIME
        );

        return $result;
    }

    public function getCountryCode(string $ip): ?string
    {
        $result = $this->lookup($ip);

        return $result['country_iso'] ?? null;
    }

    public function getTimezone(string $ip): ?string
    {
        $result = $this->lookup($ip);

        return $result['timezone'] ?? null;
    }

    public function getCoordinates(string $ip): ?array
    {
        $result = $this->lookup($ip);

        if (!isset($result['latitude'], $result['longitude'])) {
            return null;
        }

        return [
            'latitude'  => $result['latitude'],
            'longitude' => $result['longitude'],
        ];
    }

    public function getContinentCode(string $ip): ?string
    {
        $result = $this->lookup($ip);

        return $result['continent'] ?? null;
    }

    private function downloadRemoteHash(): ?string
    {
        $url = self::DOWNLOAD_URL . '?suffix=tar.gz.sha256';

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_USERPWD        => $this->config->getGeoLocationAccountId() . ':' . $this->config->getGeoLocationLicenseKey(),
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            $this->logger->error(
                sprintf('Failed to download remote hash. HTTP code: %d', $httpCode)
            );

            return null;
        }

        return trim($response);
    }

    private function getStoredHash(): ?string
    {
        $varDir  = $this->filesystem->getDirectoryRead(DirectoryList::VAR_DIR)->getAbsolutePath();
        $hashPath = $varDir . self::DATABASE_DIR . '/' . self::HASH_FILENAME;

        if (!file_exists($hashPath)) {
            return null;
        }

        return trim(file_get_contents($hashPath));
    }

    private function saveHash(string $hash): void
    {
        $varDir   = $this->filesystem->getDirectoryRead(DirectoryList::VAR_DIR)->getAbsolutePath();
        $hashPath = $varDir . self::DATABASE_DIR . '/' . self::HASH_FILENAME;

        file_put_contents($hashPath, $hash);
    }

    private function downloadAndExtractDatabase(): bool
    {
        $url = self::DOWNLOAD_URL . '?suffix=tar.gz';

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_TIMEOUT        => 300,
            CURLOPT_USERPWD        => $this->config->getGeoLocationAccountId() . ':' . $this->config->getGeoLocationLicenseKey(),
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            $this->logger->error(
                sprintf('Failed to download database tar.gz. HTTP code: %d', $httpCode)
            );

            return false;
        }

        $varDir    = $this->filesystem->getDirectoryRead(DirectoryList::VAR_DIR)->getAbsolutePath();
        $targetDir = $varDir . self::DATABASE_DIR;

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $tempGzPath  = $targetDir . '/temp.tar.gz';
        $tempTarPath = $targetDir . '/temp.tar';

        file_put_contents($tempGzPath, $response);

        try {
            // Step 1: Decompress .tar.gz to .tar using Magento's Gz class
            $this->gz->unpack($tempGzPath, $tempTarPath);

            // Step 2: Extract .tar to directory using Magento's Tar class
            $this->tar->unpack($tempTarPath, $targetDir . '/');

            // Find the extracted directory or mmdb file
            $extractedDir = null;
            $directMmdb   = null;
            $items        = scandir($targetDir);

            foreach ($items as $item) {
                if ($item === '.' || $item === '..' || strpos($item, 'temp.') === 0) {
                    continue;
                }

                $fullPath = $targetDir . '/' . $item;

                // Check if mmdb was extracted directly (flat extraction)
                if ($item === self::DATABASE_FILENAME) {
                    $directMmdb = $fullPath;
                    break;
                }

                // Check for extracted directory
                if (is_dir($fullPath) && strpos($item, 'GeoLite2') === 0) {
                    $extractedDir = $fullPath;
                    break;
                }
            }

            // If mmdb was extracted directly, we're done (no version info available)
            if ($directMmdb) {
                $this->cleanupExtraction($targetDir, '', $tempGzPath, $tempTarPath);
                return true;
            }

            if (!$extractedDir) {
                throw new \Exception('No extracted directory found. Contents: ' . implode(', ', $items));
            }

            $mmdbFile = $extractedDir . '/' . self::DATABASE_FILENAME;
            if (!file_exists($mmdbFile)) {
                throw new \Exception('Database file not found in extracted archive');
            }

            $finalPath = $targetDir . '/' . self::DATABASE_FILENAME;
            rename($mmdbFile, $finalPath);

            $this->cleanupExtraction($targetDir, $extractedDir, $tempGzPath, $tempTarPath);

            return true;

        } catch (\Exception $e) {
            $this->logger->error(
                sprintf('Failed to extract database: %s', $e->getMessage())
            );

            if (file_exists($tempGzPath)) {
                unlink($tempGzPath);
            }
            if (file_exists($tempTarPath)) {
                unlink($tempTarPath);
            }

            return false;
        }
    }

    private function cleanupExtraction(
        string $targetDir,
        string $extractedDir,
        string $tempGzPath,
        string $tempTarPath
    ): void {
        if (file_exists($tempGzPath)) {
            unlink($tempGzPath);
        }

        if (file_exists($tempTarPath)) {
            unlink($tempTarPath);
        }

        if ($extractedDir && is_dir($extractedDir)) {
            $this->removeDirectory($extractedDir);
        }
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;

            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }

    private function getReader(): ?Reader
    {
        if ($this->reader !== null) {
            return $this->reader;
        }

        if (!$this->isAvailable()) {
            return null;
        }

        try {
            $this->reader = new Reader($this->getDatabasePath());

            return $this->reader;
        } catch (\Exception $e) {
            $this->logger->error(
                sprintf('Failed to initialize GeoIP2 Reader: %s', $e->getMessage())
            );

            return null;
        }
    }
}
