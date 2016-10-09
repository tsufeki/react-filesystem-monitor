React Filesystem Monitor
========================

Asynchronous filesystem monitor based on React PHP.

Currently there is one implementation available:

* `INotifyProcessMonitor` based on `inotifywait` Linux command line utility.

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

These event pass as arguments path which triggered it and event name.

Additional events:
* `all` - fired for all events above
* `start` - fired when watchers finished setting up
* `error`

Example
-------
```php
$loop = React\EventLoop\Factory::create();

$monitor = new ReactFilesystemMonitor\INotifyProcessMonitor('foo/bar', ['modify', 'delete']);
$monitor->on('all', function ($path, $event) {
    echo sprintf("%s:  %s\n", $event, $path);
});
$monitor->start($loop);

$loop->run();
```
