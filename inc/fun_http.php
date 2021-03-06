<?php

/**
    http请求函数
 */

    /** 1
     * 快速 file_get_content()
     * @param string $url 目标url
     * @param array|null $opt 请求配置
     * @return false|string 请求结果字符串
     */
    function easy_file_get_content(string $url,array $opt=null){
        $opt = empty($opt)? easy_build_opt() : $opt;
        $result = file_get_contents($url,false,stream_context_create($opt));
        err_msg_file_get_content($opt,$http_response_header);
        return $result;
    }

    /** 2
     * 如果file_get_content失败，则输出提示
     * 无需参数，
     * @param array|null $opts 请求头信息
     * @param array|null $response 请求响应信息
     * @param string|null $user 用户信息
     */
    function err_msg_file_get_content(array $opts=null,array $response=null,string $user=null){

        // 如果未传递$response，尝试获取global的$http_response_header变量
        if(isset($response)){
            $http_response_header = $response;
        }else{
            global  $http_response_header;
        }
        // 校验程序是否传递了http返回变量
        if(!is_array($http_response_header)){
            build_err("不存在响应头信息");
        }
        if(empty($user)){
            global $user;
        }
        // 校验程序是否传递了user变量
        if(!check_session($user)){
            // 请求失败且未登录
            if(!check_http_code($http_response_header[0])){
                build_err("http请求失败");
            }
        }else if(!check_http_code($http_response_header[0])){
            // 请求失败，但已登录
            build_err("http请求失败",false);
            easy_dump(error_get_last());
            if($opts){
                easy_echo("以下为请求头信息：");
                easy_dump($opts);
            }
            easy_echo("以下为响应头信息：");
            easy_dump($http_response_header);
            die;
        }
    }

    /** 3
     * 创建file_get_content或fopen的opt
     *
     * @param string $method 指定请求方法，默认GET
     * @param string|array $content 指定请求参数键值对
     * @param array $header 指定请求头数组（注意只能是一维字符串数组，每个一条），默认使用使用百度网盘ua
     * 例如  ['User-Agent:pan.baidu.com','Cookie:age=12'])
     * @return array
     */
    function easy_build_opt(string $method="GET",$content=null, array $header=["User-Agent:pan.baidu.com"]){

        if(!empty($content)){
            // 是否为数组？
            if(is_array($content)){
                $content = http_build_query($content);
            }
        }

        return [
            'http'=>[
                'method'=>$method,
                'header'=>$header,
                'content'=>$content,
            ],
            'ssl'=>[
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ];
    }

    /** 4
     * 设置内置允许的状态码
     * @param string $response_line 返回的请求行
     * @return bool 是否为允许的响应状态码，如果true为允许，false为失败
     */
    function check_http_code(string $response_line){
        $arr = explode(" ",$response_line);
        $code = (int)$arr[1]; // 响应状态码
        $check = [200,302];   // 合法的状态码，不在这其中的为不合法.
        foreach ($check as $k=>$v){
            if($v==$code){
                return true;
            }
        }
        return false;
    }

    /** 5
     * 获取重定向地址
     * @param array $response_header
     * @return mixed|string
     */
    function get_http_redirect(array $response_header){
        foreach ($response_header as $k=>$v){
            $split = explode(": ",$v);
            if(count($split)>1){
                if($split[0]=="Location"){
                    return $split[1];
                }
            }
        }
        return "";
    }

    /** 6
     * 快速进行fopen
     * @param string $filename
     * @param string $mode
     * @param array|null $opt
     * @return false|resource
     */
    function easy_fopen(string $filename,string $mode,array $opt=null){
        $opt = empty($opt)? easy_build_opt() : $opt;

        return @fopen($filename,$mode,false,stream_context_create($opt));
    }

    /** 通用 curl
    * @param $url
    * @return bool|string
    */
    function easy_curl($url){
        $ch = curl_init($url);
        // 通用设置
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        $cus_header = ["User-Agent: pan.baidu.com"];
        curl_setopt($ch,CURLOPT_HTTPHEADER,$cus_header);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }

    function headerHandler($curl, $headerLine) {
        $len = strlen($headerLine);
        // HTTP响应头是以:分隔key和value的
        $split = explode(':', $headerLine, 2);
        if (count($split) > 1) {
            $key = trim($split[0]);
            $value = trim($split[1]);
            // 将响应头的key和value存放在全局变量里
            $GLOBALS['G_HEADER'][$key] = $value;
        }
        return $len;
    }
    /**
     * 使用 curl 获取 响应头
     */
    function easy_curl_head($url){
        $ch = curl_init($url);
        // 通用设置
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_NOBODY, true);  // 不要body，否则大文件会卡死
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, "headerHandler"); // 设置header处理函数
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);  // 从 PHP 5.1.3 开始可用。这个很关键，就是允许你查看请求header
        $cus_header = ["User-Agent: pan.baidu.com"];
        curl_setopt($ch,CURLOPT_HTTPHEADER,$cus_header);
        curl_exec($ch);
        $header = curl_getinfo($ch, CURLINFO_HEADER_OUT); //官方文档描述是“发送请求的字符串”，其实就是请求的header。这个就是直接查看请求header，因为上面允许查看
        curl_close($ch);
//        return $header;
        return $GLOBALS['G_HEADER'];
    }

    // var_dump($GLOBALS['G_HEADER']); // 以数组形式打印响应头
