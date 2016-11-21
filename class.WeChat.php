<?php
/**
 * 微信公众号类<br>
 * Class for WeChat Official Accounts
 * 
 * @author JoStudio，2016
 * 
 * @see https://github.com/jostudio189
 */

//错误代码(error codes)
define('ERROR_BAD_REQUEST', 400);
define('ERROR_UNAUTHORIZED', 401);
define('ERROR_FORBIDDEN', 403);
define('ERROR_NOT_FOUND', 404);
define('ERROR_NOT_ACCEPTABLE', 406);
define('ERROR_CONFLICT', 409);
define('ERROR_GONE', 410);
define('ERROR_PRECONDITION', 411);
define('ERROR_UNSUPPORTED', 415);
define('ERROR_INTERNAL_SERVER', 500);
define('ERROR_NOT_IMPLEMENTED', 501);
define('ERROR_SERVER_UNAVALIABLE', 503);
define('ERROR_UNKNOWN', -1);
define('ERROR_READ', 601);
define('ERROR_WRITE', 602);
define('ERROR_NOT_INITIALZED', 603);
define('ERROR_PARAMETER', 604);
define('ERROR_HTTP', 605);
define('ERROR_CONNECTION', 606);


define('LOG_LEVEL_NONE', 0);//不写日志
define('LOG_LEVEL_ERROR', 1);//写日志,仅出错信息
define('LOG_LEVEL_WARNING', 2);//写日志,仅出错信息、警告信息和访问记录
define('LOG_LEVEL_FULL', 3);//写日志,写详细运行记录


/**
 * 微信公众号类<br>
 * Class for WeChat Official Accounts
 * 
 * @author JoStudio，2016
 * 
 * @see https://github.com/jostudio189
 * @version 0.9
 */
class WeChat {
	
	/**
	 * WeChat类的版本号
	 * @var string
	 */
	public $version = "0.9"; //版本号
	
	/**
	 * TOKEN值
	 * @var string
	 */
	public $token = "";  	//TOKEN值
	
	/**
	 * 消息发送者(openId)
	 * @var string
	 */
	public $fromUser = "";  //当前消息的发送者(平台给的用户Id)
	
	/**
	 * 消息接收者(openId)
	 * @var string
	 */
	public $toUser = "";    //当前消息的接收者(平台给的用户Id)
	
	/**
	 * 当前消息的类型<br>
	 * 如：text, subscribe, click, image ...
	 * @var string
	 */
	public $messageType = "";
	
	/**
	 * 事件函数名称前缀<br>
	 * @var string
	 */
	private $functionPrefix = "";
	
	/**
	 * 错误代码
	 * @var integer
	 */
	public $errorCode = 0;  //错误代码
	
	/**
	 * 错误信息文本
	 * @var string
	 */
	public $errorMessage = "";  //错误信息文本
	
	/**
	 * 日志文件名
	 * @var string 默认值为"log.txt"
	 */
	public $logfile = "log.txt";  //日志文件名
	
	/**
	 * 写日志的程度, 取值如下：<br>
	 * LOG_LEVEL_NONE=0 不写日志,<br>
	 * LOG_LEVEL_ERROR=1 写日志,仅出错信息,<br>
	 * LOG_LEVEL_WARNING=2 写日志,仅出错信息、警告信息和访问记录,<br>
	 * LOG_LEVEL_FULL=3 写日志,写详细运行记录，包括：出错信息、警告信息、访问记录、消息内容、函数访问记录等<br>
	 * @var integer 默认值为LOG_LEVEL_NONE (=0)
	 */
	protected $logLevel = 0;
	
	/**
	 * 保存用户数据的文件目录
	 * @var string
	 */
	protected $saveDir = null; //保存用户数据的文件目录 (当使用文件保存时，需要设置该值)
	
	/**
	 * 当不是微信平台访问时，显示的页面内容
	 * @var string
	 */
	public $defaultPageContent = "<html><head><META content='text/html; charset=UTF-8'></head><body><center><br>WeChat</center></body></html>";
	
	/**
	 * 平台类型, 如果是微信，则为 "weixin"; 如果是易信，则为 "yixin"
	 * @var string
	 */
	public $platform = "weixin"; //平台类型, 如果是微信，则为 "weixin"; 如果是易信，则为 "yixin"
	
	/**
	 * 平台服务器. 微信为api.weixin.qq.com,易信为api.yixin.im
	 */
	protected $platformHost = 'api.weixin.qq.com';
	
	/**
	 * 平台提供的AppId值
	 * @var String
	 */
	protected $appId = '';
	
	/**
	 * 平台提供的AppSecret值
	 * @var String
	 */
	protected $appSecret = '';
	
	/**
	 * 平台下发的AccessToken值
	 * @var String
	 */
	public $accessToken = '';
	
	/**
	 * AccessToken值的失效时间
	 */
	public $accessTokenExpireTime = 0; //
	
	private $menus = array();         //菜单数组
	private $menuItems = array();     //菜单项数组
	private $current_menu_name = "";  //当前菜单名称
	private $current_menu_key = "";  //当前菜单的键值
	
	private $sendTextBuffer = null;  //sendText的累积区
	private $sendNewsBuffer = array(); //sendNews的累积区
	
	/**
	 * WeChat类的构造函数
	 * @param string $token Token值
	 * @return 类的实例
	 */
	function WeChat($token) {
		$this->token = $token;
		@date_default_timezone_set("Asia/ShangHai"); //设置时区为中国
	}
	
	/**
	 * 内部用:自编的json_encode(用于解决中文编码问题)
	 * @param mixed $var
	 * @return string|unknown json编码文本
	 */
	private function json_encode($var) {
		switch (gettype($var)) {
			case 'boolean':
				return $var ? 'true' : 'false'; // Lowercase necessary!
			case 'integer':
			case 'double':
				return $var;
			case 'resource':
			case 'string':
				return '"'. str_replace(array("\r", "\n", "<", ">", "&"),
				array('\r', '\n', '\x3c', '\x3e', '\x26'),
				addslashes($var)) .'"';
			case 'array':
				// Arrays in JSON can't be associative. If the array is empty or if it
				// has sequential whole number keys starting with 0, it's not associative
				// so we can go ahead and convert it as an array.
				if (empty ($var) || array_keys($var) === range(0, sizeof($var) - 1)) {
					$output = array();
					foreach ($var as $v) {
						$output[] = $this->json_encode($v);
					}
					return '[ '. implode(', ', $output) .' ]';
				}
				// Otherwise, fall through to convert the array as an object.
			case 'object':
				$output = array();
				foreach ($var as $k => $v) {
					$output[] = $this->json_encode(strval($k)) .': '. $this->json_encode($v);
				}
				return '{ '. implode(', ', $output) .' }';
			default:
				return 'null';
		}
	}

	/**
	 * 写日志<br>
	 * Write log file
	 * 
	 * @param string $thing  事件
	 * @param string $result 事件结果
	 * @return none
	 */
	public function log($thing, $result="") {
		//日志记录字段间以符号"|"分隔， 字段包括:时间 |ip|来访用户ID|事件|事件结果
		$time = date("Y-m-d H:i:s",time());
		$remoteIp = $_SERVER['REMOTE_ADDR'];
		$fromUser = $this->fromUser;
		$data = "$time|$remoteIp|$fromUser|$thing|$result";
		$this->logRawData($data);
	}
	
	/**
	 * 写日志(原始数据)<br>
	 * Write raw data to log file
	 * 
	 * @param string $data  数据
	 * @return none
	 */
	protected function logRawData($data) {
		if (!empty($this->saveDir)) {
			$filename = $this->logfile;
			$filename = $this->saveDir.$this->logfile;
			file_put_contents($filename,"\r\n".$data,FILE_APPEND);
			if ($data=="\r\r\r\r") {
				file_put_contents($filename,"");
			}
		}
	}
	
	/**
	 * 设置保存目录(用于存储临时数据的)
	 * @param string $dir
	 * @return boolean 正确返回true, 错误返回false
	 */
	public function setSaveDir($dir) {
		if (is_dir($dir)) {
			$lastChar = substr($dir, strlen($dir)-1);
			if ($lastChar=='\\' || $lastChar=='/') {
				$lastChar=DIRECTORY_SEPARATOR;
				$dir = substr($dir, 0, strlen($dir)-1).DIRECTORY_SEPARATOR;
			} else
				$dir .= DIRECTORY_SEPARATOR;
				if (is_dir($dir)) {
					$this->saveDir = $dir;
					return true;
				}
		}
		return false;
	}
	
	/**
	 * 设置写日志的程度
	 * @param string $log_level
	 * <pre>
	 * 取值如下：
	 * LOG_LEVEL_NONE 不写日志
	 * LOG_LEVEL_ERROR 写日志,仅出错信息
	 * LOG_LEVEL_WARNING 写日志,仅出错信息、警告信息和访问记录
	 * LOG_LEVEL_FULL 写日志,写详细运行记录，包括：出错信息、警告信息、访问记录、消息内容、函数访问记录等
	 * </pre>
	 * @return boolean 正确返回true, 错误返回false
	 */
	public function setLogLevel($log_level) {
		if (is_int($log_level) && ($log_level>=LOG_LEVEL_NONE) && ($log_level<=LOG_LEVEL_FULL) ) {
			$this->logLevel = $log_level;
			return true;
		} else
			return false;
		
	}
	
	
	/**
	 * 写入错误/警告信息, 错误/警告代码
	 * @param string $message  错误信息
	 * @param integer $code (可选)错误代码,默认值为-1. (>0为严重错误，其它为警告)
	 * @return boolean 返回false
	 */
	public function error($message,$code=-1) {
		$this->errorCode = $code;
		$this->errorMessage = $message;
		if ($this->logLevel>=LOG_LEVEL_ERROR) {
			if ($code>0)
				$this->log('ERROR', "$code: $message");
				else {
					if ($this->logLevel>=LOG_LEVEL_WARNING)
						$this->log('WARNING', "$code: $message");
				}
		}
		return false;
	}

	/**
	 * 计算签名值<br>
	 * Calculate signature<br>
	 * 
	 * @param string $timestamp 时间戳
	 * @param string $nonce 随机数
	 * @param string $token TOKEN值
	 * @return string 签名值
	 */
	public function calcSignature($timestamp, $nonce, $token) {
		$array = array(''.$token, ''.$timestamp, ''.$nonce); //将$token, $time, $nonce组成一个数组
		sort($array, SORT_STRING); //对数组进行排序, Version0.8修正：增加了SORT_STRING flag
		$text = implode($array);//将数组连接成一个字符串
		return sha1( $text ); //对字符串用 sha1() 算法生成签名值
	}
	
	
	/**
	 * 检查签名值是否正确<br>
	 * check whether signature is right
	 * 
	 * HTTP GET参数中有signature,timestamp,nonce, 通过token值和这三个参数检查签名值是否正确
	 * @return boolean 正确返回true,否则返回false
	 */
	private function checkSignature()	{
		if (!isset($_GET["signature"])) return false;
		if (!isset($_GET["timestamp"])) return false;
		if (!isset($_GET["nonce"])) return false;
	
		$signature = $_GET["signature"];
		$timestamp = $_GET["timestamp"];
		$nonce = $_GET["nonce"];
		$token = ''.$this->token;
		
		if ( $this->calcSignature($timestamp, $nonce, $token) == $signature )
			return true;
		else {
			if ($this->logLevel>LOG_LEVEL_WARNING) {
				$this->log("CHECK SIGNATURE FAIL", 'GET = '.$signature.', CALCULATE = '.$this->calcSignature($timestamp, $nonce, $token));
			}
			return false;
		}
	}
	
	/**
	 * 验证签名是否有效<br>
	 * validate signature<br>
	 * 
	 * 如果签名有效，则在响应结果中返回echostr值. 如果签名无效，则中断脚本运行
	 * @return none
	 */
	protected function valid() {
		$echoStr = $_GET["echostr"];
		//检查签名是否正确
		if($this->checkSignature()) {
			echo $echoStr;
			if ($this->logLevel>=LOG_LEVEL_WARNING) $this->log('PLATFORM VALID OK', 'REQUEST STRING='.$_SERVER['QUERY_STRING']);
			return true;
		} else {
			echo 'error signaturfe';
			if ($this->logLevel>=LOG_LEVEL_ERROR) {
				if (isset($_SERVER['QUERY_STRING']))
					$this->log('PLATFORM VALID FAIL','REQUEST STRING='.$_SERVER['QUERY_STRING']);
				else
					$this->log('PLATFORM VALID FAIL','REQUEST STRING=null');
			}
			return false;
		}
	}
	
	/**
	 * 接收微信消息并进行处理
	 */
	public function process() {
	
		if ($this->logLevel>=LOG_LEVEL_WARNING) {
			if (isset($_SERVER["QUERY_STRING"]) && (!empty($_SERVER["QUERY_STRING"])))
				$this->log('ACCESS',$_SERVER['PHP_SELF']. "?". $_SERVER["QUERY_STRING"]);
			else
				$this->log('ACCESS',$_SERVER['PHP_SELF']);
		}
	
		//如果是验证请求,则执行签名验证并退出
		if ( (isset($_GET["echostr"]))  && (!empty($_GET["echostr"])) ) {
			return $this->valid(); //验证签名是否有效
		}
	
		//取得POST数据
		if (isset($GLOBALS["HTTP_RAW_POST_DATA"])) { //如果有POST数据
			$postData = $GLOBALS["HTTP_RAW_POST_DATA"];
		} else {
			//如果没有POST数据, 则显示默认页面，返回
			echo $this->defaultPageContent;
			return $this->error("Request without post data", ERROR_BAD_REQUEST);
		}
	
		//验证签名不通过则退出
		if (!$this->checkSignature()) {
			echo "error signature";
			return $this->error("Error signature",ERROR_BAD_REQUEST);
		}
	
		//解析POST数据(XML格式)
		$object = simplexml_load_string($postData, 'SimpleXMLElement', LIBXML_NOCDATA);
		$this->messageType = trim("".$object->MsgType);    //取得消息类型
		$this->fromUser = "".$object->FromUserName; //记录消息发送方(不是发送者的微信号，而是一个加密后的OpenID)
		$this->toUser = "".$object->ToUserName;     //记录消息接收方(就是公共平台的OpenID)
	
		//在处理微信消息前，触发onStart()事件
		$this->onStart($this->fromUser);
	
		//记录运行状态
		if ($this->logLevel>=LOG_LEVEL_WARNING) {
			$this->log('RECEIVE MESSAGE');
			if ($this->logLevel>=3) $this->logRawData($postData);
		}
	
		//根据不同的消息类型，分别处理
		switch($this->messageType)
		{
			case "text":   //文本消息
				$this->onText(''.$object->Content);
				break;
			case "image":  //图片消息
				$this->onImage(''.$object->PicUrl, $object->MediaId);
				break;
			case "voice":  //音频消息
				$this->onVoice(''.$object->MediaId, ''.$object->Format);
				break;
			case "video":  //视频消息
				$this->onVideo(''.$object->MediaId, ''.$object->ThumbMediaId);
				break;
			case "shortvideo":  //小视频消息
				$this->onShortVideo(''.$object->MediaId, ''.$object->ThumbMediaId);
				break;
			case "location": //定位信息
				$this->onLocation(''.$object->Label, $object->Location_X, $object->Location_Y, $object->Scale);
				break;
			case "link":  //链接信息
				$this->onLink(''.$object->Url, ''.$object->Title, ''.$object->Description);
				break;
			case "music":  //音乐消息(限于易信平台, 微信平台没有这种消息)
				$this->onMusic(''.$object->url, ''.$object->name, ''.$object->desc);
				break;
			case "event":  //事件
				$this->messageType = strtolower($object->Event);
				switch ($object->Event)
				{
					case "subscribe":   //订阅事件
						if (isset($object->EventKey))
							$this->onSubscribe(''.$object->FromUserName, "".$object->EventKey);
						else
							$this->onSubscribe(''.$object->FromUserName);
							break;
					case "unsubscribe": //取消订阅事件
						$this->onUnsubscribe(''.$object->FromUserName);
						break;
					case "CLICK":      //菜单点击事件
						$this->onClick(''.$object->EventKey);
						break;
					case "VIEW":      //点击菜单跳转链接时的事件
						$this->onView(''.$object->EventKey);
						break;
					case "LOCATION":     //上报地理位置事件
						$this->onLocationEvent($object->Latitude, $object->Longitude, $object->Precision);
						break;
					case "YIXINscan": //易信用户扫描扫描带参数二维码事件
						$this->onScan(''.$object->EventKey, ''.$object->Ticket);
						break;
					case "scan": //微信用户扫描带参数二维码事件
						$this->onScan(''.$object->EventKey, ''.$object->Ticket);
						break;
					case "scancode_push": //微信用户扫描二维码推送事件
						$this->onScanCodePush(''.$object->EventKey, ''.$object->ScanCodeInfo->ScanResult);
						break;
					case "scancode_waitmsg": //微信用户扫描二维码
						$this->onScanCode(''.$object->EventKey, ''.$object->ScanCodeInfo->ScanResult);
						break;
					default :
						//Unknown Event
						$this->onUnknownMessageType (''.$this->messageType);
						if ($this->logLevel>=LOG_LEVEL_WARNING) $this->log('ERROR','Unknown Event'.$object->Event);
						break;
				}
				break;
			default:
				//收到未知消息类型
				$this->onUnknownMessageType (''.$this->messageType);
				if ($this->logLevel>=LOG_LEVEL_WARNING) $this->log('ERROR','Unknown MsgType'.$this->messageType);
				break;
					
		}
	
		//在退出前，触发onEnd()事件
		$this->onEnd($this->fromUser);
		
		
		//发送缓冲区数据，清空缓冲区
		$this->flush();
	}
	
	/**
	 * 取得函数名称:在函数名称前加上前缀
	 * Enter description here ...
	 * @param string $functionName
	 */
	private function getFunctionName($functionName) {
		if (empty($this->functionPrefix))
			return $functionName;
		else
			return $this->functionPrefix.$functionName;
	}
	
	/**
	 * 设置事件函数名称前缀
	 * @param string $prefix 函数名称前缀
	 */
	public function setFunctionPrefix($prefix) {
		$this->functionPrefix = trim(''.$prefix);
	}
	
	//----------------以下是 发送函数 ---------------------
	/**
	 * 形成 文本消息响应值
	 * @param string $toUser
	 * @param string $fromUser
	 * @param string $content
	 * @param string $flag
	 * @return string 消息的xml文本
	 */
	private function textResponse($toUser, $fromUser, $content, $flag=0)	{
		$xmlTemplate = '<xml>'.
				'<ToUserName><![CDATA[%s]]></ToUserName>'.
				'<FromUserName><![CDATA[%s]]></FromUserName>'.
				'<CreateTime>%s</CreateTime>'.
				'<MsgType><![CDATA[text]]></MsgType>'.
				'<Content><![CDATA[%s]]></Content>'.
				'<FuncFlag>%d</FuncFlag>'.
				'</xml>';
		$xmlText = sprintf($xmlTemplate, $toUser, $fromUser, time(), $content, $flag);
		return $xmlText;
	}
	
	/**
	 * 形成 图片消息的响应值
	 * @param string $toUser
	 * @param string $fromUser
	 * @param string $url 图片url
	 * @return string 消息的xml文本
	 */
	private function imageResponse($toUser, $fromUser, $mediaId) {
		$xmlTemplate = '<xml>'
				.'<ToUserName><![CDATA[%s]]></ToUserName>'
				.'<FromUserName><![CDATA[%s]]></FromUserName>'
				.'<CreateTime>%s</CreateTime>'
				.'<MsgType><![CDATA[image]]></MsgType>'
				.'<Image><MediaId><![CDATA[%s]]></MediaId></Image>'
				.'</xml>';
		$xmlText = sprintf($xmlTemplate, $toUser, $fromUser, time(), $mediaId);
		return $xmlText;
	}
	
	/**
	 * 形成 语音消息的响应值
	 * @param string $toUser
	 * @param string $fromUser
	 * @param string $mediaId 
	 * @return string 消息的xml文本
	 */
	private function voiceResponse($toUser, $fromUser, $mediaId) {
		$xmlTemplate = '<xml>'
				.'<ToUserName><![CDATA[%s]]></ToUserName>'
				.'<FromUserName><![CDATA[%s]]></FromUserName>'
				.'<CreateTime>%s</CreateTime>'
				.'<MsgType><![CDATA[voice]]></MsgType>'
				.'<Voice><MediaId><![CDATA[%s]]></MediaId></Voice>'
				.'</xml>';
		$xmlText = sprintf($xmlTemplate, $toUser, $fromUser, time(), $mediaId);
		return $xmlText;
	}
	
	/**
	 * 形成 视频消息的响应值
	 * @param string $toUser
	 * @param string $fromUser
	 * @param string $mediaId 通过素材管理中的接口上传多媒体文件，得到的id
	 * @param string $title  视频消息的标题
	 * @param string $description 视频消息的描述
	 * @return string 消息的xml文本
	 */
	private function videoResponse($toUser, $fromUser, $mediaId, $title, $description ) {
		$xmlTemplate = '<xml>'
				.'<ToUserName><![CDATA[%s]]></ToUserName>'
				.'<FromUserName><![CDATA[%s]]></FromUserName>'
				.'<CreateTime>%s</CreateTime>'
				.'<MsgType><![CDATA[video]]></MsgType>'
				.'<Video><MediaId><![CDATA[%s]]></MediaId>'
				.'<Title><![CDATA[%s]]></Title>'
				.'<Description><![CDATA[%s]]></Description>'
				.'</Video>'
				.'</xml>';
		$xmlText = sprintf($xmlTemplate, $toUser, $fromUser, time(), $mediaId,$title,$description);
		return $xmlText;
	}
	
	/**
	 * 形成 音乐消息响应值
	 * @param string $toUser
	 * @param string $fromUser
	 * @param string $title
	 * @param string $description
	 * @param string $musicUrl
	 * @param string $hqMusicUrl
	 * @param string $thumbMediaId 缩略图的媒体id
	 * @return string 消息的xml文本
	 */
	private function musicResponse($toUser, $fromUser, $title, $description, $musicUrl, $hqMusicUrl, $thumbMediaId) {
		$xmlTemplate = '<xml>'
				.'<ToUserName><![CDATA[%s]]></ToUserName>'
				.'<FromUserName><![CDATA[%s]]></FromUserName>'
				.'<CreateTime>%s</CreateTime>'
				.'<MsgType><![CDATA[music]]></MsgType>'
				.'<Music> <Title><![CDATA[%s]]></Title>'
				.'<Description><![CDATA[%s]]></Description>'
				.'<MusicUrl><![CDATA[%s]]></MusicUrl>'
				.'<HQMusicUrl><![CDATA[%s]]></HQMusicUrl>'
				.'<ThumbMediaId><![CDATA[%s]]></ThumbMediaId>'
				.'</Music>'
				.'</xml>';
		if (empty($hq_url)) $hq_url=$url;
		$xmlText = sprintf($xmlTemplate, $toUser, $fromUser, time(),
				$title, $description, $musicUrl, $hqMusicUrl, $thumbMediaId);
		return $xmlText;
	}
	
	/**
	 * 形成 图文消息响应值
	 * @param string $toUser
	 * @param string $fromUser
	 * @param array $articles 一个array，每个元素保存一条图文信息；每个元素也是一个array, 有Title,Description,PicUrl,Url四个键值
	 * @return string 消息的xml文本
	 */
	private function newsResponse($toUser, $fromUser, $articles) {
		$xmlTemplate = '<xml>'
				.'<ToUserName><![CDATA[%s]]></ToUserName>'
				.'<FromUserName><![CDATA[%s]]></FromUserName>'
				.'<CreateTime>%s</CreateTime>'
				.'<MsgType><![CDATA[news]]></MsgType>';
		$xmlText = sprintf($xmlTemplate, $toUser, $fromUser, time());
		$xmlText .= '<ArticleCount>'. count($articles) .'</ArticleCount>';
		$xmlText .= '<Articles>';
		foreach($articles as  $article) {
			$xmlText .= '<item>';
			$xmlText .= '<Title><![CDATA[' . $article['Title'] . ']]></Title>';
			$xmlText .= '<Description><![CDATA[' . $article['Description'] . ']]></Description>';
			$xmlText .= '<PicUrl><![CDATA[' . $article['PicUrl'] . ']]></PicUrl>';
			$xmlText .= '<Url><![CDATA[' . $article['Url'] . ']]></Url>';
			$xmlText .= '</item>';
		}
		$xmlText .= '</Articles> </xml>';
		return $xmlText;
	}
	
	
	/**
	 * 形成 音频消息响应值
	 * @param string $toUser
	 * @param string $fromUser
	 * @param string $url
	 * @param string $name
	 * @param string $mimeType
	 * @return string 消息的xml文本
	 */
	private function audioResponse($toUser, $fromUser, $url, $name, $mimeType='audio/aac') {
		$xmlTemplate = '<xml>'
				.'<ToUserName><![CDATA[%s]]></ToUserName>'
				.'<FromUserName><![CDATA[%s]]></FromUserName>'
				.'<CreateTime>%s</CreateTime>'
				.'<MsgType><![CDATA[audio]]></MsgType>'
				.'<url><![CDATA[%s]]></url>'
				.'<name><![CDATA[%s]]></name>'
				.'<mimeType><![CDATA[%s]]></mimeType>'
				.'</xml>';
		$xmlText = sprintf($xmlTemplate, $toUser, $fromUser, time(), $url, $name, $mimeType);
		return $xmlText;
	}
	
	
	//--------------------  以下是 发送消息 --------------------------
	
	/**
	 * 清空图文信息
	 */
	public function clearNews() {
		$this->articles=array();
	}
	
	/**
	 * 添加一条图文信息
	 * @param string $title  标题
	 * @param string $description 内容
	 * @param string $url  网页链接URL
	 * @param string $picUrl 图片的URL
	 * @return none
	 */
	public function addNews($title, $description, $url, $picUrl) {
		$article = array('Title' => $title,
				'Description' => $description,
				'PicUrl' => $picUrl,
				'Url'=>$url);
		$this->articles[] = $article;
	}
	
	/**
	 * 发送图文信息<br>
	 * 用法：首先用addNews()函数一条一条地添加图文信息，添加完成后用本函数发送
	 * @param mixed $data (可缺省)图文信息数据,缺省为null(此时发送通过addNew()方法添加的图文信息)
	 * @return none
	 */
	public function sendNews($data=null) {
		if($this->logLevel>=LOG_LEVEL_FULL)
			$this->log('SEND NEWS');
		if ($data==null) {
			echo $this->newsResponse($this->fromUser, $this->toUser, $this->articles);
		} else if (gettype($data)=="array") {
			if  (!(empty($data['title']))) {
				$this->articles = array();
				$this->addNews($data['title'], $data['description'], $data['url'], $data['picUrl']);
				$this->sendNews();
			}
		} else if (gettype($data)=="string") {
			$this->articles = array();
			$this->addNews('', $data, '', '');
			$this->sendNews();
		}
	}
	
	/**
	 * 发送文本内容
	 * @param string $content 文本内容
	 * @return none
	 */
	public function sendText($content) {
		if($this->logLevel>=LOG_LEVEL_FULL)
			$this->log('SEND TEXT', str_replace("\r\n", '\r\n', $content));
		if ($this->sendTextBuffer==null)
			$this->sendTextBuffer=$content;
		else
			$this->sendTextBuffer .= ("\r\n". $content);
	}
	
	/**
	 * 发送缓冲区数据，清空缓冲区
	 */
	private function flush() {
		if ($this->sendTextBuffer!=null) {
			$xml = $this->textResponse($this->fromUser, $this->toUser, $this->sendTextBuffer);
			if($this->logLevel>=LOG_LEVEL_FULL) {
				$this->log('SEND MESSAGE');
				if($this->logLevel>=3) $this->logRawData($xml);
			}
			echo $xml;
			$this->sendTextBuffer = null;
		}
	}
	
	/**
	 * 发送图片信息 (以图文信息方式发送)
	 * @param string $url  图片URL
	 * @return none
	 */
	public function sendImageByUrl($url) {
		$url = $this->validateUrl($url);
		$this->addNews("", "", $url, $url);
		$this->sendNews();
	}
	
	/**
	 * 发送图片信息 (使用MedialId)
	 * @param string $mediaId  通过素材管理中的接口上传多媒体文件，得到的id。
	 * @return none
	 */
	public function sendImage($mediaId) {
		echo $this->imageResponse($this->fromUser, $this->toUser, $mediaId);
	}
	
	/**
	 * 发送语音消息(使用MedialId)
	 * @param string $mediaId  通过素材管理中的接口上传多媒体文件，得到的id。
	 * @return none
	 */
	public function sendVoice($mediaId) {
		echo $this->voiceResponse($this->fromUser, $this->toUser, $mediaId);
	}
	
	/**
	 * 发送音乐信息
	 * @param string $musicUrl 音乐URL
	 * @param string $title 标题
	 * @param string $description 描述
	 * @param string $hqMusicUrl 高质量音乐的URL
	 * @param string $thumbMediaId 缩略图的媒体id, 通过素材管理中的接口上传多媒体文件，得到的id
	 * @return none
	 */
	public function sendMusic($musicUrl, $title='', $description='', $hqMusicUrl='', $thumbMediaId='') {
		echo $this->musicResponse($this->fromUser, $this->toUser, $title, $description,
			 $musicUrl, $hqMusicUrl, $thumbMediaId);
	}
	
	
	/**
	 * 发送视频信息
	 * @param string $mediaId 通过素材管理中的接口上传多媒体文件，得到的id
	 * @param string $title  视频消息的标题
	 * @param string $description 视频消息的描述
	 * @return none
	 */
	public function sendVideo($mediaId, $title, $description ) {
		echo $this->videoResponse($this->fromUser,  $this->toUser, $mediaId, $title, $description);
	}
	
	/**
	 * 发送数据
	 * @param mixed $data 要发送的数据
	 * @return none
	 */
	public function send($data) {
		switch (gettype($data)) {
			case "integer":
			case "double":
			case "boolean":
			case "object":
			case "string":
				return $this->sendText(strval($data));
			case "array":
				if (!empty($data['MsgType'])) {
					switch($data['MsgType']) {
						case "text":
							$this->sendText($data['Content']);
							break;
						case "image":
							$d = array('title'=>' ','description'=>'点击查看全图','url'=>$data['url'],'picUrl'=>$data['picUrl']);
							$this->sendNews($d);
							break;
						case "audio":
							break;
						case "video":
							break;
						case "news":
							break;
					}
				}
		}
	}
	
	//-------------- 以下是事件函数 --------------
	
	/**
	 * 事件：当启动时（在处理微信消息之前）
	 * @param string $userId 用户OpenID号
	 */
	protected function onStart($userId){	
		if ($this->logLevel>=LOG_LEVEL_FULL) $this->log('EVENT FUNCTION','Call Function onStart()');
		if (function_exists("onStart"))
			onStart($this, $userId);
	}
	
	/**
	 * 事件：当退出时（在结束处理前）
	 * @param string $userId 用户OpenID号
	 */
	protected function onEnd($userId){
		if ($this->logLevel>=LOG_LEVEL_FULL) $this->log('EVENT FUNCTION','Call Function onEnd()');
		if (function_exists("onEnd"))
			onEnd($this, $userId);
	}
	
	/**
	 * 事件：当收到未知消息类型时
	 * @param string $msgType 消息类型
	 */
	protected function onUnknownMessageType($msgType){
		$functionName = $this->getFunctionName('onUnknownMessageType');
		if ($this->logLevel>=LOG_LEVEL_ERROR) {
			$this->log('UnknownMessageType');
			if ($this->logLevel>=LOG_LEVEL_FULL) $this->log('EVENT FUNCTION','Call Function '.$functionName.'()');
		}
		if (is_callable($functionName))
			call_user_func($functionName, $this, $msgType);
	}
	
	/**
	 * 事件：当用户关注时
	 * @param string $userId 用户OpenID号
	 * @param string $eventKey 事件KEY值，当用户扫描带场景值二维码订阅时,此参数形如：qrscene_123123，qrscene_为前缀，后面为二维码的参数值
	 */
	protected function onSubscribe($userId, $eventKey=null){
		$functionName = $this->getFunctionName('onSubscribe');
		if ($this->logLevel>=LOG_LEVEL_ERROR) {
			$this->log('SUBSCRIBE');
			if ($this->logLevel>=LOG_LEVEL_FULL) $this->log('EVENT FUNCTION','Call Function '.$functionName.'()');
		}
		if (is_callable($functionName))
			call_user_func($functionName, $this, $userId, $eventKey);
	}
	
	/**
	 * 事件：当用户取消关注时
	 * @param string $userId 用户OpenID号
	 */
	protected function onUnsubscribe($userId) {
		$functionName = $this->getFunctionName('onUnsubscribe');
		if ($this->logLevel>=1) {
			$this->log('UNSUBSCRIBE');
			if ($this->logLevel>=LOG_LEVEL_FULL) $this->log('EVENT FUNCTION','Call Function '.$functionName.'()');
		}
		if (is_callable($functionName))
			call_user_func($functionName, $this, $userId);
	}
	
	/**
	 * 事件：当用户点击菜单
	 * @param string $menuKey 菜单的key值
	 */
	protected function onClick($menuKey) {
		$functionName = $this->getFunctionName('onClick');
		if ($this->logLevel>=LOG_LEVEL_WARNING) $this->log('CLICK');
		if ($this->logLevel>=LOG_LEVEL_FULL) $this->log('EVENT FUNCTION','Call Function '.$functionName.'()');
		if (is_callable($functionName))
			call_user_func($functionName, $this, $menuKey);
	}
	
	/**
	 * 事件：点击菜单跳转链接时的事件推送
	 * @param string $eventKey 事件KEY值，设置的跳转URL
	 */
	protected function onView($eventKey) {
		$functionName = $this->getFunctionName('onView');
		if ($this->logLevel>=LOG_LEVEL_WARNING) $this->log('VIEW');
		if ($this->logLevel>=LOG_LEVEL_FULL) $this->log('EVENT FUNCTION','Call Function '.$functionName.'()');
		if (is_callable($functionName))
			call_user_func($functionName, $this, $eventKey);
	}
	
	/**
	 * 事件：当用户扫描带参数二维码
	 * @param string $scene_value 参数值，是一个32位无符号整数，即创建二维码时的二维码scene_id
	 * @param string $ticket 二维码的ticket，可用来换取二维码图片
	 */
	protected function onScan($scene_value, $ticket) {
		$functionName = $this->getFunctionName('onScan');
		if ($this->logLevel>=LOG_LEVEL_WARNING) $this->log('SCAN');
		if ($this->logLevel>=LOG_LEVEL_FULL) $this->log('EVENT FUNCTION','Call Function '.$functionName.'()');
		if (is_callable($functionName))
			call_user_func($functionName, $this,  $scene_value, $ticket);
	}
	
	/**
	 * 事件：当用户扫描二维码(推送事件)
	 * @param string $eventKey 事件Key值
	 * @param string $scanResult 扫描结果值
	 */
	protected function onScanCodePush($eventKey, $scanResult) {
		$functionName = $this->getFunctionName('onScanCodePush');
		if ($this->logLevel>=LOG_LEVEL_WARNING) $this->log('SCAN CODE');
		if ($this->logLevel>=LOG_LEVEL_FULL) $this->log('EVENT FUNCTION','Call Function '.$functionName.'()');
		if (is_callable($functionName))
			call_user_func($functionName, $this,  $eventKey, $scanResult);
	}
	
	/**
	 * 事件：当用户扫描二维码
	 * @param string $eventKey 事件Key值
	 * @param string $scanResult 扫描结果值
	 */
	protected function onScanCode($eventKey, $scanResult) {
		$functionName = $this->getFunctionName('onScanCode');
		if ($this->logLevel>=LOG_LEVEL_WARNING) $this->log('SCAN CODE');
		if ($this->logLevel>=LOG_LEVEL_FULL) $this->log('EVENT FUNCTION','Call Function '.$functionName.'()');
		if (is_callable($functionName))
			call_user_func($functionName, $this,  $eventKey, $scanResult);
	}
	
	/**
	 * 事件：当用户发来文字
	 * @param string $content 用户发来的文字
	 */
	protected function onText($content) {
		$functionName = $this->getFunctionName('onText');
		if ($this->logLevel>=LOG_LEVEL_WARNING) $this->log('RECEIVE TEXT');
		if ($this->logLevel>=LOG_LEVEL_FULL) $this->log('EVENT FUNCTION','Call Function '.$functionName.'()');
		if (is_callable($functionName))
			call_user_func($functionName, $this, $content);
	}
	
	/**
	 * 事件：当用户发来图片
	 * @param string $url 图片的URL
	 * @param string $mediaId 图片消息媒体id，可以调用多媒体文件下载接口拉取数据。
	 */
	protected function onImage($url, $mediaId) {
		$functionName = $this->getFunctionName('onImage');
		if ($this->logLevel>=LOG_LEVEL_WARNING) $this->log('RECEIVE IMAGE');
		if ($this->logLevel>=LOG_LEVEL_FULL) $this->log('EVENT FUNCTION','Call Function '.$functionName.'()');
		if (is_callable($functionName))
			call_user_func($functionName, $this,  $url, $mediaId);
	}
	
	/**
	 * 事件：当用户发来语音
	 * @param string $mediaId 语音消息媒体id，可以调用多媒体文件下载接口拉取数据
	 * @param string $format 语音格式，如amr，speex等
	 */
	protected function onVoice($mediaId, $format) {
		$functionName = $this->getFunctionName('onVoice');
		if ($this->logLevel>=LOG_LEVEL_WARNING) $this->log('RECEIVE VOICE');
		if ($this->logLevel>=LOG_LEVEL_FULL) $this->log('EVENT FUNCTION','Call Function '.$functionName.'()');
		if (is_callable($functionName))
			call_user_func($functionName, $this, $mediaId, $format);
	}
	
	
	/**
	 * 事件：当用户发来视频
	 * @param string $mediaId 语音消息媒体id，可以调用多媒体文件下载接口拉取数据
	 * @param string $thumbMediaId 视频消息缩略图的媒体id，可以调用多媒体文件下载接口拉取数据。
	 */
	protected function onVideo($mediaId,$thumbMediaId) {
		$functionName = $this->getFunctionName('onVideo');
		if ($this->logLevel>=LOG_LEVEL_WARNING) $this->log('RECEIVE VIDEO');
		if ($this->logLevel>=LOG_LEVEL_FULL) $this->log('EVENT FUNCTION','Call Function '.$functionName.'()');
		if (is_callable($functionName))
			call_user_func($functionName, $this,  $mediaId, $thumbMediaId);
	}
	
	/**
	 * 事件：当用户发来小视频
	 * @param string $mediaId 视频消息媒体id，可以调用多媒体文件下载接口拉取数据
	 * @param string $thumbMediaId 视频消息缩略图的媒体id，可以调用多媒体文件下载接口拉取数据。
	 */
	protected function onShortVideo($mediaId, $thumbMediaId) {
		$functionName = $this->getFunctionName('onShortVideo');
		if ($this->logLevel>=LOG_LEVEL_WARNING) $this->log('RECEIVE SHORT VIDEO');
		if ($this->logLevel>=LOG_LEVEL_FULL) $this->log('EVENT FUNCTION','Call Function '.$functionName.'()');
		if (is_callable($functionName))
			call_user_func($functionName, $this, $mediaId, $thumbMediaId);
	}
	
	/**
	 * 事件：当用户发来音频
	 * @param string $url 音频的URL
	 * @param string $name 名称
	 * @param string $mimeType 音频格式
	 */
	protected function onAudio($url,$name,$mimeType) {
		$functionName = $this->getFunctionName('onAudio');
		if ($this->logLevel>=LOG_LEVEL_WARNING) $this->log('RECEIVE AUDIO');
		if ($this->logLevel>=LOG_LEVEL_FULL) $this->log('EVENT FUNCTION','Call Function '.$functionName.'()');
		if (is_callable($functionName))
			call_user_func($functionName, $this, $url, $name, $mimeType);
	}
	
	
	
	
	/**
	 * 事件：当用户发来音乐
	 * @param string $url 音乐的URL
	 * @param string $name 名称
	 * @param string $mimeType 描述
	 */
	protected function onMusic($url,$name,$description) {
		$functionName = $this->getFunctionName('onMusic');
		if ($this->logLevel>=LOG_LEVEL_WARNING) $this->log('RECEIVE MUSIC');
		if ($this->logLevel>=LOG_LEVEL_FULL) $this->log('EVENT FUNCTION','Call Function '.$functionName.'()');
		if (is_callable($functionName))
			call_user_func($functionName, $this, $url, $name, $description);
	}
	
	/**
	 * 事件：当用户发来地理位置
	 * @param string $label 地理位置描述文字
	 * @param string $location_x 地理位置纬度
	 * @param string $location_y 地理位置经度
	 * @param string $scale 缩放比例
	 */
	protected function onLocation($label, $location_x, $location_y, $scale) {
		$functionName = $this->getFunctionName('onLocation');
		if ($this->logLevel>=LOG_LEVEL_WARNING) $this->log('RECEIVE LOCATION');
		if ($this->logLevel>=LOG_LEVEL_FULL) $this->log('EVENT FUNCTION','Call Function '.$functionName.'()');
		if (is_callable($functionName))
			call_user_func($functionName, $this, $label, $location_x, $location_y, $scale);
	}
	
	/**
	 * 事件：当用户发来链接
	 * @param string $url 链接的URL
	 * @param string $title 标题
	 * @param string $description 描述
	 */
	protected function onLink($url, $title, $description) {
		$functionName = $this->getFunctionName('onLink');
		if ($this->logLevel>=LOG_LEVEL_WARNING) $this->log('RECEIVE LINK');
		if ($this->logLevel>=LOG_LEVEL_FULL) $this->log('EVENT FUNCTION','Call Function '.$functionName.'()');
		if (is_callable($functionName))
			call_user_func($functionName, $this, $url, $title, $description);
	}
	
	/**
	 * 事件：用户同意上报地理位置后，每次进入公众号会话时，都会在进入时上报地理位置，或在进入会话后每5秒上报一次地理位置
	 * @param string $latitude 地理位置纬度
	 * @param string $longitude 地理位置经度
	 * @param string $precision 地理位置精度
	 */
	protected function onLocationEvent($latitude, $longitude, $precision) {
		$functionName = $this->getFunctionName('onLocationEvent');
		if ($this->logLevel>=LOG_LEVEL_WARNING) $this->log('RECEIVE LOCATION');
		if ($this->logLevel>=LOG_LEVEL_FULL) $this->log('EVENT FUNCTION','Call Function '.$functionName.'()');
		if (is_callable($functionName))
			call_user_func($functionName, $this, $latitude, $longitude, $precision);
	}
	
	//-------------------以下是与 平台、AccessToken 相关的函数 ---------------------
	/**
	 * 设置平台类型
	 * @param string $platform 平台类型, 如果是微信，则为 "WeiXin"; 如果是易信，则为 "YiXin"
	 * @return boolean 成功返回true，否则返回false
	 */
	public function setPlatform($platform) {
		$platform = trim(strtolower($platform));
		if ($platform==="wechat") $platform = "weixin";
		if ($platform==="wx") $platform = "weixin";
		if ($platform==="yx") $platform = "yixin";
	
		if ($platform==='weixin')
			$this->platformHost = "api.weixin.qq.com";  ///平台服务器. 微信为api.weixin.qq.com
		else if ($platform==='yixin')
			$this->platformHost = "api.yixin.im";  ///平台服务器. 易信为api.yixin.im
		else
			return $this->error('设置平台类型错误，$platform值只能是 "WeiXin" 或  "YiXin"', ERROR_NOT_ACCEPTABLE);
	
		return true;
	}
	
	/**
	 * 设置AppId 和  AppSecret
	 * @param string $appId 平台提供的appid值
	 * @param string $appSecret 平台提供的appsecret值
	 * @return boolean 成功返回true，否则返回false
	 */
	public function setAppId($appId, $appSecret) {
		if (empty($this->platformHost))
			$this->setPlatform("WeiXin");
		$this->appId = $appId;
		$this->appSecret = $appSecret;
		return true;
	}
	
	/**
	 * 向平台取AccessToken值
	 * @return boolean 成功返回true，否则返回false
	 */
	public function getAccessToken(){
		$this->accessToken = '';
		$this->accessTokenExpireTime = 0;
	
		if (empty($this->appId)) return $this->error('参数错误: AppId为空,获取AccessToken值失败',ERROR_PARAMETER);
		if (empty($this->appSecret)) return $this->error('参数错误: AppSecret为空,获取AccessToken值失败',ERROR_PARAMETER);
		if (empty($this->platformHost)) 
			$this->setPlatform("WeiXin");
	
		//获取AccessToken的URL
		$TOKEN_URL="https://".$this->platformHost."/cgi-bin/token?grant_type=client_credential&appid=".$this->appId."&secret=".$this->appSecret;
		//以GET方式访问该URL，取得响应结果
		$data=$this->getUrl($TOKEN_URL);
	
		//如果响应结果是json格式的数据
		if ($this->isJson($data)) {
			$result=json_decode($data,true); //解析json数据
				
			//如果数据中有access_token，则表示成功取得access_token
			if (isset($result['access_token'])) {
				$this->accessToken =$result['access_token'];
				if (isset($result['expires_in'])) { //数据中的expires_in是access_token的失效时间长度(秒)
					$expires_in = $result['expires_in'];
					//echo 'current time = '. time().'<hr>';
					//echo 'expires_in = '. $expires_in.'<hr>';
					//echo 'expires_in = '. $expires_in.'<hr>';
					$this->accessTokenExpireTime = time() + $expires_in;
				} else
					$this->accessTokenExpireTime = time();
				$this->setAccessTokenCache(); //将数据写入缓存
				return true;
			} else {
				//如果数据中没有access_token，则表示出错，则记录错误码和错误信息．
				if (isset($result['errcode'])) $this->errorCode = $result['errcode'];
				if (isset($result['errmsg'])) $this->errorMessage = "获取AccessToken时出错，".$result['errmsg'];
				return false;
			}
		} else
			return $this->error("获取AccessToken时出错",ERROR_HTTP);
	}
	
	/**
	* 将AccessToken写入缓存
	* @return bool 失败返回false
	*/
	private function setAccessTokenCache(){
		if (!empty($this->saveDir)) {
			//缓存文件名
			$filename = $this->saveDir.'appid_'.$this->appId;
			//数据
			$array = array(
					'AccessToken' => $this->accessToken,
					'AccessTokenExpireTime'=> $this->accessTokenExpireTime,
			);
			//数据写入文件
			return file_put_contents($filename, json_encode($array));
		} else
			return false;
	}
	
	/**
	 * 读取缓存中的AccessToken
	 */
	private function getAccessTokenCache(){
		if (!empty($this->saveDir)) {
			$filename = $this->saveDir.'appid_'.$this->appId; //缓存文件名
			if (is_file($filename)) {
				$accessTokenCache = file_get_contents($filename);
				$array = json_decode($accessTokenCache, true);
				if ($array) {
					if (($array!=null) && isset($array['AccessToken'])) {
						$this->accessToken = $array['AccessToken'];
						$this->accessTokenExpireTime = $array['AccessTokenExpireTime'];
						if (empty($this->accessTokenExpireTime) || $this->accessTokenExpireTime < time()) {
							$this->accessToken = '';
							$this->accessTokenExpireTime = 0;
							return false;
						} else
							return true;
					}
				}
			}
		}
		return false;
	}
	
	/**
	 * 当前AccessToken是否有效
	 */
	private function isAccessTokenValid() {
		if (empty($this->accessToken)) $this->getAccessTokenCache(); //如果accessToken为空，则先读取缓存
		if (empty($this->accessToken)) $this->getAccessToken(); //如果accessToken仍为空，则向平台取
		if (empty($this->accessToken)) return false;
		return true;
	}
	
	//-------------------以下是 素材管理接口 ---------------------
	
	/**
	 * 上传临时素材(素材就是多媒体文件，如：图片,视频,语音)<br>
	 * 图片（image）: 1M，支持JPG格式<br>
	 * 语音（voice）：2M，播放长度不超过60s，支持AMR\MP3格式<br>
	 * 视频（video）：10MB，支持MP4格式<br>
	 * 缩略图（thumb）：64KB，支持JPG格式<br>
	 * 
	 * @param string  $filename 文件名
	 * @param boolean $isThumb (可缺省)是否缩略图，默认为false
	 * 
	 * @return array 成功返回media_id, 失败返回false<br>
	 * 临时素材的媒体文件在后台保存时间为3天，3天后文件自动被平台删除, media_id失效.
	 */
	public function uploadTempMedia($filename, $isThumb = false) {
		//验证AccessToken是否有效
		if (!$this->isAccessTokenValid()) return false;

		//获得真实文件名
		$real_filename = $this->getPhysicalPath($filename);
		if (empty($real_filename)) 
			return $this->error('找不到要上传的文件 '.$filename, ERROR_PARAMETER);
		
		//判断媒体类型 ，分别有图片（image）、语音（voice）、视频（video）和缩略图（thumb）
		$type = mime_content_type($real_filename);
		if ( strpos($type, '/') >= 0)
			$type = strtolower( substr($type, 0, strpos($type, '/')) );
		if ($type == 'audio') $type = 'voice';
		if ($isThumb && $type=='image')
			$type = 'thumb';
		if (!($type=='image' || $type=='audio' || $type=='video' || $type=='thumb'))
			return $this->error("上传文件 $filename 不是有效的媒体文件格式", ERROR_PARAMETER);
		
		//上传临时素材时的URL
		$url = "https://{$this->platformHost}/cgi-bin/media/upload?type=$type&access_token={$this->accessToken}";
		
		//向URL POST 文件
		$json = $this->postFile($url, $filename);
		
		return $this->processJsonResponse($json, 'media_id');
	}
	
	/**
	 * 上传永久素材(素材就是多媒体文件，如：图片,视频,语音)<br>
	 * 图片（image）: 1M，支持JPG格式<br>
	 * 语音（voice）：2M，播放长度不超过60s，支持AMR\MP3格式<br>
	 * 视频（video）：10MB，支持MP4格式<br>
	 * 缩略图（thumb）：64KB，支持JPG格式<br>
	 *
	 * @param string $filename 文件名或URL
	 * @param boolean $isThumb (可缺省)是否缩略图，默认为false
	 * @param array $title (可缺省)video媒体的标题，非video(mp4)文件不需要填写此字段<br>
	 * @param array $introduction (可缺省)video媒体的简介，非video(mp4)文件不需要填写此字段<br>
	 *
	 * @return array 成功返回一个数组（其中有media_id, url), 失败返回false
	 */
	public function uploadMaterial($filename, $isThumb = false, $title = null, $introduction = null) {
		//验证AccessToken是否有效
		if (!$this->isAccessTokenValid()) return false;
	
		//获得真实文件名
		$real_filename = $this->getPhysicalPath($filename);
		if ( empty($real_filename) )
			return $this->error('找不到要上传的文件 '.$filename, ERROR_PARAMETER);
	
		//判断媒体类型 ，分别有图片（image）、语音（voice）、视频（video）和缩略图（thumb）
		$type = mime_content_type($real_filename);
		if ( strpos($type, '/') >= 0 )
			$type = strtolower( substr($type, 0, strpos($type, '/')) );
		if ($type == 'audio') $type = 'voice';
		if ($type == 'image' && $isThumb)
			$type = 'thumb';
		if ( !($type=='image' || $type=='audio' || $type=='video'|| $type=='thumb') )
			return $this->error("上传文件 $filename 不是有效的媒体文件格式", ERROR_PARAMETER);
	
		//上传永久素材时的URL
		$url = "https://{$this->platformHost}/cgi-bin/material/add_material?type=$type&access_token={$this->accessToken}";
		
		//上传永久视频素材要增加一个description描述
		$desc = null;
		if ($type == 'video') {
			$desc['description'] = array('title'=>$title, 'introduction'=>$introduction);
		}

		//向URL POST 文件
		$json = $this->postFile($url, $filename, $desc);
		
		return $this->processJsonResponse($json, 'array');
	}
	
	
	/**
	 * 下载临时素材(多媒体文件)
	 * @param string $mediaId 素材的media_id
	 * @param string $savePath 存盘目录
	 *
	 * @return 不成功则返回false, 成功则返回存盘文件名. <br>
	 *    存盘文件名形如:  mediaId.jpg  (文件扩展名根据素材类型自动生成)
	 */
	public function downloadTempMedia($mediaId, $savePath) {
		//验证AccessToken是否有效
		if (!$this->isAccessTokenValid()) return false;
	
		//下载临时素材
		$url = "https://{$this->platformHost}/cgi-bin/media/get?media_id=$mediaId&access_token={$this->accessToken}";
		return $this->getUrlToFile($url, $savePath);
	}
	
	/**
	 * 下载永久素材(多媒体文件)
	 * @param string $mediaId 素材的media_id
	 * @param string $savePath 存盘目录
	 * @return mixed <br>不成功则返回false,  成功则返回存盘文件名 或 文件信息的数组. <br>
	 *    如果请求的素材为图文消息或video, 不下载文件，返回包含文件信息的数组.<br> 
	 *    对于其他类型的永久素材, 下载文件并存盘, 返回存盘文件名<br>
	 */
	public function downloadMaterial($mediaId, $savePath) {
		//验证AccessToken是否有效
		if (!$this->isAccessTokenValid()) return false;

		//构造URL
		$url = "https://{$this->platformHost}/cgi-bin/material/get_material?access_token={$this->accessToken}";
		$data = '{ "media_id": "' . $mediaId. '"}';
		
		echo "<br>url=$url<hr>";
		echo "data=$data<hr>";
		
		list($header, $body) =  $this->postUrl($url, $data, true);
		
		//如果HTTP响应值为空 或者不是一个数组,则出错
		if (empty($body))
			return $this->error('访问平台出错，无法下载素材',ERROR_HTTP);
		
		//分析HTTP 响应结果
		if ($this->isJson($body)) {
			//HTTP响应body为JSON
			$arr=json_decode($body,true);
			//如果结果中有errcode，则表示失败
			if (isset($arr['errcode'])) {
				$this->errorCode = $arr['errcode'];
				$this->errorMessage = $arr['errmsg'];
				return false;
			} else {
				return $arr; //返回结果数组(即,文件信息)
			}
		} else {
			//HTTP响应结果是文件数据
			echo 'header=' . $header.'<hr>';
			return false;
		}
	}
	
	/**
	 * 删除永久素材
	 * 
	 * @param string $mediaId 素材的media_id
	 * 
	 * @return 成功返回true, 失败返回false
	 */
	public function deleteMaterial($mediaId) {
		//验证AccessToken是否有效
		if (!$this->isAccessTokenValid()) return false;
		
		//构造URL
		$url = "https://{$this->platformHost}/cgi-bin/material/del_material?access_token={$this->accessToken}";
		$data = '{ "media_id": "' . $mediaId. '"}';
		
		$json = $this->postUrl($url, $data);
		
		return $this->processJsonResponse($json);
	}
	
	/**
	 * 获取素材列表
	 * 
	 * @param string $type 素材的类型，图片（image）、视频（video）、语音 （voice）、图文（news）
	 * @param int $offset 从全部素材的该偏移位置开始返回，0表示从第一个素材 返回
	 * @param int $count 返回素材的数量，取值在1到20之间
	 * 
	 * @return boolean|array 失败返回false, 成功则返回一个数组
	 */
	public function batchGetMaterial($type, $offset, $count) {
		//验证AccessToken是否有效
		if (!$this->isAccessTokenValid()) return false;
		
		//构造URL
		$url = "https://{$this->platformHost}/cgi-bin/material/batchget_material?access_token={$this->accessToken}";
		$data = '{ "type": "' . $type. '", "offset": ' . $offset. ', "count": ' . $count. ' }';
		
		$json = $this->postUrl($url, $data);
		
		return $this->processJsonResponse($json, 'array');
	}
	
	/**
	 * 获取永久素材总数
	 * 
	 * @return boolean|array 失败则返回false, 成功则返回一个数组。<br><br>
	 * 返回数组包括video, voice, image, news各类素材的数量，形如：
	 * <pre>
	 * {
	 *   "voice_count": COUNT,
	 *   "video_count": COUNT,
	 *   "image_count": COUNT,
	 *   "news_count": COUNT
	 * }
	 * </pre>
	 */
	public function getMaterialCount() {
		//验证AccessToken是否有效
		if (!$this->isAccessTokenValid()) return false;
		
		$url = "https://{$this->platformHost}/cgi-bin/material/get_materialcount?access_token={$this->accessToken}";
		
		$json = $this->getUrl($url);
		
		return $this->processJsonResponse($json, 'array');	
	}
	
	/**
	 * 取得文件的物理路径
	 * @param string $path
	 * @param string $isDirectory (可缺省)是否是目录, 默认值为false
	 * @return string 返回文件的物理路径。返回null表示无法找到文件
	 */
	public function getPhysicalPath($path, $isDirectory = false) {
		/*
		if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') { //Windows
			if ((substr($path, 1, 2) == ':\\') || (substr($path, 0, 2) == '\\\\') )
				return file_exists($path) ? $path : null;
		}
		*/
			
		if (substr($path, 0, 1) !== '/') {
			$path = dirname($_SERVER["REQUEST_URI"]).DIRECTORY_SEPARATOR.$path;
		}
			
		$path = $_SERVER['DOCUMENT_ROOT'].$path;
		
		if ($isDirectory) {
			if (is_dir($path)) return realpath($path);
		} else {
			if (file_exists($path)) return realpath($path);
		}
		
		return null;
	}
	
	//------------------------- 以下是 用户管理接口 -------------
	
	/**
	 * 用openId获取用户基本信息： 昵称等
	 * @param string $userId 用户openId
	 * @return array|bool 失败返回false; 如成功则返回一个数组，形如：
	 * {
	 "subscribe": 1,
	 "openid": "OPENID",
	 "nickname": "NICKNAME",
	 "sex": "sex",
	 "language": "LANG",
	 "city": "city"
	 }
	 */
	public function getUserInfo($userId){
		//验证AccessToken是否有效
		if (!$this->isAccessTokenValid()) return false;
	
		//获取用户基本信息的URL
		$INFO_URL = "https://".$this->platformHost."/cgi-bin/user/info?access_token=".$this->accessToken."&openid=$userId&lang=zh_CN";
		//向URL GET数据
		$json = $this->getUrl($INFO_URL);

		if ($this->isJson($json)) {
			//分析返回结果.　如果结果中有errcode，则表示失败
			$result=json_decode($json,true);
			if (isset($result['errcode'])) {
				$this->errorCode = $result['errcode'];
				$this->errorMessage = $result['errmsg'];
				return false;
			} else
				return result; //返回结果数据
		} else
			return $this->error('访问平台出错，无法获取获取用户基本信息',ERROR_HTTP);
	}
	
	//------------------- 以下是 客服消息 -----------------------
	
	/**
	 * 添加客服账号
	 * 
	 * @param string $serviceAccount 客服账号
	 * @param string $nickName 呢称
	 * @param string $password 密码
	 * 
	 * @return boolean 成功返回true, 失败返回false
	 */
	public function addServiceAccount($serviceAccount, $nickName, $password) {
		//验证AccessToken是否有效
		if (!$this->isAccessTokenValid()) return false;
		
		$url = "https://".$this->platformHost."/customservice/kfaccount/add?access_token=".$this->accessToken;
		$data = '{"kf_account" : "' .$serviceAccount. '","nickname" : "' .$nickName. '","password" : "' .$password. '"}';
		
		$json = $this->postUrl($url, $data);
		
		return $this->processJsonResponse($json);
	}
	
	/**
	 * 修改客服帐号
	 * @param string $serviceAccount 客服账号
	 * @param string $nickName 呢称
	 * @param string $password 密码
	 * 
	 * @return boolean 成功返回true, 失败返回false
	 */
	public function updateServiceAccount($serviceAccount, $nickName, $password) {
		//验证AccessToken是否有效
		if (!$this->isAccessTokenValid()) return false;
		
		$url = "https://".$this->platformHost."/customservice/kfaccount/update?access_token=".$this->accessToken;
		$data = '{"kf_account" : "' .$serviceAccount. '","nickname" : "' .$nickName. '","password" : "' .$password. '"}';
		
		$json = $this->postUrl($url, $data);
		
		return $this->processJsonResponse($json);
	}
	
	/**
	 * 删除客服帐号
	 * @param string $serviceAccount 客服账号
	 * @param string $nickName 呢称
	 * @param string $password 密码
	 * 
	 * @return boolean 成功返回true, 失败返回false
	 */
	public function deleteServiceAccount($serviceAccount, $nickName, $password) {
		//验证AccessToken是否有效
		if (!$this->isAccessTokenValid()) return false;
		
		$url = "https://".$this->platformHost."/customservice/kfaccount/del?access_token=".$this->accessToken;
		$data = '{"kf_account" : "' .$serviceAccount. '","nickname" : "' .$nickName. '","password" : "' .$password. '"}';
		
		$json = $this->postUrl($url, $data);
		
		return $this->processJsonResponse($json);
	}
	
	/**
	 * 设置客服帐号的头像
	 * @param string $serviceAccount 客服账号
	 * @param string $imageFilename 头像图片文件名, 必须是jpg格式，推荐使用640*640大小的图片以达到最佳效果
	 * 
	 * @return boolean 成功返回true, 失败返回false
	 */
	public function uploadSeviceHeadImage($serviceAccount, $imageFilename) {
		//验证AccessToken是否有效
		if (!$this->isAccessTokenValid()) return false;
		
		$url = "https://".$this->platformHost."/customservice/kfaccount/uploadheadimg?kf_account=$serviceAccount"
			."&access_token=".$this->accessToken;
		
		$json = $this->postFile($url, $imageFilename);
		
		return $this->processJsonResponse($json);
	}
	
	/**
	 * 获取所有客服账号
	 * 
	 * @return boolean 成功返回数组, 失败返回false
	 */
	public function getServiceList() {
		//验证AccessToken是否有效
		if (!$this->isAccessTokenValid()) return false;
		
		$url = "https://".$this->platformHost."/cgi-bin/customservice/getkflist?access_token=".$this->accessToken;
		
		$json = $this->getUrl($url);
		
		return $this->processJsonResponse($json, 'array');
	}
	
	/**
	 * 客服接口-发消息
	 * 
	 * @param string $receiverOpenId 接收者的OpenId
	 * 
	 * @param string $data (可缺省)要发送的数据
	 * <pre>
	 * 			如果此参数为一般文本,则发送文本
	 * 			如果不写此参数或为null, 则发送图文信息(需先用addNews())
	 * 			如果此参数写为 "[news]" + mediaId, 则发送指定mediaId的图文信息
	 * 			如果此参数写为 "[image]" + mediaId,则发送指定mediaId的图片
	 * 			如果此参数写为 "[voice]" + mediaId,则发送指定mediaId的语音
	 * 			如果此参数写为 "[video]" + mediaId,则发送指定mediaId的视频
	 * 			如果此参数写为 "[card]" + cardId, 则发送指定cardId的卡券
	 * </pre>
	 * 
	 * @return bool 成功返回true, 否则返回false
	 */
	public function sendServiceMessage($receiverOpenId, $data=null) {
		//验证AccessToken是否有效
		if (!$this->isAccessTokenValid()) return false;
	
		//发送消息的URL
		$URL = "https://".$this->platformHost."/cgi-bin/message/custom/send?access_token".$this->accessToken;

		if ($data == null)  { //发送图文信息
			//检查有否图文信息
			//上传图文信息素材, 获取media_id
			//TODO:
			$mediaId = '';
			$data = '[news]' . $mediaId;
		}
		
		if (!is_string($data)) $data = ''.$data;
		
		//准备POST数据
		$postData = "";
		
		$toUser = '"touser":"' . $receiverOpenId . '"';
			
		$prefix = strtolower(substr($data, 0, 6));
		$prefix2 = strtolower(substr($data, 0, 7));
		$this->sendText($data);
		
		if ($prefix == "[news]") { //发送指定mediaId的图文信息
			$mediaId = trim(substr($data, 6));
			$format = '{ %s, "mpnews":{ "media_id":"%s" },"msgtype":"mpnews" }';
			$postData = sprintf($format, $toUser, $data);
			
		} else if ($prefix2 == "[image]") { //发送指定mediaId的图片
			$mediaId = trim(substr($data, 7));
			$this->sendText("\r\nsend mediaId=$mediaId\r\n");
			$format = '{ %s, "image":{ "media_id":"%s" },"msgtype":"image" }';
			$postData = sprintf($format, $toUser, $data);
			
		} else if ($prefix2 == "[voice]") { //发送指定mediaId的语音
			$mediaId = trim(substr($data, 7));
			$format = '{ %s, "voice":{ "media_id":"%s" },"msgtype":"image" }';
			$postData = sprintf($format, $toUser, $data);
			
		} else if ($prefix2 == "[video]") { //发送指定mediaId视频
			$mediaId = trim(substr($data, 7));
			$format = '{ %s, "mpvideo":{ "media_id":"%s" },"msgtype":"mpvideo" }';
			$postData = sprintf($format, $toUser, $data);
			
		} else if ($prefix == "[card]") { //发送指定cardId的卡券
			$mediaId = trim(substr($data, 6));
			$format = '{ %s, "wxcard":{ "card_id":"%s" },"msgtype":"wxcard" }';
			$postData = sprintf($format, $toUser, $data);
			
		} else { //发送文本
			$format = '{ %s, "text":{ "content":"%s" }, "msgtype":"text" }';
			$postData = sprintf($format, $toUser, $data);
		}
		
		if (empty($postData)) return $this->error("发送失败", ERROR_NOT_IMPLEMENTED);

		//向URL POST数据
		$json = $this->postUrl($URL, $postData);
		
		$this->processJsonResponse($json);
	}
	
	
	/**
	 * 发送客服消息，
	 * 当用户主动发消息给公众号的时候（包括发送信息、点击自定义菜单click事件、订阅事件、扫描二维码事件、支付成功事件、用户维权），
	 * 微信将会把消息数据推送给开发者，开发者在一段时间内（目前修改为48小时）可以调用客服消息接口，通过POST一个JSON数据包来发送消息给普通用户，
	 * 在48小时内不限制发送次数。此接口主要用于客服等有人工消息处理环节的功能，方便开发者为用户提供更加优质的服务。
	 * @param string $messageType 消息类型，可以是text,image,news,voice,video,music
	 * @param string $userId 用户openId
	 * @param string $data 数据,此参数根据消息类型不同，含义不同。
	 *  <br>当消息类型为text, $data为文字内容
	 *  <br>当消息类型为image,voice,video, $data为图片media_id
	 *  <br>当消息类型为news, $data可缺省,图文数据需通过addNews()方法添加。
	 *  <br>当消息类型为music, $data为url
	 * @param string $title (可缺省)标题,适用于voice,video,music消息类型
	 * @param string $description (可缺省)描述,适用于voice,video,music消息类型
	 * @param string $thumb (可缺省)缩略图media_id,适用于music消息类型
	 * @return bool 成功返回true,否则返回false
	 */
	public function sendServiceMessage1($messageType, $userId, $data=null, $title=null, $description=null, $thumb=null) {
		//验证AccessToken是否有效
		if (!$this->isAccessTokenValid())
			return false;
	
			//发送客服消息的URL
			$URL = "https://".$this->platformHost."/cgi-bin/message/custom/send?access_token=".$this->accessToken;
			//准备数据
			switch($messageType) {
				case "text":
					$format = '{"touser":"%s","msgtype":"text","text":{"content":"%s"}}';
					$postData = sprintf($format,$userId,$data);
					break;
				case "image":
					$format = '{"touser":"%s","msgtype":"image","image":{"media_id":"%s"}}';
					$postData = sprintf($format,$userId,$data);
					break;
				case "voice":
					$format = '{"touser":"%s","msgtype":"voice","voice":{"media_id":"%s"}}';
					$postData = sprintf($format,$userId,$data);
					break;
				case "video":
					$format = '{"touser":"%s","msgtype":"video","video":{"media_id":"%s","title":"%s","description":"%s"}}';
					$postData = sprintf($format,$userId,$data,$title,$description);
					break;
				case "music":
					$format = '{"touser":"%s","msgtype":"music","music":{"title":"%s","description":"%s","musicurl":"%s","hqmusicurl":"%s","thumb_media_id":"%s"}}';
					$postData = sprintf($format,$userId,$title,$description,$data,$data,$thumb);
					break;
				case "news":
					$format = '{"touser":"%s","msgtype":"news","news":{"articles": [';
					$postData = sprintf($format,$userId);
					for ($i=0; $i<count($this->articles);$i++) {
						if ($i>0) $postData .= ',';
						$postData .= '{';
						$postData .= '"title":"' . $this->articles[$i]['Title'] . '",';
						$postData .= '"description":"' . $this->articles[$i]['Description'] . '",';
						$postData .= '"url":"' . $this->articles[$i]['Url'] . '",';
						$postData .= '"picurl":"' . $this->articles[$i]['PicUrl'] . '"';
						$postData .= '}';
					}
					$postData .=']}}';
					break;
				default:
					return $this->error('不支持消息类型'.$messageType, ERROR_PARAMETER);
			}
	
			//向URL POST数据
			$json = $this->postUrl($URL, $postData);
	
			if ($this->isJson($json)) {
				//分析返回结果.　如果结果中errcode=0，则表示成功
				$result=json_decode($json,true);
				if ($result['errcode']==0) {
					$this->errorCode = $result['errcode'];
					$this->errorMessage = $result['errmsg'];
					return false;
				} else {
					return true;
				}
			} else
				return $this->error('访问平台出错，无法发送客服消息',ERROR_HTTP);
	}
	
	
	//------------------- 以下是 群发接口 -----------------------
	/**
	 * 群发消息
	 * @param string $data (可缺省)要发送的数据
	 * <pre>
	 * 			如果此参数为一般文本,则发送文本
	 * 			如果不写此参数或为null, 则发送图文信息(需先用addNews())
	 * 			如果此参数写为 "[news]" + mediaId, 则发送指定mediaId的图文信息
	 * 			如果此参数写为 "[image]" + mediaId,则发送指定mediaId的图片
	 * 			如果此参数写为 "[voice]" + mediaId,则发送指定mediaId的语音
	 * 			如果此参数写为 "[video]" + mediaId,则发送指定mediaId的视频
	 * 			如果此参数写为 "[card]" + cardId, 则发送指定cardId的卡券
	 * </pre>
	 * @param string $scopeId (可缺省)发送范围
	 * <pre>
	 * 			如果不写此参数或为null,则全部发送.
	 * 			如果写分组tagid, 则分组发送.
	 * 			如果写一个或多个userid,则向指定用户发送. 多个id用英文逗号分隔
	 * </pre>
	 * @return bool 成功向微信平台提交群发申请返回true, 否则返回false
	 */
	public function groupSend($data=null, $scopeId = null) {
		//验证AccessToken是否有效
		if (!$this->isAccessTokenValid()) return false;
	
		//发送客服消息的URL
		$URL = "https://".$this->platformHost."/cgi-bin/message/mass/sendall?access_token=".$this->accessToken;

		if ($data == null)  { //发送图文信息
			//检查有否图文信息
			//上传图文信息素材, 获取media_id
			$mediaId = '';
			$data = '[news]' . $mediaId;
		}
		
		if (!is_string($data)) $data = ''.$data;
		
		//准备POST数据
		$postData = "";
			
		$prefix = strtolower(substr($data, 0, 6));
		$prefix2 = strtolower(substr($data, 0, 7));
		$this->sendText($data);
		
		if ($prefix == "[news]") { //发送指定mediaId的图文信息
			$mediaId = trim(substr($data, 6));
			$format = '{ %s, "mpnews":{ "media_id":"%s" },"msgtype":"mpnews" }';
			$postData = sprintf($format, $this->convertScopeId($scopeId), $data);
			
		} else if ($prefix2 == "[image]") { //发送指定mediaId的图片
			$mediaId = trim(substr($data, 7));
			$this->sendText("\r\nsend mediaId=$mediaId\r\n");
			$format = '{ %s, "image":{ "media_id":"%s" },"msgtype":"image" }';
			$postData = sprintf($format, $this->convertScopeId($scopeId), $data);
			
		} else if ($prefix2 == "[voice]") { //发送指定mediaId的语音
			$mediaId = trim(substr($data, 7));
			$format = '{ %s, "voice":{ "media_id":"%s" },"msgtype":"image" }';
			$postData = sprintf($format, $this->convertScopeId($scopeId), $data);
			
		} else if ($prefix2 == "[video]") { //发送指定mediaId视频
			$mediaId = trim(substr($data, 7));
			$format = '{ %s, "mpvideo":{ "media_id":"%s" },"msgtype":"mpvideo" }';
			$postData = sprintf($format, $this->convertScopeId($scopeId), $data);
			
		} else if ($prefix == "[card]") { //发送指定cardId的卡券
			$mediaId = trim(substr($data, 6));
			$format = '{ %s, "wxcard":{ "card_id":"%s" },"msgtype":"wxcard" }';
			$postData = sprintf($format, $this->convertScopeId($scopeId), $data);
			
		} else { //发送文本
			$format = '{ %s, "text":{ "content":"%s" }, "msgtype":"text" }';
			$postData = sprintf($format, $this->convertScopeId($scopeId), $data);
		}
		
		if (empty($postData)) return $this->error("群发数据错误", ERROR_NOT_IMPLEMENTED);

		//向URL POST数据
		$json = $this->postUrl($URL, $postData);
		
		$this->processJsonResponse($json);
	}
	
	/**
	 * 用于groupSend()函数, 将$scopeId转为json中的文本描述
	 * @param string $scopeId
	 */
	private function convertScopeId($scopeId) {
		if (empty($scopeId) || strpos($scopeId,',')===false ) { //全发 或 根据标签进行群发
			$format = '"filter":{ "is_to_all":%s, "tag_id":%s }';
			$isAll = empty($scopeId) ? "true" : "false";
			$tagId = empty($scopeId) ? "0" : $scopeId;
			return sprintf($format, $isAll, $tagId);
		} else {
			//指定OpenID群发
			$format = '"touser":[%s]';
			$arr = explode(",", $scopeId);
			$toUser = "";  //"OPENID1", "OPENID2"
			foreach ($arr as $item) {
				if (!empty($toUser)) $touser .= ', ';
				$touser .= '"'. trim($item) .'"';
			}
			return sprintf($format, $toUser);
		}
	}
	
	//-------------------以下是 带参数的二维码 相关的函数 ---------------------
	/**
	 * 创建临时二维码ticket
	 * @param integer $scene_id 场景值ID，临时二维码时为32位非0整型
	 * @param integer $expire_seconds 该二维码有效时间，以秒为单位。 最大不超过1800
	 * @return string 成功则返回二维码ticket；错误则返回false
	 */
	public function qrcodeCreateTemp($scene_id, $expire_seconds) {
		//验证AccessToken是否有效
		if (!$this->isAccessTokenValid())
			return false;
	
			//创建二维码URL
			$URL = "https://".$this->platformHost."/cgi-bin/qrcode/create?access_token=".$this->accessToken;
			//数据格式: {"expire_seconds": 1800, "action_name": "QR_SCENE", "action_info": {"scene": {"scene_id": 123}}}
			$data = '{"expire_seconds": %s, "action_name": "QR_SCENE", "action_info": {"scene": {"scene_id": %s}}}';
			$data = sprintf($data,$expire_seconds,$scene_id);
			//向URL POST数据
			$json = $this->postUrl($URL, $data);
	
			if ($this->isJson($json)) {
				//分析返回结果.　如果结果有ticket，则表示成功
				$result=json_decode($json,true);
				if (isset($result['ticket']))
					return $result['ticket']; //返回ticket值
					else {
						$this->errorCode = $result['errcode'];
						$this->errorMessage = $result['errmsg'];
						return false;
					}
			} else
				return $this->error('访问平台出错，无法创建临时二维码',ERROR_HTTP);
					
	}
	
	/**
	 * 创建永久二维码ticket
	 * @param integer $scene_id 场景值ID，永久二维码时最大值为100000（目前参数只支持1--100000）
	 * @return string 成功则返回二维码ticket；错误则返回false
	 */
	public function qrcodeCreate($scene_id) {
		//验证AccessToken是否有效
		if (!$this->isAccessTokenValid())
			return false;
	
			//创建二维码URL
			$URL = "https://".$this->platformHost."/cgi-bin/qrcode/create?access_token=".$this->accessToken;
			//数据格式: {"action_name": "QR_LIMIT_SCENE", "action_info": {"scene": {"scene_id": 123}}}
			$data = '{"action_name": "QR_LIMIT_SCENE", "action_info": {"scene": {"scene_id": %s}}}';
			$data = sprintf($data,$scene_id);
			//向URL POST数据
			$json = $this->postUrl($URL, $data);
	
			if ($this->isJson($json)) {
				//分析返回结果.　如果结果有ticket，则表示成功
				$result=json_decode($json,true);
				if (isset($result['ticket']))
					return $result['ticket']; //返回ticket值
					else {
						$this->errorCode = $result['errcode'];
						$this->errorMessage = $result['errmsg'];
						return false;
					}
			} else
				return $this->error('访问平台出错，无法创建临时二维码',ERROR_HTTP);
					
	}
	
	/**
	 * 通过ticket换取二维码, 保存到文件中
	 * @param string $ticket 二维码ticket
	 * @param string $saveFilename (可缺省)保存文件名，如果此参数缺省，则返回值为二维码图片URL，否则将二维码存盘。
	 * @return string|bool 当$saveFilename参数缺省时，返回值为二维码图片URL。 <br>当指明$saveFilename参数时，存盘成功返回存盘字节数,否则返回false
	 */
	public function qrcodeGet($ticket, $saveFilename=null) {
		//验证AccessToken是否有效
		if (!$this->isAccessTokenValid())
			return false;
	
			//换取二维码的URL
			$URL = "https://".$this->platformHost."/cgi-bin/showqrcode?ticket=$ticket";
			if (empty($saveFilename))
				return $URL;
				else {
					$data = $this->getUrl($URL);
					return file_put_contents($saveFilename, $data);
				}
	}
	
	//-------------------以下是 自定义菜单 ---------------------
	
	/**
	 * 菜单开始
	 */
	public function startMenu(){
		$this->menus = array();
		$this->menuItems = array();
		$this->current_menu_name = "";
		$this->current_menu_key = null;
	}
	
	/**
	 * 结束当前下拉菜单定义
	 */
	private function finishMenu() {
		if (!empty($this->current_menu_name)) {
			if (empty($this->current_menu_key)) {
				$menu = array('name' => $this->current_menu_name, 'sub_button' => $this->menuItems);
			} else {
				if ($this->isUrl($this->current_menu_key)) {
					$menu = array('type' => 'view', 'name' => $this->current_menu_name, 'url' => $this->current_menu_key);
				} else
					$menu = array('type' => 'click', 'name' => $this->current_menu_name, 'key' => $this->current_menu_key);
			}
					
			$this->menus[] = $menu;
			$this->menuItems = array();
			$this->current_menu_name = "";
			$this->current_menu_key = null;
		}
	}
	
	/**
	 * 增加一个下拉菜单
	 * @param string $name 上拉菜单名称
	 * @param string $menuKey (可缺省)菜单键值，如该参数缺省则此菜单是上拉菜单(可添加子菜单项）,否则此菜单是独立菜单项(不可添加子菜单项，可点击)
	 * @return none
	 */
	public function addMenu($name, $menuKey=null){
		$this->finishMenu();
		$this->current_menu_name = $name;
		$this->current_menu_key = $menuKey;
	}
	
	/**
	 * 增加一个子菜单项
	 * @param string $name 菜单项
	 * @param string $keyOrUrl 菜单键值或URL
	 * @param string $menuType (可缺省)菜单的响应动作类型，默认值为空，可选值为：<br>
	 * <pre>
	 *    scancode_waitmsg   扫码带提示
	 *    scancode_push      扫码推事件
	 *    pic_sysphoto       系统拍照发图
	 *    pic_photo_or_album 拍照或者相册发图
	 *    pic_weixin         微信相册发图
	 *    location_select    发送位置
	 *    media_id           图片， 此时keyOrUrl值是media_id
	 *    view_limited       图文消息， 此时keyOrUrl值是media_id
	 * </pre>
	 * @return none
	 */
	public function addMenuItem($name, $keyOrUrl, $menuType = '' ){
		$this->current_menu_key = null;
		
		if (empty($menuType)) {
			if ($this->isUrl($keyOrUrl)) {
				$menuItem = array( 'type' => "view",
						'name' => $name,
						'url' => $keyOrUrl);
			} else {
				$menuItem = array( 'type' => "click",
						'name' => $name,
						'key' => $keyOrUrl);
			}
			
		} else {
			if ($menuType=="media_id" || $menuType=="view_limited")
				$menuItem = array( 'type' => $menuType,
						'name' => $name,
						'media_id' => $keyOrUrl);
			else
				$menuItem = array( 'type' => $menuType,
						'name' => $name,
						'key' => $keyOrUrl);
				
		}
		
		$this->menuItems[] = $menuItem;
	}
	
	/**
	 * 生成菜单
	 * @return boolean 成功返回true,失败返回false
	 */
	public function createMenu(){
		if (count($this->menus)==0) return $this->error('菜单未定义',ERROR_PRECONDITION);
	
		//生成菜单定义数据
		$this->finishMenu();
		$data = array('button' => $this->menus);
		$menuDefineStr = $this->json_encode($data); //$menuDefineStr 是菜单定义数据(json格式)
	
		//验证AccessToken是否有效
		if (!$this->isAccessTokenValid()) return false;
		
		//先删除菜单
		$DELETE_MENU_URL = "https://".$this->platformHost."/cgi-bin/menu/delete?access_token=".$this->accessToken;
		$result = $this->getUrl($DELETE_MENU_URL);
		
		//创建菜单的URL
		$CREATE_MENU_URL = "https://".$this->platformHost."/cgi-bin/menu/create?access_token=".$this->accessToken;
		//向URL POST菜单定义数据
		$json = $this->postUrl($CREATE_MENU_URL, $menuDefineStr); 	//sendPost($this->host, $CREATE_MENU_URL, $menuStr, true);
		
		return $this->processJsonResponse($json);
	}
	
	//-------------------以下是 微信网页 相关函数-------------------------------
	
	/**
	 * 返回微信网页授权URL
	 * 
	 * @param string $redirect_uri  授权后重定向去到的URL (应使用https链接来确保授权code的安全性)
	 * @param string $state  (可缺省)携带到重定向URL的state参数，页面将跳转至 redirect_uri/?code=CODE&state=STATE。
	 * @param string $scope  (可缺省)应用授权作用域， 取值为 snsapi_userinfo（默认值）, snsapi_base等，说明如下：<br><br>
	 * 			snsapi_base 不弹出授权页面，直接跳转，只能获取用户openid<br>
	 * 			snsapi_userinfo 弹出授权页面，可通过openid拿到昵称、性别、所在地。<br>
	 * 							并且，即使在未关注的情况下，只要用户授权，也能获取其信息<br>
	 * @param string $appId  (可缺省)appId, 默认值为本对象通过setAppId()设置的appId
	 * 
	 * @return boolean|string 返回微信网页授权URL, 错误返回false 
	 */
	public function authUrl($redirect_uri, $state='', $scope='snsapi_userinfo', $appId='') {
		if (empty($appId)) $appId = $this->appId;
		if (empty($appId)) return $this->errorCode('没有设置AppId，请先用setAppId()设置', ERROR_PARAMETER);
	
		$newUrl  = 'https://open.weixin.qq.com/connect/oauth2/authorize?appid='.$appId;
		$newUrl .= '&redirect_uri='.urlencode($redirect_uri);
		$newUrl .= '&response_type=code&scope='.$scope;
		$newUrl .= '&state='.$state;
		//$newUrl .= '&connect_redirect=1';
		$newUrl .= '#wechat_redirect';
	
		return $newUrl;
	}
	
	/**
	 * 通过微信授权code换取网页授权access_token<br>
	 * 注意：code只能使用一次，获得access_token后请自行保存。
	 * 
	 * @param string $code 微信网页授权重定向网页携带的code
	 * @return array|boolean 失败返回false, 成功返回一个数组，形如：<pre>	
		 { 
		 "access_token":"ACCESS_TOKEN",    
		 "expires_in":7200,    
		 "refresh_token":"REFRESH_TOKEN",    
		 "openid":"OPENID",    
		 "scope":"SCOPE" 
		 } 
	 * </pre>
	 */
	public function authGetAccessToken($code) {
		$url = 'https://'.$this->platformHost.'/sns/oauth2/access_token?appid='.$this->appId;
		$url .= '&secret='. $this->appSecret;
		$url .= "&code=$code&grant_type=authorization_code";
		
		$json = $this->getUrl($url);
		return $this->processJsonResponse($json, 'array');
	}
	
	/**
	 * 刷新网页授权access_token
	 * @param string $refresh_token 获取access_token时一并返回的refresh_token参数
	 * @return array|boolean 失败返回false, 成功返回一个数组，形如：<pre>	
		{ 
		 "access_token":"ACCESS_TOKEN",  
		 "expires_in":7200,   
		 "refresh_token":"REFRESH_TOKEN",   
		 "openid":"OPENID",   
		 "scope":"SCOPE" 
		 }
		</pre> 
	 */
	public function authRefreshAccessToken($refresh_token) {
		$url = 'https://'.$this->platformHost.'/sns/oauth2/refresh_token?appid='. $this->appId;
		$url .= '&grant_type=refresh_token&refresh_token=' .$refresh_token;
		$json = $this->getUrl($url);
		return $this->processJsonResponse($json, 'array');
	}
	
	/**
	 * 授权网页 拉取用户信息(需scope为 snsapi_userinfo)
	 * 
	 * @param string $authAccessToken 网页授权接口调用凭证,注意：此access_token与基础支持的access_token不同
	 * @param string $openId 用户的唯一标识
	 * @param string $language (可缺省)语言版本，zh_CN 简体中文(默认值)，zh_TW 繁体，en 英语
	 * 
	 * @return array|boolean 失败返回false, 成功返回一个数组，形如：<pre>	
		{ 
		 "openid":" OPENID",  
		 "nickname": NICKNAME,   
		 "sex":"1",   
		 "province":"PROVINCE"   
		 "city":"CITY",   
		 "country":"COUNTRY",    
		 "headimgurl": "http://wx.qlogo.cn/mmopen/g3MonUZtNHkdmzicIlibx6iaFqAc56vxLSUfpb6n5WKSYVY0ChQKkiaJSgQ1dZuTOgvLLrhJbERQQ4eMsv84eavHiaiceqxibJxCfHe/46",  
		 "privilege": [ "PRIVILEGE1" "PRIVILEGE2" ],    
		 "unionid": "o6_bmasdasdsad6_2sgVt7hMZOPfL" 
		 }
		</pre> 
	 */
	public function authGetUserInfo($authAccessToken, $openId, $language='zh_CN') {
		$url = 'https://'.$this->platformHost.'/sns/userinfo?access_token='. $authAccessToken;
		$url .= '&openid='. $openId.'&lang='.$language;
		$json = $this->getUrl($url);
		return $this->processJsonResponse($json, 'array');
	}
	
	/**
	 * 检验网页授权凭证（access_token）是否有效
	 * 
	 * @param string $authAccessToken 网页授权接口调用凭证,注意：此access_token与基础支持的access_token不同
	 * @param string $openId 用户的唯一标识
	 * 
	 * @return boolean 有效返回true, 无效返回false
	 */
	public function authCheckAccessToken($authAccessToken, $openId) {
		$url = 'https://'.$this->platformHost.'/sns/auth?access_token='. $authAccessToken;
		$url .= '&openid='. $openId;
		$json = $this->getUrl($url);
		return $this->processJsonResponse($json);
	}
	
	
	/**
	 * 读取授权用户信息（自动完成网页授权全过程）
	 * 
	 *  @param string $onlyOpenId 是否只需要用户openId
	 * 
	 *  @return array|boolean 失败返回false, 成功返回一个数组，形如：<pre>	
		{ 
		 "openid":" OPENID",  
		 "nickname": NICKNAME,   
		 "sex":"1",   
		 "province":"PROVINCE"   
		 "city":"CITY",   
		 "country":"COUNTRY",    
		 "headimgurl": "http://wx.qlogo.cn/mmopen/g3MonUZtNHkdmzicIlibx6iaFqAc56vxLSUfpb6n5WKSYVY0ChQKkiaJSgQ1dZuTOgvLLrhJbERQQ4eMsv84eavHiaiceqxibJxCfHe/46",  
		 "privilege": [ "PRIVILEGE1" "PRIVILEGE2" ],    
		 "unionid": "o6_bmasdasdsad6_2sgVt7hMZOPfL" 
		 }
		</pre> 
	 */
	public function authUser( $onlyOpenId = false ) {
		
		//如果URL中没有code，则不是微信平台重定向来的
		if ( !isset($_GET['code']) ) {
			if ( headers_sent() )
				return $this->error("authUser()将重定向URL，在调用authUser()前，不能有任何实际的输出", ERROR_NOT_INITIALZED);
			
			//生成微信网页授权URL
			$url = $this->currentUrl();
			if ($onlyOpenId)
				$newUrl = $this->authUrl($url,'','snsapi_base');
			else
				$newUrl = $this->authUrl($url);
			if ($newUrl == false) return false;
			
			//跳转到微信网页授权URL
			exit(header("Location: $newUrl"));
			//header('Location: '.$newUrl);
			//die();
		}
		
		$code = $_GET['code'];//取得code参数
		
		$token_array = false; //access_token数据数组
		
		//用于存放网页access_token的缓存文件名
		$cache_filename = $this->saveDir. "code_".hash("md5", $code);
		
		//如要缓存文件存在
		if ( file_exists($cache_filename) ) {
			//则，优先从缓存文件读出access_token数据
			$token_array = json_decode(file_get_contents($cache_filename), true);
			//读出后，判断有否超时
			if ( is_array($token_array) && isset($token_array['expires_time']) ) {
				if ( time() > $token_array['expires_time'] - 20 ) { //如果即将超时
					//刷新AccessToken
					$token_array = $this->authRefreshAccessToken( $token_array['refresh_token'] );
				}	
			} else {
				$token_array = false;
			}
		} 
		
		//如果无法读出
		if ( !is_array($token_array) ) {
			//则，向微信平台索取access_token
			$token_array = $this->authGetAccessToken($code);
			if ( is_array($token_array) ) {
				//增加超时时间
				$token_array['expires_time'] = time() + $token_array['expires_in'];
			}
		}
		
		if ( !is_array($token_array) ) return false;
		
		//如果没有缓存文件， 则写缓存文件
		if ( !file_exists($cache_filename) )
			file_put_contents($cache_filename, json_encode($token_array));
		
		if ( ! $onlyOpenId) {
			//当需要用户全信息, 则，向微信平台索取全部用户信息
			$user_info = $this->authGetUserInfo($token_array['access_token'], $token_array['openid']);
			if (!is_array($user_info)) return false;
			return $user_info;
			
		} else {
			//当仅要openid
			if ( isset($token_array['openid']) ) {
				$user_info = array('openid'=> $token_array['openid'], 'nickname'=>'','headimgurl'=>'');
				return $user_info;
			} else
				return false;
		}
		
		
	}
	
	/**
	 * 自动删除cache文件
	 * 
	 * @param string $prefix  文件名前缀
	 * @param number $timeout 超时秒数, 即文件时间比当前时间早timeout秒, 默认值为36000秒
	 * @return boolean
	 */
	public function autoDeleteCacheFile($prefix, $timeout = 36000 ) {
		if (empty($this->saveDir)) return 0;
		if(!($dp = opendir($this->saveDir))) return 0;
		
		//find files matched
		$file_array = array();
		while ( $file = readdir ($dp) ) {
			$len = strlen($prefix);
			if ($file=='.' && $file=='..')
				continue;
			if( $prefix=='' || substr($file,0,$len) == $prefix ) {
				$file_array[] =  $file;
			}
		}
		
		$delete_count = 0;
		while ( list($fileIndexValue, $file_name) = each ($file_array) )
		{
			$file_name = $this->saveDir . $file_name;
			if( ( time()- filemtime($file_name) ) > $timeout ) //if timeout
			{
				if ( unlink($file_name) )
					$delete_count++;
			}
		}
		
		closedir($dp);
		return $delete_count;
	}
	
	
	
	//-------------------数据文件读写函数-------------------------------
	
	/**
	 * 从数据文件(Json格式)中读取字段值<br>
	 * 数据文件均存盘于saveDir中
	 * @param string $dataFileName 数据文件名
	 * @param string $fieldName 字段名
	 * @return mixed 正确读出返回数据值,读错误返回false
	 */
	public function readDataField($dataFileName, $fieldName) {
		$fileName = $this->saveDir.$dataFileName;
		if (file_exists($fileName)) {
			$content = file_get_contents($fileName);
			if ($content!==false) {
				$arr = json_decode($content, true);
				if ($arr!=false) {
					if (isset($arr[$fieldName]))
						return $arr[$fieldName];
				}
			}
		}
		return false;
	}
	
	/**
	 * 向数据文件(Json格式)写字段值<>
	 * 数据文件均存盘于saveDir中. 如果数据文件不存在,则创建它
	 * @param string $dataFileName 数据文件名
	 * @param string $fieldName 字段名
	 * @param mixed $fieldValue 字段值
	 * @return boolean 正确写入返回true,写入错误返回false
	 */
	public function writeDataField($dataFileName, $fieldName, $fieldValue) {
		$fileName = $this->saveDir.$dataFileName;
		
		if (file_exists($fileName)) {
			$data = file_get_contents($fileName);
			if ($data!==false) {
				$arr = json_decode($data, true);
				if ($arr!=false) {
					$arr[$fieldName] = $fieldValue;
					$bytes = file_put_contents($fileName, $this->json_encode($arr));
					return $bytes>0 ? true : false;
				}
			}
			return false;
			
		} else {
			$arr = array();
			$arr[$fieldName] = $fieldValue;
			$bytes = file_put_contents($fileName, $this->json_encode($arr)); 
			return $bytes>0 ? true : false;
		}
	}
	
	/**
	 * 在数据文件(Json格式)中删去某个字段<>
	 * 数据文件均存盘于saveDir中. 
	 * @param string $dataFileName 数据文件名
	 * @param string $fieldName 字段名
	 * @return boolean 正确返回true,错误返回false
	 */
	public function deleteDataField($dataFileName, $fieldName) {
		$fileName = $this->saveDir.$dataFileName;
		
		if (file_exists($fileName)) {
			$data = file_get_contents($fileName);
			if ($data!==false) {
				$arr = json_decode($data, true);
				if ($arr!=false) {
					if (isset($arr[$fieldName])) {
						unset($arr[$fieldName]);
						if (count($arr)==0) {
							return unlink($fileName);
						} else {
							$bytes = file_put_contents($fileName, $this->json_encode($arr));
							return $bytes>0 ? true : false;
						}
					} else {
						return true;
					}
				}
			}
			return false;	
		} else 
			return true;
	}
	
	/**
	 * 删除数据文件<br>
	 * 数据文件均存盘于saveDir中.
	 * @param string $dataFileName 数据文件名
	 * 
	 * @return boolean 正确返回true,错误返回false
	 */
	public function deleteDataFile($dataFileName) {
		$fileName = $this->saveDir.$dataFileName;
		if (file_exists($fileName)) {
			return unlink($fileName);
		} else
			return true;
	}
	
	
	//-------------------http 处理函数: getUrl(), postUrl()---------------------
	/**
	 * 用HTTP GET 方式访问url，取得响应内容
	 * @param string $url URL
	 * @return mixed|string 响应内容
	 */
	protected function getUrl($url) {
		if (function_exists ('curl_init')) { //如果 curl库已加载
			//采用curl库实现HTTP GET
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			//curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
			curl_setopt($ch, CURLOPT_URL, $url);
			$result = curl_exec($ch);
			curl_close($ch);
			return $result;
		} else {
			//采用file_get_contents()实现HTTP GET
			return file_get_contents($url);
		}
	}
	
	/**
	 * 分析HTTP响应Header段，取出 content-type, filename
	 * @param string $httpHeader Header段文字
	 * @return array 返回一个数组（包含2个元素，第0个元素是content-type，第一个元素是 filename)
	 */
	protected function getFileInfoFromHeader($httpHeader) {
		$header_array = explode("\n", $httpHeader);
		$headers = array();
		foreach($header_array as $header_value) {
			$header_pieces = explode(':', $header_value);
			if(count($header_pieces) == 2) {
				$headers[strtolower($header_pieces[0])] = trim($header_pieces[1]);
			}
		}
		//取得content-type 和 content-disposition
		$contentType = isset($headers['content-type']) ? $headers['content-type'] : '';
		$contentDisposition = isset($headers['content-disposition']) ? $headers['content-disposition'] : '';
		$filename = '';
		
		//分析 content disposition, 取出filename
		$arr = explode(";", $contentDisposition);
		foreach($arr as $tag) {
			$tag_array = explode('=', $tag);
			if( count($tag_array) == 2 && strtolower(trim($tag_array[0]))=='filename')
				$filename = trim($tag_array[1]);
		}
		//去掉filename两端的引号
		if (substr($filename,0,1)=='"' && substr($filename,strlen($filename)-1,1)=='"')
			$filename = substr($filename, 1, strlen($filename)-2);
		
		return array($contentType, $filename);
	}
	
	
	/**
	 * 用HTTP GET 方式访问url，取得下载文件, 存到指定目录中
	 * @param string $url URL
	 * @param string $savePath 存盘目录<br>
	 *     存盘文件名由HTTP返回结果中指定.
	 * 
	 * @return mixed 成功则返回存盘文件名, 错误返回false
	 */
	protected function getUrlToFile($url, $savePath) {
		//检查有否CURL库
		if (!function_exists ('curl_init'))
			return $this->error('PHP未安装CURL库模块，无法上传文件', ERROR_HTTP);
			
		//检查存盘目录正确性
		$savePath = $this->getPhysicalPath($savePath, true);
		if (empty($savePath))
			return $this->error('存盘目录不存在或不正确', ERROR_WRITE);
			
		//采用curl库实现HTTP GET
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_BINARYTRANSFER, true) ; 
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		//curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, 1); //获得Header
		//curl_setopt($ch, CURLOPT_NOBODY, 0); //只取body头  
		$result = curl_exec($ch);
		$httpinfo = curl_getinfo($ch);
		curl_close($ch);		
		
		$filename = '';
		$contentType = '';
		
		//解析Http头部的Content-Disposition获取文件名
		if ($httpinfo['http_code']==200) {
			list($header, $body) = explode("\r\n\r\n", $result, 2);
			list($contentType, $filename) = $this->getFileInfoFromHeader($header);
		}
		
		//分析结果
		if ($httpinfo['http_code']==200) {
			//如果结果是Json
			if ($this->isJson($body)) {
				$arr = json_decode($body, true);
				if ($arr['errcode']) {
					return $this->error($arr['errmsg'], $arr['errcode']);
				} else {
					return $this->error("平台返回结果不正确", ERROR_HTTP);
				}
			}
			
			if (empty($filename)) {
				//如果没有取得文件名
				return $this->error("HTTP响应中没有文件名", ERROR_HTTP);
			} else {
				//将文件存盘
				$bytes = file_put_contents($savePath .DIRECTORY_SEPARATOR. $filename, $body);
				if ($bytes === false) {
					return $this->error("文件存盘不成功", ERROR_WRITE);
				} else {
					if ($bytes == $httpinfo['download_content_length'])
						return $filename;
					else
						return $this->error("文件存盘字节数与下载不符", ERROR_WRITE);
				}
			}
		} else {
			//http_code 不是 200
			if ($httpinfo['http_code']==0)
				return $this->error("无法访问平台", ERROR_HTTP);
			else
				return $this->error("访问平台错误", $httpinfo['http_code']);
		}
	}
	
	/**
	 * 用HTTP POST 方式访问url, 提交数据, 取得响应内容
	 * @param string $url URL
	 * @param string $data 需提交的数据
	 * @param boolean $needHeader (可缺省)返回值是否需要HTTP Header, 默认值为false(不需要)
	 * @return mixed 如$needHeader为false, 则返回值为HTTP响应内容文本.<br>
	 *  如$needHeader为true, 则返回值为一个数组, 其中包含 header, body 两个元素
	 */
	protected function postUrl($url, $data, $needHeader = false) {
	     //upload file see: http://www.cnblogs.com/walter371/p/5688059.html
	     
		$contentType = "application/x-www-form-urlencoded";
		
		if (is_string($data)) {
			if ($this->isXml($data)) $contentType = "raw/xml";
			if ($this->isJson($data)) $contentType = "application/json";
		}
	
		if (function_exists ('curl_init')) { //如果 curl库已加载
			//采用curl库实现HTTP POST
			$ch = curl_init( $url );
			curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, "POST"); //使用POST方式
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); //对于SSL，要增加这一句
			//curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1);
			
			if (is_array($data)) { //如果 data是数组, 则为上传文件
				if ( version_compare(phpversion(),'5.5.0') >= 0 ) //如果PHP版本>5.5.0,要设置CURLOPT_SAFE_UPLOAD 
					curl_setopt( $ch, CURLOPT_SAFE_UPLOAD, false);//for PHP > 5.5.0
				@curl_setopt( $ch, CURLOPT_POSTFIELDS, $data);
			} else {
				curl_setopt( $ch, CURLOPT_POSTFIELDS, $data);
			}
			
			if ( $needHeader )
				curl_setopt( $ch, CURLOPT_HEADER, true);
			else
				curl_setopt( $ch, CURLOPT_HEADER, false); 
			
			if (is_string($data)) {//如果POST数据是文本
				//设置 Content-Type
				curl_setopt($ch, CURLOPT_HTTPHEADER, array(
					'Content-Type: '.$contentType,
					'Content-Length: '.strlen($data)
					)
				);
			}
			
			$result = curl_exec( $ch ); //执行
			curl_close($ch);
			
			if ( $needHeader ) {
				//需要header, 将result切分为header 和  body
				return explode("\r\n\r\n", $result, 2);
			} else {
				return $result;
			}
			
		} else if (ini_get('allow_url_fopen')=='1') { //是否能 file_get_content()函数
			echo "in<hr>";
			//如果采用file_get_content()实现HTTP POST
			$options = array(
					'http' => array(
							'header'  => "Content-type: $contentType\r\n".
							"User-Agent: Mozilla/5.0;IE6\r\n".
							"Accept: text/xml,text/html,*/*",
							'method'  => 'POST',
							'content' => $data,
					),
			);
			$context  = stream_context_create($options);
			// set to the user defined error handler
			//$old_error_handler = set_error_handler("browseUrlErrorHandler");
	
			//post url using file_get_contents()
			$result = file_get_contents($url, false, $context); //以POST方式访问
			return $result;
		} else {
			//如果什么都没有，则采用socket通信实现HTTP POST
			//首先解析url，得到各项参数
			$array = parse_url($url);
			if (isset($array['scheme'])) $scheme=strtolower($array['scheme']); else $scheme='http';
			if (isset($array['host'])) $host=strtolower($array['host']); else $host='';
			if (isset($array['port']))
			$port=strtolower($array['port']);
			else {
				$port = 80;
				if ($scheme=='https') $port=443;
			}
			//if (isset($array['path'])) $path=strtolower($array['path']); else $path='/';
			if (!isset($array['path'])) $path='/';
			if ($scheme=='https') $prefix = "ssl://"; else $prefix = '';
			if (empty($host)) return false;
			
			if ( (!isset($array['query'])) || empty($array['query']) )
				$post_path = $array['path'];
			else 
				$post_path = $array['path'].'?'.$array['query'] ;

			//构造POST请求数据
			$header = "POST ".$post_path." HTTP/1.0\r\n";
			$header .= "Host: $host:$port\r\n";
			$header .= "User-Agent: Mozilla/5.0;IE6\r\n";
			$header .= "Accept: text/xml,text/html,*/*\r\n";
			$header .= "Content-Type: $contentType\r\n";
			$header .= "Content-Length: " . strlen($data) . "\r\n";
			$header .= "Connection: Close\r\n\r\n";
			$header .= $data;

			$result = ""; //http响应结果文本
			$content_started = false; //http响应结果中 内容有否开始
	
			//打开socket
			if (empty($prefix))
				$fp = fsockopen($host, $port);
			else
				$fp = fsockopen($prefix.$host, $port, $errno, $errstr); //SSL

			//如果打开了socket, 则发送请求数据，读取响应结果
			if ($fp) {
				fputs($fp, $header);
				while (!feof($fp)) {
					$line = fgets($fp);
					if ($content_started==false) {
						if ($line=="\r\n") $content_started=true;
					} else {
						$result .= $line;
					}
				}
				fclose($fp);
			}
			return $result;
		}
	}
	
	/**
	 * 以HTTP POST方式上传文件, 返回响应结果
	 * @param string $url URL
	 * @param string $filename 文件名
	 * @param string $post_info_array (可缺省)添加在POST数据中的附加数据Array, 默认为null
	 * 
	 * @return 返回HTTP响应结果
	 */
	protected function postFile($url, $filename, $post_info_array = null) {
		//检查有否CURL库
		if (!function_exists ('curl_init'))
			return $this->error('PHP未安装CURL库模块，无法上传文件', ERROR_HTTP);
			
		//获得真实文件名
		$real_filename = $this->getPhysicalPath($filename);
		if (empty($real_filename)) 
			return $this->error('找不到要上传的文件', ERROR_FILE_NOT_EXIST);
		
		//构造上传数据
		if(version_compare(phpversion(),'5.5.0') >= 0 && class_exists('CURLFile')){
			//对于PHP5.5以上版本,使用CURLFile对象上传文件
			$data = array('media' => new CURLFile($real_filename) );
		} else {
			//PHP 5.5以下版本不支持CURLFile, 采用 @ 形式
			$data = array('media' => '@' . $real_filename);	
		}
		
		//如果有附加数据
		if (is_array($post_info_array))
			$data = array_merge($data, $post_info_array);
			
		//向URL POST 数据
		return $this->postUrl($url, $data);
		
	}
	
	/**
	 * 分析处理 JSON格式 的 HTTP 响应结果, 获取返回值
	 * @param string $response
	 * @param string $returnType (可缺省)返回值类型，默认值为null
	 * @return mixed 失败返回false, 
	 *    成功的返回值取决于 $returnType
	 *    当 $returnType = null, 需要$response中的errcode=0，才表示成功，返回值为true
	 *    当 $returnType = 'array', 返回$response整个数组
	 *    当 $returnType 是一个字符串时, 返回$response数组中名为$returnType的元素的值
	 *    
	 */
	protected function processJsonResponse($response, $returnType = null ) {
		//分析HTTP 响应结果
		if ( $this->isJson($response) ) {
			//HTTP响应body为JSON
			$arr = json_decode($response, true);
			//如果结果中有errcode
			if ( isset($arr['errcode']) ) {
				if ( $arr['errcode'] == 0 )
					return true;
		
				$this->errorCode = $arr['errcode'];
				$this->errorMessage = $arr['errmsg'];
				return false;
			} else {
				if ( $returnType === null )
					return $this->error('平台返回结果数据错误', ERROR_HTTP);
				else if ( $returnType === 'array' )
					return $arr;
				else if ( is_string($returnType) ) {
					if ( isset($arr[$returnType]) )
						return $arr[$returnType];
					else 
						return $this->error('平台返回结果数据中不包含 '.$returnType, ERROR_HTTP);
				}
					
			}
		} else {
			return $this->error('平台访问错误', ERROR_HTTP);
		}
	}
	
	//-------------------以下这一段是字符串处理函数---------------------
	
	/**
	 * $content是用户输入的文字，为防止用户输入错误，需进行一定处理，纠正字符串中的文字错误．<br>
	 * 处理方法：删除两端空格, 删除末尾多余符号, 将全角字符转为半角
	 * @param string $content 字符串(用户输入的文字)
	 * @param string $halfCornerChars (可缺省)半角字符串，该字串中的每个字符对应的全角字符均将被转化为半角字符
	 * @return string 返回纠正后的字符串
	 */
	public function correctInput($content, $halfCornerChars=',? ') {
		$content = trim($content); //将用户输入去掉两端空格
		if (substr($content, strlen($content)-strlen("。"),strlen("。"))=="。") //如果结尾是全角句号，则删除它
			$content = substr($content, 0, strlen($content)-strlen("。"));
			if (substr($content, strlen($content)-strlen("，"),strlen("，"))=="，") //如果结尾是全角逗号，则删除它
				$content = substr($content, 0, strlen($content)-strlen("，"));
				$content = trim($content);
	
				//字符替换表
				$replaceWords = array(
						','=>'，',
						' '=>'　',
						'?'=>'？',
						'.'=>'。',
						'0'=>'０',
						'1'=>'１',
						'2'=>'２',
						'3'=>'３',
						'4'=>'４',
						'5'=>'５',
						'6'=>'６',
						'7'=>'７',
						'8'=>'８',
						'9'=>'９',
						';'=>'；',
						'+'=>'＋',
						'-'=>'－',
						'*'=>'＊',
						'/'=>'／'
				);
	
				//字符替换:将$halfCornerChars指定字符的全角转为半角
				for($i=0; $i<strlen($halfCornerChars); $i++) {
					$c = $halfCornerChars[$i];
					if (isset($replaceWords[$c]))
						$content = str_replace($replaceWords[$c] , $c  , $content);
				}
	
				return $content;
	}
	
	/**
	 * 将字符串$text按分隔符$delimiters切分为数组 （$delimiters中每个字符均可做为分隔符）
	 * <br>如果空格是分隔符，则分隔符后的空格将被忽略
	 * @param string $delimiters 分隔符，其中每个字符均可做为分隔符
	 * @param string $text 字符串
	 * @param string $trimmed (可缺省)每个数组元素是否删除两端空格,默认值为true
	 * @return array 返回一个数组
	 */
	public function explode($delimiters, $text, $trimmed=false) {
		$array = array(); //作为返回值的数组
	
		//如果字符串为空，直接将其作为数组的元素返回
		if (empty($text)) {
			$array[] = $text;
			return $array;
		}
	
		//将$delimiter中每个字符拆分出来，形成一个数组
		$delims = array();
		for($i=0; $i<strlen($delimiters); $i++) {
			$c = $delimiters[$i];
			$delims[$c] = $c;
		}
		$spaceIsDelim = isset($delims[' ']); //判断空格符是不是分隔符
	
		$last_index = 0; //上一个拆分点的位置
		$last_space = false; //上一个字符是否空格
		//在$text中逐个分析字符，查找分隔符，并切分成数组元素
		for ($i=0; $i<strlen($text); $i++) {
			$isDelimiter = false; //是否分隔符
				
			if (isset($delims[$text[$i]])) { //如果当前字符是分隔符
				if ($text[$i]==' ') { //如果分隔符是空格
					if ($last_index==$i) { //如果前一个字符是上一个分隔符
						$last_index=$i+1; //忽略这个空格
					} else
						$isDelimiter = true;
				} else {
					if (($last_index==$i) && $spaceIsDelim && $last_space) { //如果前一个字符是上一个分隔符，且为空格分隔符
						$last_index=$i+1; //忽略这个分隔符
					} else
						$isDelimiter = true;
				}
			}
				
			//如果碰到一个分隔符，则将它切分下来，成为一个数组元素
			if ($isDelimiter) {
				$item = substr($text, $last_index, $i-$last_index);
				if ($trimmed===true) $item = trim($item);
				$array[] = $item;
				$last_index = $i+1;
				if ($text[$i]==' ') $last_space=true; else $last_space=false;
			}
	
			if ($text[$i]!=' ') $last_space=false;
		}
	
	
		//处理最后一个元素
		if ($last_index<strlen($text)) {
			$item = trim(substr($text, $last_index, strlen($text)-$last_index));
			if ($trimmed===true) $item = trim($item);
			$array[] = $item;
		}
	
		//返回结果数组
		return $array;
	}
	
	/**
	 * 从Utf-8字符串$string的起始位置fromIndex处中取出一个字(包含中英文)<br>
	 *
	 * UTF-8编码的字符可能由1~3个字节组成， 具体数目可以由第一个字节判断出来。
	 * 第一个字节大于224的，它与它之后的2个字节一起组成一个UTF-8字符
	 * 第一个字节大于192小于224的，它与它之后的1个字节组成一个UTF-8字符
	 * 否则第一个字节本身就是一个英文字符（包括数字和一小部分标点符号）。
	 *
	 * @param string $string 字符串(Utf-8编码)
	 * @param integer $fromIndex 起始位置,取字后$fromIndex位置指向该字后
	 * @return integer 返回一个字, 返回false表示错误
	 */
	public function getWord($string, &$fromIndex) {
		if ($fromIndex>=strlen($string)) return false;
		$temp_str=substr($string,$fromIndex,1);
		$ascnum=Ord($temp_str);//得到字符串中第$fromIndex位字符的ascii码
		if ($ascnum>=224)	{ //如果ASCII位高于224，则后续3个字符计为单个字符
			$fromIndex +=3;
			return substr($string,$fromIndex-3,3);
		} else if ($ascnum>=192) { //如果ASCII位高于192，则后续2个字符计为单个字符
			$fromIndex +=2;
			return substr($string,$fromIndex-2,2);
		} else { //否则为一个字节的ASCII码
			$fromIndex +=1;
			return substr($string,$fromIndex-1,1);
		}
		return false;
	}
	
	/**
	 * 字符串$str是否json格式
	 * @param string $str 字符串
	 * @return boolean
	 */
	protected function isJson($str) {
		if ( is_string($str) && !empty($str) ) {
			$len = strlen($str);
			$start = 0;
			//skip leading blanks
			while( $start < $len && ($str[$start] == ' ' || $str[$start] == '\t') )
				$start++;
			//compare first char
			if ( substr($str,$start,1)!=='{' ) return false;
			
			$start = $len -1;
			//skip last blanks
			while( $start >= 0 && ($str[$start] == ' ' || $str[$start] == '\t') )
				$start--;
			//compare last char
			if ( $start >= 0 && substr($str,$start,1)!=='}' ) return false;
			
			return true;
		}
		return false;
	}
	
	/**
	 * 字符串$str是否XML格式
	 * @param string $str 字符串
	 * @return boolean
	 */
	protected function isXml($str) {
		if ( is_string($str) && !empty($str) ) {
			$len = strlen($str);
			$start = 0;
			//skip blanks
			while( $start < $len && ($str[$start] == ' ' || $str[$start] == '\t') )
				$start++;
			//compare leading 6 bytes
			if ( strcasecmp(substr($str,$start,4), '<xml') !== 0 ) return false;
			
			$start = $len -1;
			while( $start >= 0 && ($str[$start] == ' ' || $str[$start] == '\t') )
				$start--;
			//compare last 6 bytes
			if ( $start >= 6 && substr($str,$start-5,6)!=='</xml>' ) return false;
			
			return true;
		}
		return false;
	}
	
	/**
	 * 字符串$str是否URL
	 * @param string $str 字符串
	 * @return boolean 是URL返回true, 不是返回false
	 */
	protected function isUrl($str) {
		if ( is_string($str) && !empty($str) ) {
			$len = strlen($str);
			$start = 0;
			//skip blanks
			while( $start < $len && ($str[$start] == ' ' || $str[$start] == '\t') )
				$start++;
			//compare leading bytes
			if ( strcasecmp(substr($str,$start,4), 'http') !== 0 ) return false;
			if ( strcasecmp(substr($str,$start,7), 'http://') === 0 ) return true;
			if ( strcasecmp(substr($str,$start,8), 'https://') === 0 ) return true;
		}
		return false;
	}
	
	/**
	 * 当前请求是否https
	 */
	private function isHttps(){  
	    if(!isset($_SERVER['HTTPS'])) return false;  
	    if($_SERVER['HTTPS'] === 1){  //Apache  
	        return TRUE;  
	    }elseif($_SERVER['HTTPS'] === 'on'){ //IIS  
	        return TRUE;  
	    }elseif($_SERVER['SERVER_PORT'] == 443){ //其他  
	        return TRUE;  
	    }  
	    return FALSE;  
	}  
	
	/**
	 * 返回当前URL根目录的URL
	 */
	function currentUrlRoot() {
		if ($_SERVER["SERVER_PORT"] != 80 )
			$url = $_SERVER['SERVER_NAME'].':'.$_SERVER["SERVER_PORT"];
		else
			$url = $_SERVER['SERVER_NAME'];
		$url = $this->isHttps() ? 'https://'.$url : 'http://'.$url; 	
		return $url;
	}
	
	/**
	 * 返回当前URL
	 */
	private function currentUrl() {
		return $this->currentUrlRoot().$_SERVER["REQUEST_URI"];
	}
	
	/**
	 * 返回当前URL所在目录的URL
	 */
	private function currentUrlPath() {
		return dirname($this->currentUrl());
	}
	
	/**
	 * 生成一个有效的全称URL(如, http:://www.host.com/dir/filename)
	 * 如果URL是一个路径,则生成一个有效的URL
	 * @param string $url
	 * @return string 生成一个有效的URL.
	 */
	private function validateUrl($url) {
		if (!$this->isUrl($url) ) {
			if ( substr($url, 0, 1) == '/' )
				$url = $this->currentUrlRoot().$url;
			else {
				$base = $this->currentUrlPath();
				while( substr($url, 0, 3)=='../' ) {
					$base = dirname($base);
					$url = substr($url, 3);
				}
				$url = $base . '/' . $url;
			}	
		}
		return $url;
	}
	
	/**
	 * 字符串$str是否哪个运营商的手机号码
	 * @param string $str 字符串
	 * @return string|false 如果不是有效手机号码，返回false. 如果是，返回号码归属的运营商名称(中国电信，中国移动，中国联通)
	 */
	public function whatMobilePhone($str) {
		if (strlen($str)!=11) return false; //如果不是11位
		if (!is_numeric($str)) return false; //如果不是数字
		if (strpos($str, ".")) return false; //如果中间有小数点
	
		//取号码前两位,判断是否是手机号段
		$prefix = substr($str, 0, 2);
		$all = array("13","15","17","18");
		if (!in_array($prefix, $all)) return false;
	
		//各运营商号段表
		$cmcc = array("134","135","136","137","138","139","150","151","152","157","158","159",
				"182","183","184","187","188","147");
		$ct = array("133","153","180","189","1349","177","1700");
		$unicom = array("130","131","132","152","155","156","185","186","176");
	
		$prefix = substr($str, 0, 4);//取号码前4位
		if (in_array($prefix, $cmcc)) return '中国移动';
		if (in_array($prefix, $ct)) return '中国电信';
		if (in_array($prefix, $unicom)) return '中国联通';
	
		$prefix = substr($str, 0, 3);//取号码前3位
		if (in_array($prefix, $cmcc)) return '中国移动';
		if (in_array($prefix, $ct)) return '中国电信';
		if (in_array($prefix, $unicom)) return '中国联通';
	
		return "未知运营商";
	}
	
	/**
	 * 根据openId判断是否是微信用户．
	 * 此函数根据微信平台的id号特征进行预计，结果不一定非常准确．
	 * @param string $openId (可缺省)如果该值缺省，则取当前访问用户的openId
	 * @return bool 如果是微信用户，则返回true．否则返回false.
	 */
	public function isWeChatUser($openId=null) {
		if ( empty($openId) ) $openId = $this->fromUser;
		//对openId逐个字符进行分析，如果有非16进制字符，则为微信用户
		for ($i=0; $i < strlen($openId); $i++) {
			$s = substr($openId, $i, 1);
			$isHex = false;
			if ( ($s>='a') && ($s<='f') ) $isHex = true;
			if ( ($s>='A') && ($s<='F') ) $isHex = true;
			if ( ($s>='0') && ($s<='9') ) $isHex = true;
			if ( !$isHex ) return true;
		}
		return false;
	}
	
	/**
	 * 判断当前网页是否在微信浏览器中打开
	 * 
	 * @return bool 如果是微信浏览器，则返回true．否则返回false.
	 */
	public function isWeChatBrowser() {
		if ( isset($_SERVER['HTTP_USER_AGENT']) ) {
			$user_agent = strtolower( $_SERVER['HTTP_USER_AGENT'] );
			if ( strpos($user_agent, "micromessenger") !== false )
				return true;
		}
		
		return false;
	}
	

}

?>