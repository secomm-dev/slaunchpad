<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Model\GhtkAddressOverrideImport;

/**
 * Outcome of a GHTK address override CSV import (replace-all).
 *
 * Counts: inserted / updated / removed (replace-all) / skipped (duplicate-in-file)
 * / failed (rows that aborted the whole import). $committed is true only when the
 * transactional replace-all actually landed.
 */
class Summary
{
    private int $inserted = 0;
    private int $updated = 0;
    private int $removed = 0;
    private int $skipped = 0;
    private int $failed = 0;
    private bool $committed = false;

    /** @var string[] */
    private array $errors = [];

    public function incrementInserted(int $n = 1): self
    {
        $this->inserted += $n;

        return $this;
    }

    public function incrementUpdated(int $n = 1): self
    {
        $this->updated += $n;

        return $this;
    }

    public function incrementRemoved(int $n = 1): self
    {
        $this->removed += $n;

        return $this;
    }

    public function incrementSkipped(int $n = 1): self
    {
        $this->skipped += $n;

        return $this;
    }

    public function addError(string $message): self
    {
        $this->errors[] = $message;

        return $this;
    }

    public function markCommitted(): self
    {
        $this->committed = true;

        return $this;
    }

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    public function getInserted(): int
    {
        return $this->inserted;
    }

    public function getUpdated(): int
    {
        return $this->updated;
    }

    public function getRemoved(): int
    {
        return $this->removed;
    }

    public function getSkipped(): int
    {
        return $this->skipped;
    }

    /**
     * Number of rows that failed structural validation (caused the import to abort).
     */
    public function getFailed(): int
    {
        return $this->failed;
    }

    public function setFailed(int $failed): self
    {
        $this->failed = $failed;

        return $this;
    }

    public function isCommitted(): bool
    {
        return $this->committed;
    }

    /**
     * @return string[]
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
