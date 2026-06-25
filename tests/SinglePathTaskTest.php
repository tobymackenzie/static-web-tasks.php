<?php
namespace TJM\StaticWebTasks\Tests;
use TJM\Dev\Test\TestCase;
use TJM\StaticWebTasks\SinglePathTask;
use TJM\WebCrawler\Crawler;

class SinglePathTaskTest extends TestCase{
	protected string $dir = __DIR__ . '/tmp';
	protected string $webDir =  __DIR__ . '/resources/www';
	public function setUp(): void{
		mkdir($this->dir);
	}
	public function tearDown(): void{
		exec('rm -r ' . $this->dir);
	}
	public function test(){
		//--create exclude files
		$crawler = new Crawler([
			'client'=> 'php ' . $this->webDir . '/index.php',
		]);

		//--root
		$task = new SinglePathTask($crawler, $this->dir, '/');
		$task->do();
		$this->assertEquals("index.html\n", shell_exec('ls -1AR ' . $this->dir));

		//--text file
		$task = new SinglePathTask($crawler, $this->dir, '/text.txt');
		$task->do();
		$this->assertEquals("index.html\ntext.txt\n", shell_exec('ls -1AR ' . $this->dir));

		//--non-existant both sides
		$task = new SinglePathTask($crawler, $this->dir, '/nope');
		$task->do();
		$this->assertEquals("index.html\ntext.txt\n", shell_exec('ls -1AR ' . $this->dir));

		//--non-existant source side
		file_put_contents($this->dir . '/nope.html', '<title>Nope</title>');
		$task = new SinglePathTask($crawler, $this->dir, '/nope');
		$task->do();
		$this->assertEquals("index.html\ntext.txt\n", shell_exec('ls -1AR ' . $this->dir));
	}
}
