<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Model\Address\Dataset;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * SPEC-TASK-TBM30R §7 — lightweight dataset manifest: dataset_version, generated_at,
 * source_environment and per-file scheme_code / record_count / sha256. Purpose is
 * reproducibility + auditability only — deliberately NOT a versioning platform.
 */
class Manifest
{
    public function __construct(private readonly Json $serializer)
    {
    }

    /**
     * @return array<string, mixed>|null null when the dataset ships without a manifest
     * @throws LocalizedException malformed manifest JSON
     */
    public function read(string $manifestFile): ?array
    {
        if (!is_readable($manifestFile)) {
            return null;
        }

        $raw = (string) file_get_contents($manifestFile);
        try {
            $data = $this->serializer->unserialize($raw);
        } catch (\Throwable $exception) {
            throw new LocalizedException(__('Dataset manifest %1 is not valid JSON: %2', $manifestFile, $exception->getMessage()));
        }

        if (!is_array($data)) {
            throw new LocalizedException(__('Dataset manifest %1 must be a JSON object.', $manifestFile));
        }

        return $data;
    }

    /**
     * Validate one dataset file against its manifest entry (checksum + record count).
     *
     * @param array<string, mixed> $manifest
     * @throws LocalizedException checksum/count mismatch (tampered or stale dataset)
     */
    public function validateFile(array $manifest, string $absolutePath, string $relativePath, int $recordCount): void
    {
        $entries = $manifest['files'] ?? null;
        if (!is_array($entries) || !isset($entries[$relativePath]) || !is_array($entries[$relativePath])) {
            return; // file not covered by manifest — importer reports this, not a hard failure
        }

        $entry = $entries[$relativePath];
        $expectedSha = isset($entry['sha256']) ? (string) $entry['sha256'] : null;
        if ($expectedSha !== null && $expectedSha !== hash_file('sha256', $absolutePath)) {
            throw new LocalizedException(
                __('Dataset file %1 does not match manifest checksum (expected %2).', $relativePath, $expectedSha)
            );
        }

        $expectedCount = isset($entry['record_count']) ? (int) $entry['record_count'] : null;
        if ($expectedCount !== null && $expectedCount !== $recordCount) {
            throw new LocalizedException(
                __('Dataset file %1 has %2 data rows, manifest declares %3.', $relativePath, $recordCount, $expectedCount)
            );
        }
    }

    /**
     * Build a manifest array for an exported dataset.
     *
     * @param array<string, array{scheme_code: string, record_count: int, sha256: string}> $files relative path => stats
     */
    public function build(string $datasetVersion, ?string $generatedAt, ?string $sourceEnvironment, array $files): array
    {
        return [
            'dataset_version' => $datasetVersion,
            'generated_at' => $generatedAt,
            'source_environment' => $sourceEnvironment,
            'files' => $files,
        ];
    }

    /**
     * @param array<string, mixed> $manifest
     * @throws LocalizedException unserializable manifest
     */
    public function write(string $manifestFile, array $manifest): void
    {
        $json = $this->serializer->serialize($manifest) . "\n";
        if (file_put_contents($manifestFile, $json) === false) {
            throw new LocalizedException(__('Unable to write dataset manifest %1.', $manifestFile));
        }
    }
}
