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

use Google\Protobuf\Internal\Message;
use Kovey\Network\PacketInterface;
use Kovey\Socket\Protocol\ProtocolInterface;

interface PackInterface
{
    public function pack(Message $message, int $action) : PacketInterface;

    public function unpack(string $packet) : ProtocolInterface;
}
