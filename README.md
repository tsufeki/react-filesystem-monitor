React Filesystem Monitor
========================

Asynchronous filesystem monitor based on React PHP.

Currently these implementations are available:

* `INotifyProcessMonitor` based on [`inotifywait`][inotify] command line utility, used on Linux.
* `FsWatchProcessMonitor` based on [`fswatch`][fswatch], used on OSX.

[inotify]: https://github.com/rvoicilas/inotify-tools/wiki
[fswatch]: http://emcrisostomo.github.io/fswatch/

All implementations' constructors take two arguments: a path to watch (file or
recursively watched directory) and optional array of event to watch for
(defaults to all events).

Available events:

* `access` i.e. read
* `attribute` - modification of permissions, timestamps etc.
* `close`
* `create`
* `delete`
* `modify`
* `move_from`, `move_to` - file move, fired with source and destination path respectively.
  Only those for paths inside watched dir are fired.
* `open`

These events pass as arguments: path which triggered it, boolean indicating
whether the path is a directory, event name and monitor instance itself.

Additional events:

* `all` - fired for all events above
* `start` - fired when watchers finished setting up
* `error`

Please note that not all backends support all events. `fswatch` won't emit
`open` and `close` events; also `start` is fired immediately after process starts
instead of when setup is complete.

Example
-------
```php
$loop = React\EventLoop\Factory::create();

$monitor = (new ReactFilesystemMonitor\FilesystemMonitorFactory())->create('foo/bar', ['modify', 'delete']);
$monitor->on('all', function ($path, $isDir, $event, $monitor) {
    echo sprintf("%s:  %s%s\n", $event, $path, $isDir ? ' [dir]' : '');
});
$monitor->start($loop);

$loop->run();
```
