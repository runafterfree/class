<?php
class HttpCurl
{
    var $debug = true;
    //请求参数设定
    var $postdata = '';     //post请求参数
    var $referer;           //引用网址
    var $headers = array(
        'Accept'=>'text/xml,application/xml,application/xhtml+xml,text/html,text/plain,image/png,image/jpeg,image/gif,*/*',
        'User-Agent'=>'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/35.0.1916.114 Safari/537.36',
        'Accept-encoding'=>'gzip',
        'Accept-language'=>'zh_CN,zh,en_us',
    );
    var $timeout = 2;            //请求默认超时时间
    var $connect_timeout = 2;        //连接时间
    var $use_cookies = false;    //是否启用cookie
    var $cookie_file = '/cookie.txt';      //cookie文件路径
    var $max_redirects = 1;      //0表示不追踪跳转
    var $body_only = true;       //是否获取内容信息
    var $response_header = '';    //返回头部信息
    var $status;

    //代理服务器参数设定
    var $host='',$port='';
    var $username;
    var $password;

    var $content = '';
    var $errormsg='';
    function HttpCurl()
    {
        
    }

    function setProxy($host, $username='', $password='')
    {
        $this->host = $host;
        $this->username = $username;
        $this->password = $password;
    }

    function get($path, $data = false)
    {
        $this->path = $path;
        $this->method = 'GET';
        if ($data)
        {
            $this->path .= '?' . $this->buildQueryString($data);
        }
        return $this->doRequest();
    }

    function post($path, $data)
    {
        $this->path = $path;
        $this->method = 'POST';
        $this->postdata = $data;
        return $this->doRequest();
    }

    function getHeader($path, $data)
    {
        $body_only = $this->body_only;
        $this->body_only = false;
        $this->method = 'GET';
        if ($data)
        {
            $this->path .= '?' . $this->buildQueryString($data);
        }
        $re = $this->doRequest();

        $this->body_only = $body_only;

        return $re;
    }

    function buildQueryString($data)
    {
        $querystring = '';
        if (is_array($data))
        {
            // Change data in to postable data
            foreach ($data as $key => $val)
            {
                if (is_array($val))
                {
                    foreach ($val as $val2)
                    {
                        $querystring .= urlencode($key) . '=' . urlencode($val2) . '&';
                    }
                } else
                {
                    $querystring .= urlencode($key) . '=' . urlencode($val) . '&';
                }
            }
            $querystring = substr($querystring, 0, -1); // Eliminate unnecessary &
        } else
        {
            $querystring = $data;
        }
        return $querystring;
    }

    function doRequest()
    {
        $ch = curl_init();
        if(!$ch)
        {
            $this->errormsg = 'CURL初始化失败';
        }

        $opt = array(
            CURLOPT_URL => $this->path,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT=>$this->connect_timeout,
            CURLOPT_HEADER => true,
            CURLOPT_RETURNTRANSFER => true,
        );
        if($this->use_cookies)
        {
            if(file_exists($this->cookie_file))
            {
                $opt[CURLOPT_COOKIEFILE] = $this->cookie_file;
            }
            $opt[CURLOPT_COOKIEJAR] = $this->cookie_file;
        }
        
        if(!empty($this->headers))
        {
            $header = array();
            foreach($this->headers as $k=>$val)
                $header[] = $k.': '.$val;
            $opt[CURLOPT_HTTPHEADER] = $header;
            unset($header);
        }
        if($this->max_redirects)    //设置允许重定向次数
        {
            $opt[CURLOPT_FOLLOWLOCATION] = true;
            $opt[CURLOPT_MAXREDIRS] = $this->max_redirects;
        }
        if(!$this->body_only)
            $opt[CURLOPT_NOBODY] = true;

        if($this->method=='POST')   //设置post参数
        {
            $opt[CURLOPT_POST] = TRUE;
            if(!empty($this->postdata))
                $opt[CURLOPT_POSTFIELDS] = $this->postdata;
        }
        if($this->host) //使用代理进行访问
        {
            $opt[CURLOPT_PROXY] = $this->host;
            if($this->username)
                $opt[CURLOPT_PROXYUSERPWD] = $this->username.':'.$this->passwd;
            //判断是否https代理
            if(strpos($this->host,'https://')===0)
            {
                $opt[CURLOPT_SSL_VERIFYPEER] =  FALSE;
                $opt[CURLOPT_SSL_VERIFYHOST] = FALSE;
            }
        }
        
        curl_setopt_array($ch, $opt);
        $this->content = curl_exec($ch);
        if(!$this->content || !strpos($this->content,'200 OK'))
        {
            $this->errormsg = curl_error($ch);
            curl_close($ch);
            return false;
        }
        $this->errormsg = '';

        //获得头部信息数组
        if($this->body_only)
        {
            $info = curl_getinfo($ch);
            $header = substr($this->content, 0, $info['header_size']);
            $this->content = substr($this->content, $info['header_size']);
        }
        else
        {
            $header = $this->content;
            $this->content = '';
        }
        

        //获取返回头信息
        $this->response_header = array();
        $header = preg_split("/[\r\n]+/", $header);
        foreach($header as $i=>$val)
        {
            if(empty($val))
                continue;
            if($i==0)
                $this->status = $val;
            else
            {
                $pos = strpos($val, ':');
                $v = substr($val, $pos+1);
                $k = substr($val, 0, $pos);
                $this->response_header[$k] = $v;
            }
        }
        if($this->content && isset($this->response_header['Content-Encoding']))
        {
            switch($this->response_header['Content-Encoding'])
            {
            case ' gzip':
                $this->content = substr($this->content, 10); // See http://www.php.net/manual/en/function.gzencode.php
                $this->content = gzinflate($this->content);
                break;
            case ' deflate':
                $this->content = gzdeflate($this->content);
                break;
            }
        }

        curl_close($ch);
        return true;
    }


    function getError()
    {
        return $this->errormsg;
    }

    function setTimeout($val)
    {
        $this->timeout = $val;
    }
    function setConnectTimeout($val)
    {
        $this->connect_timeout = $val;
    }
    function setReferee($string)
    {
        $this->headers['Referer'] = $string;
    }

    function setUserAgent($string)
    {
        $this->headers['User-Agent'] = $string;
    }

    function setUseCookies($boolean)
    {
        $this->use_cookies = $boolean;
    }

    function setMaxRedirects($num)
    {
        $this->max_redirects = $num;
    }

    function setBodyOnly($boolean)
    {
        $this->body_only = $boolean;
    }
    function setDebug($boolean)
    {
        $this->debug = $boolean;
    }
    function debug($msg)
    {
        if ($this->debug)
        {
            print '<div style="border: 1px solid red; padding: 0.5em; margin: 0.5em;"><strong>HttpCurl Debug:</strong> ' .
                $msg;
            ob_start();
            $content = htmlentities(ob_get_contents());
            ob_end_clean();
            print '<pre>' . $content . '</pre>';
            print '</div>';
        }
    }
}
?>
