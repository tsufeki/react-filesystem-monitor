<?php

namespace ReactFilesystemMonitor;

class FilesystemMonitorFactory implements FilesystemMonitorFactoryInterface
{
    public function create(string $path, array $events = null, array $options = [])
    {
        return new INotifyProcessMonitor($path, $events, $options);
    }
}
