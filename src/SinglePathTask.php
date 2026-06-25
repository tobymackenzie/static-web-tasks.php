<?php
namespace TJM\StaticWebTasks;
use Exception;
use TJM\TaskRunner\Task as Base;
use TJM\WebCrawler\Crawler;

class SinglePathTask extends Base{
	protected ?Crawler $crawler;
	//--destination: path where static build will go, web root
	protected string $destination;
	//--path: url / path to get content from
	protected ?string $path = null;

	public function __construct($crawler, string $destination, string $path){
		if($crawler instanceof Crawler){
			$this->crawler = $crawler;
		}else{
			$this->crawler = new Crawler($crawler);
		}
		$this->destination = $destination;
		$this->path = $path;
	}
	public function do(){
		//--crawl
		$this->crawler->crawl([$this->path]);

		//--build static file
		$response = $this->crawler->getResponse($this->path);
		if(is_file($this->destination)){
			$pathDest = $destination;
		}else{
			if(!file_exists($this->destination)){
				mkdir($this->destination);
			}
			if(isset($response->headers['content-type'])){
				$contentType = $response->headers['content-type'];
			}elseif(isset($response->headers['Content-Type'])){
				$contentType = $response->headers['Content-Type'];
			}else{
				$contentType = 'text/html';
			}
			if(substr($this->path, -1) === '/'){
				$fileDest = $this->path . 'index.html';
			}elseif(pathinfo($this->path, PATHINFO_EXTENSION) || $contentType !== 'text/html'){
				$fileDest = $this->path;
			}else{
				switch($this->getFormatForNodes()){
					case Task::FORMAT_NODES_DOT_HTML:
						$fileDest = $this->path . '.html';
					break;
					case Task::FORMAT_NODES_INDEX:
						$fileDest = $this->path . '/index.html';
					break;
				}
			}
			if(substr($fileDest, 0, 1) !== '/'){
				$fileDest = '/' . $fileDest;
			}
			$pathDest = $this->destination . $fileDest;
		}
		switch($response->getStatusCode()){
			case 200:
				$content = $response->getContent();
				if(!file_exists($pathDest) || $content !== file_get_contents($pathDest)){
					file_put_contents($pathDest, $content);
				}
			break;
			case 404:
			case 410:
				//--remove not found
				if(file_exists($pathDest)){
					unlink($pathDest);
				}
			break;
			default:
				//--otherwise let user know, fail
				//-! should put file for 30x in certain build scenarios, eg Github pages
				throw new Exception($response->getContent());
			break;
		}
	}

	//==conf
	protected function getFormatForNodes(){
		return Task::FORMAT_NODES_DOT_HTML;
	}
}
