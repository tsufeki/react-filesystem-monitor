<?php

namespace ReactFilesystemMonitor;

use Evenement\EventEmitterInterface;
use React\EventLoop\LoopInterface;

/**
 * @event start
 * @event stop
 * @event error
 *
 * @event all (string $path, bool $isDir, string $event, FilesystemMonitorInterface $monitor)
 *
 * @event access
 * @event attribute
 * @event close
 * @event create
 * @event delete
 * @event modify
 * @event move_from
 * @event move_to
 * @event open
 */
interface FilesystemMonitorInterface extends EventEmitterInterface
{
    /**
     * @param LoopInterface $loop
     */
    public function start(LoopInterface $loop);

    public function stop();
}
