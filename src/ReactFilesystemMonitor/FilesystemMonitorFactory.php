<?php

namespace ReactFilesystemMonitor;

class FilesystemMonitorFactory implements FilesystemMonitorFactoryInterface
{
    public function create(string $path, array $events = null, array $options = [])
    {
        if (php_uname('s') === 'Linux') {
            return new INotifyProcessMonitor($path, $events, $options);
        }

        return new FsWatchProcessMonitor($path, $events, $options);
    }
}
