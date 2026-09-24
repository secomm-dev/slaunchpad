<?php
/**
 * Manual factory for the MoMo refund request entity.
 *
 * The entity is DataObject-backed (no generated factory: it is not bound to
 * a generated AbstractModel factory chain), so the factory is a plain class.
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2026 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Model;

class RefundRequestFactory
{
    /**
     * Create a refund request entity, optionally pre-hydrated.
     *
     * @param array $data
     * @return RefundRequest
     */
    public function create(array $data = []): RefundRequest
    {
        return new RefundRequest($data);
    }
}
