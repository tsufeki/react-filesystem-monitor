<?php

namespace ReactFilesystemMonitor;

interface FilesystemMonitorFactoryInterface
{
    /**
     * @param string     $path
     * @param array|null $events
     * @param array      $options
     *
     * @return FilesystemMonitorInterface
     */
    public function create(string $path, array $events = null, array $options = []);
}
