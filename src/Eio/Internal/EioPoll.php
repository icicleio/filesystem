<?php
namespace Icicle\File\Eio\Internal;

use Icicle\Loop;

class EioPoll
{
    /**
     * @var \Icicle\Loop\Watcher\Io
     */
    private $poll;

    /**
     * @var int
     */
    private $requests = 0;

    public function __construct()
    {
        \eio_init();
        $this->poll = $this->createPoll();
    }

    public function listen()
    {
        if (0 === $this->requests++) {
            if ($this->poll->isFreed()) {
                \eio_init();
                $this->poll = $this->createPoll();
            }

            $this->poll->listen();
        }
    }

    public function done()
    {
        if (0 === --$this->requests) {
            $this->poll->cancel();
        }
    }

    public function __destruct()
    {
        $this->poll->free();
    }

    private function createPoll()
    {
        return Loop\poll(\eio_get_event_stream(), function () {
            while (\eio_npending()) {
                \eio_poll();
            }
        }, true);
    }
}
