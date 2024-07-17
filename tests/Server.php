<?php
namespace TJM\StaticWebTasks\Tests;

class Server{
	const CONTENT_TYPES = [
		'html'=> 'text/html',
	];
	protected string $root;
	public function __construct(string $root){
		$this->root = $root;
	}
	public function __invoke(string $path){
		echo $this->getResponse($path) . "\n";
	}
	public function getResponse(string $path){
		if(empty($path)){
			$path = '/';
		}
		$file = $this->root . $path;
		if(!is_file($file)){
			if(substr($path, -1) !== '/'){
				$file .= '/';
			}
			$file .= 'index.html';
			if(!is_file($file)){
				$file = null;
			}
		}
		$response = 'HTTP/1.1 ';
		if(empty($file)){
			$status = 404;
			$response .=  "{$status} Not Found\r\n";
			$response .=  "Content-Type: text/html\r\n";
			$response .= "\r\n";
			$response .= "<!doctype html>Not found";
		}else{
			$content = file_get_contents($file);
			$status = 200;
			if(preg_match(':\.([\w\-]+)$:', $file, $matches)){
				$contentType = self::CONTENT_TYPES[$matches[1]] ?? null;
			}
			if(empty($contentType)){
				if(stripos($content, '<!doctype') !== false){
					$contentType = 'text/html';
				}else{
					$contentType = 'text/plain';
				}
			}

			$response .= "{$status} OK\r\n";
			$response .= 'Content-Length: ' . strlen($content) . "\r\n";
			$response .= "Content-Type: {$contentType}\r\n";
			$response .= "\r\n";
			$response .= $content;
		}
		return $response;
	}
}
