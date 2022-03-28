<?php
/**
 * @description
 *
 * @package
 *
 * @author kovey
 *
 * @time 2022-03-28 12:08:34
 *
 */
namespace Kovey\Socket\Server;

use Kovey\Network\Handler\ReceiveInterface;
use Kovey\Network\Event\Receive;
use Kovey\Socket\Protobuf\ProtobufInterface;
use Kovey\Socket\Handler\PackInterface;
use Kovey\App\Components\Work;

class Handler implements ReceiveInterface
{
    private PackInterface $pack;

    private bool $openMonitor;

    private Work $work;

    public function __construct(bool $openMonitor)
    {
        $this->openMonitor = $openMonitor;
    }

    public function setWork(Work $work) : self
    {
        $this->work = $work;
        return $this;
    }

    public function setPack(PackInterface $pack)
    {
        $this->pack = $pack;
    }

    public function receive(Receive $event) : void
    {
        $packet = $this->pack->unpack($event->getPacket());

        $receive = new Receive($event, $this->config['name'], $packet);
        $receive->begin()
                 ->run($this->work)
                 ->end($this->pack);
        if (!$this->openMonitor) {
            return;
        }

        $receive->monitor();
    }
}
