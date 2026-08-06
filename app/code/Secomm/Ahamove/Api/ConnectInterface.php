<?php

namespace Secomm\Ahamove\Api;

interface ConnectInterface
{
    /**
     * @return mixed
     */
    public function post();

    /**
     * @return mixed
     */
    public function get();

    /**
     * @return mixed
     */
    public function put();

    /**
     * @param $url
     * @return $this
     */
    public function to($url);

    /**
     * @param string $name
     * @return $this
     */
    public function name(string $name);

    /**
     * @param array $headers
     * @return $this
     */
    public function withHeaders(array $headers);

    /**
     * @param array $data
     * @return $this
     */
    public function withData($data = []);
}