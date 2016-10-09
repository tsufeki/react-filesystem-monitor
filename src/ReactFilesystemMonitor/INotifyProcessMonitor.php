<?php

namespace ReactFilesystemMonitor;

use Evenement\EventEmitter;
use React\ChildProcess\Process;
use React\EventLoop\LoopInterface;
use React\Stream\ReadableStreamInterface;
use React\Stream\Util;
use ReactLineStream\LineStream;

class INotifyProcessMonitor extends EventEmitter implements FilesystemMonitorInterface
{
    const EVENT_MAP = [
        'ACCESS' => 'access',
        'ATTRIB' => 'attribute',
        'CLOSE_WRITE' => 'close',
        'CLOSE_NOWRITE' => 'close',
        'CREATE' => 'create',
        'DELETE' => 'delete',
        'DELETE_SELF' => 'delete',
        'MODIFY' => 'modify',
        'MOVE_SELF' => 'move_from',
        'MOVED_FROM' => 'move_from',
        'MOVED_TO' => 'move_to',
        'OPEN' => 'open',
    ];

    /**
     * @var string
     */
    private $path;

    /**
     * @var string[]
     */
    private $events;

    /**
     * @var Process
     */
    private $process;

    /**
     * @var ReadableStreamInterface
     */
    private $stdout;

    /**
     * @var ReadableStreamInterface
     */
    private $stderr;

    public function __construct($path, array $events = null, array $options = [])
    {
        $this->path = realpath($path);

        if ($events === null) {
            $events = array_values(self::EVENT_MAP);
        }
        $this->events = $events;
    }

    public function start(LoopInterface $loop)
    {
        $eventsCmd = '';
        foreach ($this->events as $event) {
            foreach (array_keys(self::EVENT_MAP, $event) as $inotifyEvent) {
                $eventsCmd .= ' -e ' . strtolower($inotifyEvent);
            }
        }

        $cmd = sprintf("%s -m -r -c %s %s",
            escapeshellarg(isset($options['inotifywait_cmd']) ? $options['inotifywait_cmd'] : 'inotifywait'),
            $eventsCmd,
            escapeshellarg($this->path)
        );

        $this->process = new Process($cmd);
        Util::forwardEvents($this->process, $this, ['error']);
        $this->process->on('exit', function () use ($cmd) {
            $this->emit('error', [new \Exception(sprintf('"%s" exited', $cmd))]);
        });

        $this->process->start($loop);
        $this->stdout = new LineStream($this->process->stdout);
        $this->stderr = new LineStream($this->process->stderr);

        $this->stderr->on('line', function ($line) {
            if (trim($line) === 'Watches established.') {
                $this->emit('start');
            }
        });

        $this->stdout->on('line', [$this, 'handleEvent']);
    }

    public function close()
    {
        $this->process->removeAllListeners('exit');
        $this->process->terminate();
    }

    /**
     * @internal
     *
     * @param string $line
     */
    public function handleEvent($line)
    {
        $fields = str_getcsv($line);
        $path = (isset($fields[0]) ? $fields[0] : '') . (isset($fields[2]) ? $fields[2] : '');
        $path = rtrim($path, '/');
        $events = explode(',', isset($fields[1]) ? $fields[1] : '');

        foreach ($events as $inotifyEvent) {
            if (isset(self::EVENT_MAP[$inotifyEvent])) {
                $event = self::EVENT_MAP[$inotifyEvent];
                $this->emit($event, [$path, $event, $this]);
                $this->emit('all', [$path, $event, $this]);
            }
        }
    }
}
