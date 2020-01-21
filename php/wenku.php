<?php
// namespace kufeisoft;

// $url = 'https://wenku.baidu.com/view/0571dbdf6f1aff00bed51e62.html?sxts=1539958717044';
// $jsList = []; // JS脚本列表
// $picList = []; // 图片列表

// if (!is_dir('./images')) {
  // mkdir('./images', 0777, TRUE);
//}

class Wenku {
  /**
   * JS列表
   *
   * @var jsList
   */  
  protected $jsList;
  
  /**
   * 图片列表
   *
   * @var picList
   */
  protected $picList;
  
  /**
   * 文档标题
   *
   * @var Title
   */
  protected $title;

  /**
   * 错误代码
   *
   * @var Error
   */
  private $errno;
  /** 
   * 错误信息
   * 
   * @var Errmsg
   */
  private $errmsg;

  /**
   * 相关配置
   *
   * @var Config;
   */
  protected $config;

  /**
   * 文库文章类型
   *
   * @var docType
   */
  protected $docType;

  public function __construct(array $config = []) {
    $this->config = $config;

  }

  public function getInfo(String $url){
    if(empty($url)) throw new \Exception('url不正确');
    $content = $this->_request($url);
    if(!$content){
      throw new \Exception('错误原因:' . $this->errmsg . ',错误代码:' . $this->errno);
    }
    $scripts = $this->_parserJs($content, '/WkInfo.htmlUrls\s=\s\'(.*?)\'/is');
    $docType = $this->_parserJs($content, '/\'docType\':\s\'(.*?)\',/is');
    $docInfo = $this->_parserJs($content, '/docId:\s\'(.*?)\'.*?title:\s\'(.*?)\'/is');
    $this->title = iconv('GB2312', 'UTF-8', $docInfo[2]);
    echo '文章标题:' . $this->title . "\n";
    echo '文档ID:' . $docInfo[1] . "\n";
    echo '文档类型为:' . $docType[1] . "\n";
    switch ($docType[1]) {
      case 'txt':
      case 'doc':
        $this->_doc($scripts[1]);
      break;
      case 'ppt':
        $this->_ppt($docInfo[1]);
      break;
      case 'pdf':
        $this->_pdf($scripts[1]);
        break;
      default:
        throw new \Exception('错误原因: 暂不支持该类型文档，文档类型:' . $docType[1]);
    }
  }

  private function _ppt($docid){
    $pptJson = $this->_request('https://wenku.baidu.com/browse/getbcsurl?doc_id=' . $docid . '&pn=1&rn=99999&type=ppt');
    $pptlist = json_decode($pptJson, true);
    if(!$pptlist){
      throw new \Exception('错误原因:获取PPT列表失败,请稍候再试!');
    }
    echo '当前文档共有' . count($pptlist) . '页' . PHP_EOL;
    // 创建一个对应的目录
    $path = './' . $this->title;
    if(!is_dir($path)){
      mkdir('./' . $this->title, 0777);
    }
    foreach($pptlist as $ppt){
      if(!file_exists($path . '/' . $this->title . $ppt['page'] . '.jpg')){
        $r = $this->_request($ppt['zoom']);
        if($r){
          file_put_contents($path . '/' . $this->title . $ppt['page'] . '.jpg', $r);
        }else{
          echo '第' . $ppt['page'] . '页获取失败，失败原因:' . $this->errmsg . PHP_EOL;
        }
      } else {
        echo '第' . $ppt['page'] . '页已存在，直接跳过' . PHP_EOL;
      }
    }
  }

  private function _doc($content) {
    $content = str_replace('\\x22', '', $content);
    $content = $this->_parserJs($content, '/pageLoadUrl:(.*?),/', true);
    foreach($content[1] as $con) {
      if(strpos($con, 'json') !== false){
        $this->jsList[] = str_replace(['}','\\\\\\'], '', $con);
      }
    }
    echo '当前文档共有' . count($this->jsList) . '页' . PHP_EOL;
    foreach($this->jsList as $js){
      $this->_page($js);
      usleep(200000); // 防止被ban,单位 微秒
    }
  }

  private function _pdf($content){
    $content = str_replace('\\x22', '', $content);
    $content = $this->_parserJs($content, '/pageLoadUrl:(.*?),/', true);
    foreach($content[1] as $con) {
      if(strpos($con, 'png') !== false){
        $this->picList[] = str_replace(['}','\\\\\\'], '', $con);
      }
    }
    echo '当前文档共有' . count($this->picList) . '页' . PHP_EOL;
    // 创建一个对应的目录
    $path = './' . $this->title;
    if(!is_dir($path)){
      mkdir('./' . $this->title, 0777);
    }
    foreach($this->picList as $num => $pic){
      if(!file_exists($path . '/' . $this->title . $num . '.png')){
        $r = $this->_request($pic);
        if($r){
          file_put_contents($path . '/' . $this->title . $num . '.png', $r);
        }else{
          echo '第' . $num . '页获取失败，失败原因:' . $this->errmsg  . PHP_EOL;
        }
      } else {
        echo '第' . $num . '页已存在，直接跳过' . PHP_EOL;
      }
    }
  }

  private function _page($url) {
    $content = $this->_request(str_replace('\\', '', $url));
    $result = preg_match('/\(\{(.*?)\}\)/is', $content, $contentarr);
    $content = json_decode('{' . $contentarr[1] . '}', true);
    if (false === $content) {
      throw new \Exception('错误原因: 获取失败,请稍后再试或者内容为空');
    }else{
      $body = $content['body'] ? $content['body'] : [];
      $text = '';
      if($body){
        foreach($body as $b){
          if ($b['t'] == 'word') {
            $text = $b['c'];
            if ($b['ps'] != NULL && isset($b['ps']['_enter']) ){
              $text .= PHP_EOL;
            }
            $path = './' . $this->title;
            if(!is_dir($path)){
              mkdir('./' . $this->title, 0777);
            }
            file_put_contents($path . '/' . $this->title . '.txt', $text, FILE_APPEND);
          }
        }
      }
    }
  }

  private function _parserJs(String $content, String $reg, Bool $all=false){
    if ($all) {
      $result = preg_match_all($reg, $content, $contentArr);
    } else {
      $result = preg_match($reg, $content, $contentArr);
    }
    return $contentArr;
  }

  private function _request($url, $params = [], $method = 'GET', $options = []) {
    $method = strtoupper($method);
    $protocol = substr($url, 0, 5);
    $query_string = is_array($params) ? http_build_query($params) : $params;
    $ch = curl_init();
    $defaults = [];
    if ('GET' == $method) {
      $geturl = $query_string ? $url . (stripos($url, "?") !== FALSE ? "&" : "?") . $query_string : $url;
      $defaults[CURLOPT_URL] = $geturl;
    } else {
      $defaults[CURLOPT_URL] = $url;
      if ($method == 'POST') {
        $defaults[CURLOPT_POST] = 1;
      } else {
        $defaults[CURLOPT_CUSTOMREQUEST] = $method;
      }
      $defaults[CURLOPT_POSTFIELDS] = $query_string;
    }
    $defaults[CURLOPT_HEADER] = FALSE;
    $defaults[CURLOPT_USERAGENT] = "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/45.0.2454.98 Safari/537.36";
    $defaults[CURLOPT_FOLLOWLOCATION] = TRUE;
    $defaults[CURLOPT_RETURNTRANSFER] = TRUE;
    $defaults[CURLOPT_CONNECTTIMEOUT] = 3;
    $defaults[CURLOPT_TIMEOUT] = 3;
    // disable 100-continue
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect:'));
    if ('https' == $protocol) {
      $defaults[CURLOPT_SSL_VERIFYPEER] = FALSE;
      $defaults[CURLOPT_SSL_VERIFYHOST] = FALSE;
    }
    curl_setopt_array($ch, (array) $options + $defaults);
    $ret = curl_exec($ch);
    $err = curl_error($ch);
    if (FALSE === $ret || !empty($err)){
      $errno = curl_errno($ch);
      $info = curl_getinfo($ch);
      curl_close($ch);
      $this->errno = $errno;
      $this->errmsg = $err;
      return false;
    }
    curl_close($ch);
    return $ret;
  }
}

if('cli' == php_sapi_name()){
  $wenkuUrl = $argv[1];
  if(!$wenkuUrl) exit('文库地址错误!' . PHP_EOL);
  $wenku = new Wenku();
  $wenku->getInfo($wenkuUrl);
}else{
  exit('请使用CLI方式运行该文件!' . PHP_EOL);
}



// $wenku->getInfo('https://wenku.baidu.com/view/0571dbdf6f1aff00bed51e62.html?sxts=1539958717044');
// $wenku->getInfo('https://wenku.baidu.com/view/ea64d65e18e8b8f67c1cfad6195f312b3069ebc9.html');
// $wenku->getInfo('https://wenku.baidu.com/view/d554141701d8ce2f0066f5335a8102d276a261a3.html');