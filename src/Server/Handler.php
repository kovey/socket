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
use Kovey\Network\Event\Receive as ER;
use Kovey\Socket\Protobuf\ProtobufInterface;
use Kovey\Socket\Handler\PackInterface;
use Kovey\App\Components\Work;

class Handler implements ReceiveInterface
{
    private PackInterface $pack;

    private bool $openMonitor;

    private Work $work;

    private string $name;

    public function __construct(bool $openMonitor, string $name)
    {
        $this->openMonitor = $openMonitor;
        $this->name = $name;
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

    public function receive(ER $event) : void
    {
        $packet = $this->pack->unpack($event->getData());

        $receive = new Receive($event, $this->name, $packet);
        $receive->begin()
                 ->run($this->work)
                 ->end($this->pack);
        if (!$this->openMonitor) {
            return;
        }

        $receive->monitor();
    }

    public function getPack() : PackInterface
    {
        return $this->pack;
    }
}
