<?php
namespace Coolie\LogStorage;

/**
 * Interface BaseInterface
 * @package Coolie\LogStorage
 */
interface BaseInterface
{
    public function init(array $config);

    public function store(array $logs);
}
