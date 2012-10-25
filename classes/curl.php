<?php
//require 'classes\url_to_absolute.php'; 

class curl {
    
    public $header;
    public $lastUrl;
    private $cookie;
    
    public function get($url, $data = null, $redirects = 10) {
        if ($data != null)
        {
            $getStr = "?";
            foreach ($data as $key => $value) {
                $getStr .= $key . "=" . $value . "&";
            }
            $getStr = rtrim($getStr, "&");
            $url .= $getStr;    
        }
        
        $ch = curl_init($url);
        $content = $this->curl_exec_follow($ch, $redirects);
        
        $err     = curl_errno( $ch );
        $errmsg  = curl_error( $ch );
        $header  = curl_getinfo( $ch );
        $last_url = curl_getinfo($ch, CURLINFO_REDIRECT_URL); 
        $t1 = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL); 
        curl_close( $ch );

        $header['errno']   = $err;
        $header['errmsg']  = $errmsg;
        $header['content'] = $content;
        $this->header = $header;
        

        return $content;
    }
    
    public function curl_exec_follow(/*resource*/ $ch, /*int*/ &$maxredirect = null) {
        $mr = $maxredirect === null ? 5 : intval($maxredirect);
        if (ini_get('open_basedir') == '' && ini_get('safe_mode' == 'Off')) {
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, $mr > 0);
            curl_setopt($ch, CURLOPT_MAXREDIRS, $mr);
        } else {
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
            if ($mr > 0) {
                $newurl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);

                $rch = curl_copy_handle($ch);
                curl_setopt($rch, CURLOPT_HEADER, true);
                curl_setopt($rch, CURLOPT_NOBODY, true);
                curl_setopt($rch, CURLOPT_FORBID_REUSE, false);
                curl_setopt($rch, CURLOPT_RETURNTRANSFER, true);
                do {
                    curl_setopt($rch, CURLOPT_URL, $newurl);
                    $header = curl_exec($rch);
                    if (curl_errno($rch)) {
                        $code = 0;
                    } else {
                        $code = curl_getinfo($rch, CURLINFO_HTTP_CODE);
                        if ($code == 301 || $code == 302) {
                            preg_match('/Location:(.*?)\n/', $header, $matches);
                            $location = trim(array_pop($matches));
                            $newurl = url_to_absolute($newurl, $location);
                        } else {
                            $code = 0;
                        }
                    }
                } while ($code && --$mr);
                curl_close($rch);
                if (!$mr) {
                    if ($maxredirect === null) {
                        trigger_error('Too many redirects. When following redirects, libcurl hit the maximum amount.', E_USER_WARNING);
                    } else {
                        $maxredirect = 0;
                    }
                    return false;
                }
                curl_setopt($ch, CURLOPT_URL, $newurl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            }
        }
        return curl_exec($ch); 
    } 
}

?>
