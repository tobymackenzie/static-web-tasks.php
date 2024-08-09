<?php
namespace TJM\StaticWebTasks;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use TJM\TaskRunner\Task as Base;
use TJM\WebCrawler\Crawler;

class Task extends Base{
	const FORMAT_NODES_DOT_HTML = 1;
	const FORMAT_NODES_INDEX = 2;

	protected ?Crawler $crawler;
	//--destination: path where static build will go
	protected string $destination;
	//--exclude: files to exclude during sync, eg files that should remain in destination without being deleted.  will be attached as `--exclude` opts to `rsync`
	protected array $exclude = [];
	//--paths: urls / path(s) to get content from
	protected array $paths = ['/'];
	//--syncOpts: opts to pass to `rsync`
	protected ?string $syncOpts = '-a';

	public function __construct($crawler, string $destination, array $opts = null){
		if(is_array($opts)){
			foreach($opts as $key=> $value){
				$this->$key = $value;
			}
		}
		if($crawler instanceof Crawler){
			$this->crawler = $crawler;
		}else{
			$this->crawler = new Crawler($crawler);
		}
		$this->destination = $destination;
	}
	public function do(){
		//--build into temporary directory
		//-# so we aren't modifying in place
		//-# also needed to support remote destinations
		$tmpDir = 'tmp-staticbuild-' . date('YmdHis');
		mkdir($tmpDir);

		//--build sync opts
		$syncOpts = $this->syncOpts;
		foreach($this->exclude as $exclude){
			$syncOpts .= ' --exclude=' . escapeshellarg($exclude);
		}

		//--sync current structure to tmp dir
		shell_exec("rsync {$syncOpts} {$this->destination}/ {$tmpDir}/");

		//--crawl
		$this->crawler->crawl($this->paths);

		//--build static files
		$buildPaths = [];
		$loadPaths = [];
		foreach($this->crawler->getVisitedPaths() as $path){
			if(!in_array($path, $loadPaths) && !in_array($path, $buildPaths)){
				$response = $this->crawler->getResponse($path);
				if($response->getStatusCode() !== 200){
					continue;
				}
				$content = $this->crawler->getResponse($path)->getContent();
				if(substr($path, -1) === '/'){
					$fileDest = $path . 'index.html';
				}elseif(pathinfo($path, PATHINFO_EXTENSION)){
					$fileDest = $path;
				}else{
					switch($this->getFormatForNodes()){
						case self::FORMAT_NODES_DOT_HTML:
							$fileDest = $path . '.html';
						break;
						case self::FORMAT_NODES_INDEX:
							$fileDest = $path . '/index.html';
						break;
					}
				}
				$pathDest = $tmpDir . $fileDest;
				exec('mkdir -p ' . dirname($pathDest));
				if(!file_exists($pathDest) || $content !== file_get_contents($pathDest)){
					file_put_contents($pathDest, $content);
				}
				$buildPaths[] = $fileDest;
				$loadPaths[] = $path;
			}
		}

		//--remove non-built paths
		$removeLength = strlen($tmpDir);
		$glob = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmpDir, FilesystemIterator::SKIP_DOTS));
		foreach($glob as $file){
			$path = substr($file->getPathname(), $removeLength);
			if(!in_array($path, $buildPaths)){
				unlink($file->getPathname());
			}
		}
		//--remove empty dirs, eg when removing page
		shell_exec("find {$tmpDir} -type d -empty -delete");


		//--sync to destination
		shell_exec("rsync {$syncOpts} --delete {$tmpDir}/ {$this->destination}/");

		//--clean up
		passthru('rm -r ' . $tmpDir);
	}

	//==conf
	protected function getFormatForNodes(){
		return self::FORMAT_NODES_DOT_HTML;
	}
}
