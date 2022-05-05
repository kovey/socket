<?php
/**
 * @description
 *
 * @package
 *
 * @author kovey
 *
 * @time 2022-03-28 14:03:18
 *
 */
namespace Kovey\Socket\Handler;

interface CheckSpeedInterface
{
    public function isSpeed(int $fd) : bool;

    public function getErrorPacket() : Array;
}
