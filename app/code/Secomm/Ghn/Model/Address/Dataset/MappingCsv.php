<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Model\Address\Dataset;

/**
 * SPEC-TASK-TBM30R §5 — portable mapping CSV schema. Offline working files may carry
 * REVIEW_REQUIRED / UNRESOLVED / AMBIGUOUS rows; only APPROVED is activated by the importer.
 * The same header is used by the suggester workfile so a reviewed file imports unmodified.
 */
final class MappingCsv
{
    public const HEADER = [
        'secomm_scheme_code',
        'secomm_unit_code',
        'ghn_scheme_code',
        'ghn_provider_key',
        'mapping_method',
        'mapping_status',
        'note',
    ];

    public const STATUS_APPROVED = 'APPROVED';
    public const STATUS_REVIEW_REQUIRED = 'REVIEW_REQUIRED';
    public const STATUS_UNRESOLVED = 'UNRESOLVED';
    public const STATUS_AMBIGUOUS = 'AMBIGUOUS';

    public const FILE_STATUSES = [
        self::STATUS_APPROVED,
        self::STATUS_REVIEW_REQUIRED,
        self::STATUS_UNRESOLVED,
        self::STATUS_AMBIGUOUS,
    ];

    /**
     * METHODS an APPROVED row may carry. The offline authoring review (TASK-6TNKDH, dataset
     * v1.0.0) settled the authoritative method taxonomy below; the three original matcher-label
     * values stay valid so suggester workfiles remain importable after review.
     */
    public const METHODS = [
        // Offline reviewed authoring taxonomy (v1.0.0 dataset).
        'NORMALIZED_EXACT',
        'NORMALIZED_TONE_PLACEMENT',
        'NORMALIZED_SPACING_DASH',
        'NORMALIZED_ORTHOGRAPHY',
        'NORMALIZED_POLICY',
        'NORMALIZED_SEMANTIC_NOTATION',
        'PARENT_SCOPED_REVIEWED',
        'PARENT_SCOPED_RESIDUAL_1TO1',
        'HIERARCHY_REVIEWED',
        'OFFICIAL_SOURCE_REVIEWED',
        'PROVIDER_DUPLICATE_CURATED',
        'HISTORICAL_MERGE_PRIMARY',
        'AI_REVIEWED_ALIAS',
        // Legacy matcher-label values (suggester workfiles; REVIEW_REQUIRED rows are skipped
        // by the importer — these are only reachable after a human flips status to APPROVED).
        'EXACT_NAME',
        'CURATED_ALIAS',
        'MANUAL',
    ];

    private function __construct()
    {
    }
}
