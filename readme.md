Static Web Tasks
=======

Task to build static version of website.  Use like:

``` php
use TJM\StaticWebTasks\Task;
$task = new Task([
	'host'=> 'example.com',
	'scheme'=> 'https',
], __DIR__ . '/tmp', [
	'exclude'=> ['/.htaccess'],
]);
$task->do();
```

See code for more details.  Early implementation, interface may change.

License
------

<footer>
<p>SPDX-License-Identifier: 0BSD</p>
</footer>
