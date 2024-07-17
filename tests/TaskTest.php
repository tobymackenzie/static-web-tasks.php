<?php
namespace TJM\StaticWebTasks\Tests;
use TJM\StaticWebTasks\Task;
use TJM\Dev\Test\TestCase;

class TaskTest extends TestCase{
	protected string $dir = __DIR__ . '/tmp';
	protected string $webDir =  __DIR__ . '/resources/www';
	public function setUp(): void{
		mkdir($this->dir);
	}
	public function tearDown(): void{
		exec('rm -r ' . $this->dir);
	}
	public function test(){
		//--create exclude file
		file_put_contents($this->dir . '/.htaccess', 'RewriteEngine On');
		//--create remove file
		file_put_contents($this->dir . '/asdf.txt', 'A S D F');

		$task = new Task([
			'client'=> 'php ' . $this->webDir . '/index.php',
		], $this->dir, [
			'exclude'=> ['/.htaccess'],
		]);
		$task->do();
		chdir($this->dir);
		$this->assertEquals(file_get_contents(__DIR__ . '/resources/expects.txt'), shell_exec('ls -1AR'));
	}
}
