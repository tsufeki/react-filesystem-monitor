<?php

namespace ReactFilesystemMonitor;

use Evenement\EventEmitter;
use React\ChildProcess\Process;
use React\EventLoop\LoopInterface;
use React\Stream\ReadableStreamInterface;
use React\Stream\Util;
use ReactLineStream\LineStream;

class FsWatchProcessMonitor extends EventEmitter implements FilesystemMonitorInterface
{
    const EVENT_MAP = [
        'PlatformSpecific' => 'access',
        'AttributeModified' => 'attribute',
        'Link' => 'attribute',
        'OwnerModified' => 'attribute',
        'Created' => 'create',
        'Removed' => 'delete',
        'Updated' => 'modify',
        'MovedFrom' => 'move_from',
        'MovedTo' => 'move_to',
    ];

    const BATCH_END = 'BATCH_END';

    /**
     * @var string
     */
    private $fswatchCmd;

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

    /**
     * @var string
     */
    private $stderrLog;

    /**
     * @var array [string path => [string eventName => true]]
     */
    private $batchEvents = [];

    /**
     * @param string     $path
     * @param array|null $events
     * @param array      $options
     */
    public function __construct(string $path, array $events = null, array $options = [])
    {
        $this->fswatchCmd = $options['fswatch_cmd'] ?? 'fswatch';
        $this->path = realpath($path);

        if ($events === null) {
            $events = array_values(self::EVENT_MAP);
        }
        $this->events = $events;
    }

    public function start(LoopInterface $loop)
    {
        $flags = [
            '--recursive',
            '--print0',
            '--format="%f:%p"', // "Created IsDir:/tmp/foo"
            '--batch-marker=' . self::BATCH_END,
            '--follow-links',
            '--allow-overflow',
            '--event=Overflow',
            '--event=IsDir',
        ];

        foreach ($this->events as $event) {
            foreach (array_keys(self::EVENT_MAP, $event) as $fswatchEvent) {
                $flags[] = '--event=' . $fswatchEvent;
            }
            if ($event === 'access') {
                $flags[] = '--access';
            }
        }

        $cmd = sprintf('exec %s %s %s',
            escapeshellarg($this->fswatchCmd),
            implode(' ', array_unique($flags)),
            escapeshellarg($this->path)
        );

        $this->stderrLog = '';
        $this->process = new Process($cmd, null, ['LC_ALL' => 'C']);
        Util::forwardEvents($this->process, $this, ['error']);
        $this->process->on('exit', function ($exitCode) {
            $this->emit('error', [new \Exception(sprintf('fswatch exited (%s): %s', $exitCode, $this->stderrLog))]);
        });

        $this->process->start($loop);
        $this->stdout = new LineStream($this->process->stdout, "\0");
        $this->stderr = new LineStream($this->process->stderr);

        $this->batchEvents = [];
        $this->stderr->on('line', function ($line) {
            $line = trim($line);
            if ($line === 'Watches established.') {
                $this->emit('start');
            } elseif (explode('.', $line)[0] !== 'Setting up watches') {
                $this->stderrLog .= ' ' . $line;
            }
        });

        $this->stdout->on('line', [$this, 'handleEvent']);
    }

    public function stop()
    {
        $this->process->removeAllListeners('exit');
        $this->process->terminate();
        $this->emit('stop');
    }

    /**
     * @internal
     *
     * @param string $line
     */
    public function handleEvent(string $line)
    {
        $line = trim($line, "\0");
        if ($line === self::BATCH_END) {
            $this->handleBatch();

            return;
        }

        list($eventsString, $path) = explode(':', $line, 2);
        $events = explode(' ', $eventsString);
        $path = rtrim($path, '/');
        foreach ($events as $event) {
            $this->batchEvents[$path][$event] = true;
            if ($event === 'Overflow') {
                $this->emit('error', [new \RuntimeException('fswatch overflow')]);
            }
        }
    }

    private function handleBatch()
    {
        $batchEvents = $this->batchEvents;
        $this->batchEvents = [];

        foreach ($batchEvents as $path => $events) {
            $isDir = isset($events['IsDir']);
            foreach ($events as $fswEvent => $_) {
                if (isset(self::EVENT_MAP[$fswEvent])) {
                    $event = self::EVENT_MAP[$fswEvent];
                    $this->emit($event, [$path, $isDir, $event, $this]);
                    $this->emit('all', [$path, $isDir, $event, $this]);
                }
            }
        }
    }
}
