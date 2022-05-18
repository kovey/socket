<?php
/**
 * @description protocol interface
 *
 * @package
 *
 * @author kovey
 *
 * @time 2020-04-29 14:21:46
 *
 */
namespace Kovey\Socket\Protocol;

use Google\Protobuf\Internal\Message;

interface ProtocolInterface
{
    /**
     * @description get message
     *
     * @return string
     */
    public function getMessage() : string;

    /**
     * @description get action
     *
     * @return int
     */
    public function getAction() : int | string;

    public function getBase() : Message;
}
