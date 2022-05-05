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
use Kovey\Socket\Handler\CheckSpeedInterface;
use Kovey\App\Components\Work;
use Swoole\Coroutine\System;

class Handler implements ReceiveInterface
{
    private PackInterface $pack;

    private bool $openMonitor;

    private Work $work;

    private string $name;

    private CheckSpeedInterface $speed;

    public function __construct(bool $openMonitor, string $name)
    {
        $this->openMonitor = $openMonitor;
        $this->name = $name;
    }

    public function setCheckSpeed(CheckSpeedInterface $speed) : self
    {
        $this->speed = $speed;
        return $this;
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
        if ($this->isSpeed($event)) {
            return;
        }

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

    private function isSpeed(ER $event) : bool
    {
        if (empty($this->speed) || !$this->speed instanceof CheckSpeedInterface) {
            return false;
        }

        if (!$this->speed->isSpeed($event->getFd())) {
            return false;
        }

        $result = $this->speed->getErrorPacket();
        if (empty($result['message']) || empty($result['action'])) {
            $event->getServer()->close($event->getFd());
            return true;
        }

        $event->getServer()->send($this->pack->pack($result['message'], $result['action']), $event->getFd());
        System::sleep(0.5);
        $event->getServer()->close($event->getFd());

        return true;
    }
}
