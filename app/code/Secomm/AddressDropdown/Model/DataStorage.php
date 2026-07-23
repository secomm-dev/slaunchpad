<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Model;

class DataStorage
{
    /**
     * @var array
     */
    protected $data = [];

    /**
     * Set a value
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function set(string $key, $value): void
    {
        $this->data[$key] = $value;
    }

    /**
     * Get a value
     *
     * @param string $key
     * @return mixed
     */
    public function get(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    /**
     * Unset a value
     *
     * @param string $key
     * @return void
     */
    public function unset(string $key): void
    {
        unset($this->data[$key]);
    }

    /**
     * Clear all data
     *
     * @return void
     */
    public function clear(): void
    {
        $this->data = [];
    }

    /**
     * Destructor
     */
    public function __destruct()
    {
        $this->clear();
    }
}
