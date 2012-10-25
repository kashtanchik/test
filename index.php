<?php

require 'classes\url_to_absolute.php'; 
require 'classes\curl.php'; 

class TubeParser {

    private $curl;
    private $dom;
    public $files;
    
    public function __construct() {
        $this->curl = new Curl();
        $this->dom = new DOMDocument();
        
        // turn off parser errors
        libxml_use_internal_errors( true ); 
        libxml_clear_errors();
    }
    
    public function parse($query, $site = "all", $pageFrom = 1, $pageTo = null) {
        $url = "http://www.filestube.com/search.html";
        $siteId = $this->parseSiteId($site);
        $page = $pageFrom;
        $files = array();
        
        do{
            $data = array(
                "q" => $query,
                "hosting" => $siteId,
                "select" => "all",       // extensions
                "page" => $page
                );

            $resp = $this->curl->get($url, $data);
            $this->dom->loadHTML($resp);
            $urls = $this->parseUrls();
            $files = array_merge($files, $this->parseFiles($urls));
            
            $page++;
        } while (count($urls) > 0 &&
                $page <= $pageTo);
        
        $this->files = $files;
    }

    private function parseSiteId($site) {
        $url = "http://www.filestube.com/advanced_search.html";
        $resp = $this->curl->get($url);
        
        $this->dom->loadHTML($resp);
        $xpath = new DOMXPath($this->dom);
        $expression = "//*[@name='hosting']/option[contains(translate(text(), 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), '"
            .$site."')]";
        return $xpath->query($expression)->item(0)->getAttribute("value");
    }
           
    private function parseUrls() {
        $xpath = new DOMXPath($this->dom);
        $expression = "//div[@id='newresult']/a[@class='resultsLink']";
        $nUrls = $xpath->query($expression);
        
        $urls = array();
        $url = "http://www.filestube.com";
        foreach ($nUrls as $nUrl) {
            $href = $nUrl->getAttribute("href");
            $url = url_to_absolute($url, $href);
            $urls[] = $url;
        }
        
        return $urls;
    }
    
    private function parseFiles($urls) {
        $files = array();
        
        foreach ($urls as $url) {
            //$url = "http://www.filestube.com/rapidshare/fafuAQD4K1tyoAUqiUoxkX/Morteltrf-Subs-BINMoVIE-ORG.html";
            $resp = $this->curl->get($url);
            $this->dom->loadHTML($resp);
            $xpath = new DOMXPath($this->dom);
            
            $file = array();
//            $t1 = $xpath->query("//div[span[contains(text(), 'Added')]]/text()")->item(0);
//            $t2 = $t1->getNodePath();
            $file["PageTitle"] = $xpath->query("/html/body/div[2]/div[3]/div[2]/div[1]/h1")
                    ->item(0)
                    ->nodeValue;
            $file["Page"] = $url;
            $file["Added"] = $xpath->query("//div[span[contains(text(), 'Added')]]/text()")
                    ->item(0)
                    ->nodeValue;
            $directLinks = $xpath->query("//div/pre[@id='copy_paste_links']")
                    ->item(0)
                    ->nodeValue;
            $directLinks = trim($directLinks);
            $file["Links"] = explode("\n", $directLinks);
            $sizeNodes = $xpath->query("//tr/td[@class='tright alt_width3']");
            $totalSize = 0;
            foreach ($sizeNodes as $sizeNode) {
                $size = $this->convertToBytes($sizeNode->nodeValue);
                $totalSize += $size;
            }
            $file["Size"] = $totalSize;
            $file["SourceTitle"] = $xpath->query("//div[span[contains(text(), 'Source title')]]/div/h3")
                    ->item(0)
                    ->nodeValue;
            $file["Source"] = $xpath->query("//div[span[contains(text(), 'source')]]/div/a")
                    ->item(0)
                    ->nodeValue;
            
            $files[] = $file;
        }
        
        return $files;
    }
    
    function convertToBytes($from){
        $number=substr($from,0,-2);
        switch(strtoupper(substr($from,-2))){
            case "KB":
                return $number*1024;
            case "MB":
                return $number*pow(1024,2);
            case "GB":
                return $number*pow(1024,3);
            case "TB":
                return $number*pow(1024,4);
            case "PB":
                return $number*pow(1024,5);
            default:
                return $from;
        }
    }

    public function save($filename) {
        $f = fopen($filename, 'w');
        
        // write headers
        $headers = array_keys($this->files[0]);
            fputcsv($f, $headers);
        
        foreach ($this->files as $file) {
            $row = array();
            
            foreach ($file as $value) {
                if (is_array($value))
                    $row[] = implode(";", $value);
                else
                    $row[] = $value;
            }
            
            fputcsv($f, $row);  
        }
        
        fclose($f);
    }
}

$keyword = "DVDRIP";
$site = "filefactory.com";
$fileName = $keyword . "-" . $site . ".csv";

$tubeParser = new TubeParser();
$tubeParser->parse($keyword, $site, 1, 5);
$tubeParser->save($fileName);

?>
