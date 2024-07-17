<?php
namespace TJM\StaticWebTasks;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use TJM\TaskRunner\Task as Base;
use TJM\WebCrawler\Crawler;

class Task extends Base{
	protected ?Crawler $crawler;
	//--destination: path where static build will go
	protected string $destination;
	//--exclude: files to exclude during sync, eg files that should remain in destination without being deleted.  will be attached as `--exclude` opts to `rsync`
	protected array $exclude = [];
	//--follow: whether to crawl content for `href` in resulting html to add more urls to follow
	//-!! follow should be option on crawler?
	protected bool $follow = true;
	// ?headers(bool): whether to store response headers.  not needed for normal use, how to store?
	// ?outputFormat: output format to store files in dest
		// would affect things like directory structure, eg `/about` vs `/about/index.html`
		// redirects would be different between github and cloudflare pages formats
	//--paths: urls / path(s) to get content from
	protected array $paths = ['/'];
	// query(string): querystring params to add to all http requests
		// useful if we want site to have certain modifications for static export
		// determined automatically from entries if urls and not otherwise set
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
		/*
		loop through paths via `shell_exec` or `curl`, save to file
		need to not update dest files if unchanged (for rsync to elsewhere)
		need to monitor paths that still exist, remove dest files that should no longer be there

		rsync from dest to tmp (with excludes to prevent extra data)
		gets content into string, compares to content of existing file, if different, write
		remove any files not in list
		rsync back to dest, with excludes, delete options enabled
			maybe allow dry run with output to show what would be copied / removed
			maybe use git repo on tmp so we can see what has changed
				init with all files after first rsync
				commit when done
		*/
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
		$this->crawler->crawl($this->paths, $this->follow);

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
					$fileDest = $path . '/index.html';
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
			// if($glob->isDot()) continue;
			$path = substr($file->getPathname(), $removeLength);
			if(!in_array($path, $buildPaths)){
				unlink($file->getPathname());
			}
		}

		//--sync to destination
		shell_exec("rsync {$syncOpts} --delete {$tmpDir}/ {$this->destination}/");

		//--clean up
		passthru('rm -r ' . $tmpDir);
	}
}
