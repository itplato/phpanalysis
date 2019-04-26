<?php
/*
 * 居于Unicode编码词典的php分词器
 *  1、只适用于php5以上，必要函数iconv或substring其中一种
 *  2、本程序是使用RMM逆向匹配算法进行分词的，词库需要特别编译，本类里提供了 MakeDict() 方法
 *  3、简单操作流程： SetSource -> StartAnalysis -> Get***Result
 *  4、对主词典使用特殊格式进行编码, 不需要载入词典到内存操作
 *
 * Copyright IT柏拉图  QQ: 2500875 Email: 2500875#qq.com
 *
 * @version 3.0
 *
 */

//常量定义
define('_SP_', chr(0xFF).chr(0xFE)); 
define('UCS2', 'ucs-2be');
class PhpAnalysis
{
    
    //hash算法选项
    public $mask_value = 0xEFFF;
    
    //输入和输出的字符编码（只允许 utf-8、gbk/gb2312/gb18030、big5 三种类型）  
    public $sourceCharSet = 'utf-8';
    public $targetCharSet = 'utf-8';
    
    //句子长度小于这个数值时不拆分，notSplitLen = n(个汉字) * 2 + 1
    public $notSplitLen = 5;
    
    //把英文单词全部转小写
    public $toLower = FALSE;
    
    //生成的分词结果数据类型 1 为全部， 2为 词典词汇、个字符或英语单词、(@#+-)这些可能有意义的特殊符号  3 仅词典词汇（SEO提词专用）
    public $resultType = 1;
    
    //使用最大切分模式对二元词进行消岐
    public $differMax = FALSE;
    
    //尝试合并单字
    public $unitWord = TRUE;
    
    //使用热门词优先模式进行消岐
    public $differFreq = FALSE;
    
    //对分词后的结果尝试优化
    public $optimizeResult = TRUE;
    
    //初始化类时直接加载词典(此选项本版本中无多大意义)
    public static $loadInit = TRUE;
    
    //被转换为unicode的源字符串
    private $sourceString = '';
    
    //附加词典
    public $addonDic = array();
    public $addonDicFile = 'dict/words_addons.dic';
    
    //半角与全角ASCII对照表
    public $sbcArr = array();
    
    //主词典 
    public $dicStr = '';
    public $mainDic = array();
    public $mainDicHand = FALSE;
    public $mainDicInfos = array();
    public $mainDicFile = 'dict/base_dic_full.dic';
    
    //主词典词语最大长度 x / 2
    private $dicWordMax = 16;
    
    //粗分后的数组（通常是截取句子等用途）
    private $simpleResult = array();
    
    //最终结果(用空格分开的词汇列表)
    private $finallyResult = '';
    
    //标注了属性后的结果(仅适用当次分词)
    private $propertyResult = "";
    
    //是否已经载入词典
    public $isLoadDic = FALSE;
    
    //系统识别或合并的新词
    public $newWords = array();
    
    //英语高频词
    public $enBadWordFile = 'dict/english-bad-words.txt';
    public $enBadWords = array();
    
    //最热门的前1000个汉字(尝试单词合并时，排除过于冷门的字，可以防止乱码的情况)
    public $charDicFile = 'dict/char_rank.txt';
    public $hotChars = array();
    
    //权重因子
    private $rankStep = 100;

    //词库载入时间
    public $loadTime = 0;
    
    //对照符号(快速分词时会以这些符号作为粗分依据)
    public $symbolCompares = array(
                ['“', '”', '‘', '’', '，', '！', '。', '；', '？', '：', '《', '》', '——', '（', '）', '【', '】', '、'],
                ['"', '"', '\'', '\'', ',', '!', '.', ',', '?', ':', '<', '>', '--', '(', ')', '[', ']', '.'],
           );
    
    //处理英语或英语和数字混合的东西 
    private $ansiWordMatch = "[0-9a-z@#%\+\._-]";
    private $notNumberMatch = "[a-z@#%_\+]";
    
    //不适合太高权重的词
    private $lowRankWords = array('mq', 'd', 'p', 'r', 'vk', 'nk');
           
    //SEO提词模式
    public $seoModel = FALSE;
    
    /**
     * 构造函数
     * @param $source_charset
     * @param $target_charset
     * @param $load_dict  是否马上加载词典(在进行非分词工作或要使用非默认房词典时，这个值可以设置为FALSE，然后通过LoadDict加载词典)
     * @param $source
     *
     * @return void
     */
    public function __construct($source_charset='utf-8', $target_charset='utf-8', $load_dict=TRUE, $source='')
    {
        $this->SetSource( $source, $source_charset, $target_charset );
        $this->mainDicFile = dirname(__FILE__).'/'.$this->mainDicFile;
        $this->addonDicFile = dirname(__FILE__).'/'.$this->addonDicFile;
        //self::$loadInit  这个值默认是TRUE，这里仍然使用这个值仅是为了兼容之前版本
        if( self::$loadInit && $load_dict ) $this->LoadDict();
    }
    
   /**
    * 析构函数
    */
    function __destruct()
    {
        if( $this->mainDicHand !== FALSE )
        {
            @fclose( $this->mainDicHand );
        }
    }
    
   /**
    * 用substring代替iconv
    * @return string    
    */
    private function _iconv($in, $out, $str)
    {
        //优先使用mb_string
        if( function_exists('mb_convert_encoding') ) {
            return mb_convert_encoding($str, $out, $in);
        }
        else if( function_exists('iconv') ) {
            return iconv($in, $out, $str);
        }
        else {
            throw new Exception("iconv or mb_substring not install!");
            return '';
        }
    }
    
    /**
     * 根据字符串计算key索引
     * @param $key
     * @return short int
     */
    private function _get_index( $key )
    {
        $l = strlen($key);
        $h = 0x238f13af;
        while ($l--)
        {
            $h += ($h << 5);
            $h ^= ord($key[$l]);
            $h &= 0x7fffffff;
        }
        return ($h % $this->mask_value);
    }
    
    /**
     * 从文件获得词
     * @param $key
     * @param $type (类型 word 或  key_groups)
     * @return short int
     */
    public function GetWordInfos( $key, $type='word' )
    {
        
        //自动识别的词单独处理
        if( isset($this->newWords[$key]) && $type='word' )
        {
            return $this->newWords[$key];
        }
        
        if( !$this->mainDicHand )
        {
            $this->mainDicHand = fopen($this->mainDicFile, 'r') or die("GetWordInfos Load Dict:{$this->mainDicFile} error!");
        }
        $keynum = $this->_get_index( $key );
        if( isset($this->mainDicInfos[ $keynum ]) )
        {
            $data = $this->mainDicInfos[ $keynum ];
        }
        else
        {
            //rewind( $this->mainDicHand );
            $move_pos = $keynum * 8;
            fseek($this->mainDicHand, $move_pos, SEEK_SET);
            $dat = fread($this->mainDicHand, 8);
            $arr = unpack('I1s/n1l/n1c', $dat);
            if( $arr['l'] == 0 )
            {
                return FALSE;
            }
            fseek($this->mainDicHand, $arr['s'], SEEK_SET);
            $data = @unserialize(fread($this->mainDicHand, $arr['l']));
            $this->mainDicInfos[ $keynum ] = $data;
       }
       if( !is_array($data) || !isset($data[$key]) ) 
       {
           return FALSE;
       }

       return ($type=='word' ? $data[$key] : $data);
    }
    
    /**
     * 设置源字符串
     * @param $source
     * @param $source_charset
     * @param $target_charset
     *
     * @return bool
     */
    public function SetSource( $source, $source_charset='utf-8', $target_charset='utf-8' )
    {
        $this->sourceCharSet = strtolower($source_charset);
        $this->targetCharSet = strtolower($target_charset);
        $this->simpleResult = array();
        $this->finallyResult = array();
        $this->finallyIndex = array();
        $this->propertyResult = array();
        $this->newWords = array();
        if( $source != '' )
        {
            $rs = TRUE;
            if( preg_match("/^utf/", $source_charset) ) {
                $this->sourceString = $this->_iconv('utf-8', UCS2, $source);
            }
            else if( preg_match("/^gb/", $source_charset) ) {
                $this->sourceString = $this->_iconv('utf-8', UCS2, $this->_iconv('gb18030', 'utf-8', $source));
            }
            else if( preg_match("/^big/", $source_charset) ) {
                $this->sourceString = $this->_iconv('utf-8', UCS2, $this->_iconv('big5', 'utf-8', $source));
            }
            else {
                $rs = FALSE;
            }
        }
        else
        {
           $rs = FALSE;
        }
        return $rs;
    }
    
    /**
     * 设置源字符串(快速粗分模式，不遍历源字符串，直接用正则对源字符串进行分割)
     * @param $source
     * @param $source_charset
     * @param $target_charset
     * @return bool
     */
    private function _quick_set_source( $source, $source_charset='utf-8', $target_charset='utf-8' )
    {
        $this->sourceCharSet = strtolower($source_charset);
        $this->targetCharSet = strtolower($target_charset);
        $this->simpleResult = array();
        $this->finallyResult = array();
        $this->finallyIndex = array();
        $this->newWords = array();
        if( $source != '' )
        {
            $rs = TRUE;
            if( preg_match("/^gb/", $source_charset) ) {
                $this->sourceString = $this->_iconv('gb18030', 'utf-8', $source);
            }
            else if( preg_match("/^big/", $source_charset) ) {
                $this->sourceString = $this->_iconv('big5', 'utf-8', $source);
            }
            else if( preg_match("/^utf/", $source_charset) ) {
                $this->sourceString = $source;
            } else {
                $rs = FALSE;
                throw new Exception("source charset error!");
            }
        }
        else
        {
           $rs = FALSE;
           throw new Exception("source can't empty!");
        }
        return $rs;
    }
    
    /**
     * 设置结果类型(只在获取finallyResult才有效)
     * @param $rstype 1 为全部， 2去除特殊符号  3 SEO提词器（仅导出词典内的词条）
     *
     * @return void
     */
    public function SetResultType( $rstype )
    {
        $this->resultType = $rstype;
    }
    
    /**
     * 载入词典
     *
     * @return void
     */
    public function LoadDict( $maindic='' )
    {
        $startt = microtime(TRUE);

        //手动指定词典（构造函数第三个参数不等于-1或没设置PhpAnalysis::$loadInit=FALSE的情况下会打开默认词库，但重复这个操作并不会占资源，因为它仅打开文件）
        if( $maindic != '' )
        {
            if( file_exists($maindic) ) {
                $this->mainDicFile = $maindic;
            } else {
                throw new Exception("New Dictionary file not exists");
            }
        } 
        //使用默认词典只加载一次
        else if( $this->isLoadDic ){
            return;
        }
        
        //加载主词典（只打开）
        if( $this->isLoadDic )  @fclose( $this->mainDicHand );
        $this->mainDicHand = fopen($this->mainDicFile, 'r') or die("Load dicfile: {$this->mainDicFile} error!");
        
        //载入副词典
        if( !$this->isLoadDic )
        {
            $hw = '';
            $ds = file( $this->addonDicFile );
            foreach($ds as $d)
            {
                $d = trim($d);
                if($d=='') continue;
                $estr = substr($d, 1, 1);
                if( $estr==':' ) {
                    $hw = substr($d, 0, 1);
                }
                else
                {
                    $spstr = _SP_;
                    $spstr = $this->_iconv(UCS2, 'utf-8', $spstr);
                    $ws = explode(',', $d);
                    $wall = $this->_iconv('utf-8', UCS2, join($spstr, $ws));
                    $ws = explode(_SP_, $wall);
                    foreach($ws as $estr)
                    {
                        $this->addonDic[$hw][$estr] = strlen($estr);
                    }
                }
            }
            
            //全角与半角字符对照表
            $j = 0;
            for($i=0xFF00; $i < 0xFF5F; $i++)
            {
                $scb = 0x20 + $j;
                $j++;
                $this->sbcArr[$i] = $scb;
            }
            
            //热门汉字
            $n = 1;
            $fp = fopen( dirname(__FILE__).'/'.$this->charDicFile , 'r');
            while($n < 1000)
            {
                $line = rtrim(fgets($fp, 64));
                list($c, $r) = explode(' ', $line);
                $this->hotChars[$c] = sprintf('%0.2f', log($r) - 10);
                $n++;
            }
            //print_r($this->hotChars); exit();
            fclose($fp);
            
            //英语坏词条(字典的英语词条应该都为小写)
            $fp = fopen( dirname(__FILE__).'/'.$this->enBadWordFile , 'r');
            fgets($fp, 1024); //跳过第一行注解
            $str = fgets($fp, 10240);
            $this->enBadWords = explode(' ', $str);
            fclose($fp);
            
            
        }//load addDic
        
        $this->loadTime = microtime(TRUE) - $startt;
        $this->isLoadDic = TRUE;
        
    }
    
   /**
    * 检测某个词是否存在
    */
    public function IsWord( $word )
    {
         $winfos = $this->GetWordInfos( $word );
         return ($winfos !== FALSE);
    }
    
    /**
     * 获得某个词的词性及词频信息
     * @parem $word unicode编码的词
     * @return void
     */
     public function GetWordProperty($word)
     {
        $this->CheckWordProperty();
        return isset( $this->propertyResult[$word] ) ? $this->propertyResult[$word] : array($this->rankStep*10, 'xx');
     }
    
    /**
     * 指定某词的词性信息（通常是新词）
     * 由于大批量更新语料库的情况下，把新词添加到主词典，可能会导致词典词条占用内存不可控，因此不更新mainDicInfos
     * @parem $word unicode编码的词
     * @parem $infos array(词频, 词性);
     * @return void;
     */
    public function SetWordInfos($word, $infos)
    {
        if( strlen($word) < 4 )
        {
            return ;
        }
        //$cn = $this->_out_string_encoding( $word ); $_isdebug = FALSE;
        if( !isset($this->newWords[$word]) )
        {
            $dinfo = $this->GetWordInfos( $word );
            if( $dinfo !== FALSE ) {
                $this->newWords[$word] = $dinfo;
            }
            else {
                $this->newWords[$word] = $infos;
            }
            //echo "<xmp>".$cn, var_export($this->newWords[$word], TRUE), "\n</xmp>";
        }
    }
    
    /**
     * 设置分词选项
     * @param $differMax = FALSE 使用最大切分模式对二元词进行消岐
     * @param $unitWord = TRUE 尝试合并单字
     * @param $optimizeResult = TRUE;  是否对分词结果进行二次优化
     * @param $differFreq = FALSE; 使用热门词优先模式进行消岐
     * @return bool
    */
    public function SetOptimizeParams( $differMax = FALSE, $unitWord = TRUE, $optimizeResult = TRUE, $differFreq = FALSE )
    {
        $this->differMax = $differMax;
        $this->unitWord = $unitWord;
        $this->differFreq = $differFreq;
        $this->optimizeResult = $optimizeResult;
    }
    
    /**
     * 开始执行分析
     * @parem bool optimize 是否对结果进行优化(这个变量已经不用，由 $this->optimizeResult 替代, 建议在 SetOptimizeParams 控制)
     * @return bool
     */
    public function StartAnalysis( $optimize = TRUE )
    {
        //兼容处理
        if( !$optimize )
        {
            $this->optimizeResult = $optimize;
        }
        if( !$this->isLoadDic )
        {
            $this->LoadDict();
        }
        $this->simpleResult = $this->finallyResult = array();
        $this->sourceString .= chr(0).chr(0x20);
        $slen = strlen($this->sourceString);
        
        //对字符串进行粗分
        $onstr = '';
        $lastc = 1; //1 中/韩/日文, 2 英文/数字/符号('.', '@', '#', '+'), 3 ANSI符号 4 纯数字 5 非ANSI符号或不支持字符
        $s = 0;
        $ansiWordMatch = $this->ansiWordMatch;
        $notNumberMatch = $this->notNumberMatch;
        for($i=0; $i < $slen; $i++)
        {
            $c = $this->sourceString[$i].$this->sourceString[++$i];
            $cn = hexdec(bin2hex($c));
            $cn = isset($this->sbcArr[$cn]) ? $this->sbcArr[$cn] : $cn;
            //ANSI字符
            if($cn < 0x80)
            {
                if( preg_match('/'.$ansiWordMatch.'/i', chr($cn)) )
                {
                    if( $lastc != 2 && $onstr != '') {
                        $this->simpleResult[$s]['w'] = $onstr;
                        $this->simpleResult[$s]['t'] = $lastc;
                        $this->_deep_analysis($onstr, $lastc, $s, $this->optimizeResult);
                        $s++;
                        $onstr = '';
                    }
                    $lastc = 2;
                    $onstr .= chr(0).chr($cn);
                }
                else
                {
                    if( $onstr != '' )
                    {
                        $this->simpleResult[$s]['w'] = $onstr;
                        if( $lastc==2 )
                        {
                            if( !preg_match('/'.$notNumberMatch.'/i', $this->_iconv(UCS2, 'utf-8', $onstr)) ) $lastc = 4;
                        }
                        $this->simpleResult[$s]['t'] = $lastc;
                        if( $lastc != 4 ) $this->_deep_analysis($onstr, $lastc, $s, $this->optimizeResult);
                        $s++;
                    }
                    $onstr = '';
                    $lastc = 3;
                    if($cn < 31)
                    {
                        continue;
                    }
                    else
                    {
                        $this->simpleResult[$s]['w'] = chr(0).chr($cn);
                        $this->simpleResult[$s]['t'] = 3;
                        $s++;
                    }
                }
            }
            //普通字符
            else
            {
                //正常文字
                if( ($cn>0x3FFF && $cn < 0x9FA6) || ($cn>0xF8FF && $cn < 0xFA2D)
                    || ($cn>0xABFF && $cn < 0xD7A4) || ($cn>0x3040 && $cn < 0x312B) )
                {
                    if( $lastc != 1 && $onstr != '')
                    {
                        $this->simpleResult[$s]['w'] = $onstr;
                        if( $lastc==2 )
                        {
                            if( !preg_match('/'.$notNumberMatch.'/i', $this->_iconv(UCS2, 'utf-8', $onstr)) ) $lastc = 4;
                        }
                        $this->simpleResult[$s]['t'] = $lastc;
                        if( $lastc != 4 ) $this->_deep_analysis($onstr, $lastc, $s, $this->optimizeResult);
                        $s++;
                        $onstr = '';
                    }
                    $lastc = 1;
                    $onstr .= $c;
                }
                //特殊符号
                else
                {
                    if( $onstr != '' )
                    {
                        $this->simpleResult[$s]['w'] = $onstr;
                        if( $lastc==2 )
                        {
                            if( !preg_match('/'.$notNumberMatch.'/i', $this->_iconv(UCS2, 'utf-8', $onstr)) ) $lastc = 4;
                        }
                        $this->simpleResult[$s]['t'] = $lastc;
                        if( $lastc != 4 ) $this->_deep_analysis($onstr, $lastc, $s, $this->optimizeResult);
                        $s++;
                    }
                    
                    //检测书名
                    if( $cn == 0x300A )
                    {
                        $tmpw = '';
                        $n = 1;
                        $isok = FALSE;
                        $ew = chr(0x30).chr(0x0B);
                        while(TRUE)
                        {
                            if( !isset($this->sourceString[$i+$n+1]) )  break;
                            $w = $this->sourceString[$i+$n].$this->sourceString[$i+$n+1];
                            if( $w == $ew )
                            {
                                $this->simpleResult[$s]['w'] = $c;
                                $this->simpleResult[$s]['t'] = 5;
                                $s++;
                        
                                $this->simpleResult[$s]['w'] = $tmpw;
                                $this->newWords[$tmpw] = 1;
                                if( !isset($this->newWords[$tmpw]) )
                                {
                                    $this->SetWordInfos($tmpw, array($this->rankStep, 'nb'));
                                }
                                $this->simpleResult[$s]['t'] = 13;
                                
                                $s++;

                                //最大切分模式对书名继续分词
                                if( $this->differMax )
                                {
                                    $this->simpleResult[$s]['w'] = $tmpw;
                                    $this->simpleResult[$s]['t'] = 21;
                                    $this->_deep_analysis($tmpw, $lastc, $s, $this->optimizeResult);
                                    $s++;
                                }
                                
                                $this->simpleResult[$s]['w'] = $ew;
                                $this->simpleResult[$s]['t'] =  5;
                                $s++;
                        
                                $i = $i + $n + 1;
                                $isok = TRUE;
                                $onstr = '';
                                $lastc = 5;
                                break;
                            }
                            else
                            {
                                $n = $n+2;
                                $tmpw .= $w;
                                //书名超过10个中文，不作为词条使用
                                if( strlen($tmpw) > 30 )
                                {
                                    break;
                                }
                            }
                        }//while
                        if( !$isok )
                        {
                              $this->simpleResult[$s]['w'] = $c;
                              $this->simpleResult[$s]['t'] = 5;
                              $s++;
                              $onstr = '';
                              $lastc = 5;
                        }
                        continue;
                    }
                    
                    $onstr = '';
                    $lastc = 5;
                    if( $cn==0x3000 )
                    {
                        continue;
                    }
                    else
                    {
                        $this->simpleResult[$s]['w'] = $c;
                        $this->simpleResult[$s]['t'] = 5;
                        $s++;
                    }
                }//2byte symbol
                
            }//end 2byte char
        
        }//end for
        
        //处理分词后的结果
        $this->_sort_finally_result();
    }
    
    /**
     * 开始执行分析(只检测词典词条)
     * @parem bool optimize 是否对结果进行优化(如果存在中英文混合词条，有可能会用到这个选项)
     * @return bool
     */
    public function StartSeoAnalysis( $sortby="count", $optimize = TRUE )
    {
        if( !$this->isLoadDic )
        {
            $this->LoadDict();
        }
        $this->simpleResult = $this->finallyResult = array();
        $this->sourceString .= chr(0).chr(0x20);
        $slen = strlen($this->sourceString);
        $str = $this->sourceString;
        $ansiWordMatch = $this->ansiWordMatch;
        $tmparr = array();
        //进行切分
        $s = 0;
        for($i=$slen-1; $i > 0; $i -= 2)
        {
            //单个词
            $nc = $str[$i-1].$str[$i];
            
            //全角转半角，排除无意义符号
            $cn = hexdec(bin2hex($nc));
            $ac = isset($this->sbcArr[$cn]) ? $this->sbcArr[$cn] : FALSE;
            if( $ac !== FALSE )
            {
                if( !preg_match('/'.$ansiWordMatch.'/i', chr($ac) ) )
                {
                    continue;
                } else {
                    $str[$i-1] = chr(0);
                    $str[$i] = chr($ac);
                }
            }
            
            //是否已经到最后两个字
            if( $i <= 2 )
            {
                $tmparr[] = $nc;
                $i = 0;
                break;
            }
            $isok = FALSE;
            $i = $i + 1;
            for($k=$this->dicWordMax; $k>1; $k=$k-2)
            {
                if($i < $k) continue;
                $w = substr($str, $i-$k, $k);
                if( strlen($w) <= 2 )
                {
                    $i = $i - 1;
                    break;
                }
                if( $this->IsWord( $w ) )
                {
                    $tmparr[] = $w;
                    $i = $i - $k + 1;
                    $isok = TRUE;
                    break;
                }
            }
            //没适合词
            //if(!$isok) $tmparr[] = $nc;
        }
        $words = array();
        foreach($tmparr as $w)
        {
            $w = $this->_out_string_encoding( $w );
            if( isset($words[$w]) )  $words[$w]++;
            else $words[$w] = 1;
        }
        arsort($words);
        if( $sortby=='rank' )
        {
            foreach($words as $w => $c)
            {
                //$rearr[$w] = sprintf("%0.3f", $c * 100 / $rearr_m[$w][0]);
                $i = $this->GetWordInfos( $w );
                if( !empty($i[0]) )
                {
                    //降低一些无意义的词的权重
                    if( in_array($i[1], $this->lowRankWords) ) {
                        $words[$w] = $this->rankStep * 20;
                    }
                    $words[$w] = sprintf("%0.3f", $c * 100 / $i[0]);
                }
                else {
                    $words[$w] = $c / ($this->rankStep * 10);
                }
            }
            arsort($words);
        }
        return $words;
    }
    
    /**
     * 执行快速分析(用正则的方式进行粗分，实验性算法，实际性能区别不大)
     * (正常分词方式是先： SetSource，然后 StartAnalysis，然后 Getxxx 各种需要的结果)
     * @parem bool optimize 是否对结果进行优化
     * @return array
     */
    public function QuickAnalysis( $source, $source_charset='utf-8', $target_charset='utf-8', $optimize = FALSE )
    {
        $this->_quick_set_source( $source, $source_charset, $target_charset );
        //内容结尾追加一个空格以确保最后一行能被匹配
        $this->sourceString =  str_replace($this->symbolCompares[0], $this->symbolCompares[1], $this->sourceString).' ';
        preg_match_all("/([^\"!',\.,'\?:<>\(\)\[\]\\/\s]*)[\",'!\.,'\?:<>\(\)\[\]\\/\s]/", $this->sourceString, $rearr);
        $lastc = 1;
        $s = 0;
        foreach( $rearr[1] as $line)
        {
            if( strlen($line) > 1 )
            {
                $uline = $this->_iconv("utf-8", UCS2, $line);
                if( preg_match("/[\x{10FF}-\x{FFFF}]/u", $line ) )
                {
                    $this->simpleResult[$s]['w'] = $uline;
                    $this->simpleResult[$s]['t'] = 1;
                    $this->_deep_analysis_cn( $uline, 1, $s, strlen($uline), $optimize );
                    $lastc = 1;
                }
                else
                {
                    $this->simpleResult[$s]['w'] = $uline;
                    if( !preg_match("/[^0-9]/", $line) ) {
                        $this->simpleResult[$s]['t'] = $lastc = 4;
                    } else {
                        $this->simpleResult[$s]['t'] = $lastc = 2;
                    }
                }
                $s++;
            }
        }
        $this->_sort_finally_result();
        return $this->finallyResult;
    }
    
    /**
     * 深入分词
     * @parem $str
     * @parem $ctype (2 英文类， 3 中/韩/日文类)
     * @parem $spos   当前粗分结果游标
     * @return bool
     */
    private function _deep_analysis( &$str, $ctype, $spos, $optimize=TRUE )
    {

        //中文句子
        if( $ctype==1 )
        {
            $slen = strlen($str);
            //小于系统配置分词要求长度的句子
            if( $slen < $this->notSplitLen )
            {
                $tmpstr = '';
                $lastType = 0;
                if( $spos > 0 ) $lastType = $this->simpleResult[$spos-1]['t'];
                if($slen < 5)
                {
                      //echo $this->_iconv(UCS2, 'utf-8', $str).'<br/>';
                      //尝试合并数字和单位(附加词典的词默认已经转为unicode)
                      if( $lastType==4 && ( isset($this->addonDic['u'][$str]) || isset($this->addonDic['u'][substr($str, 0, 2)]) ) )
                      {
                              $str2 = '';
                              if( !isset($this->addonDic['u'][$str]) && isset($this->addonDic['s'][substr($str, 2, 2)]) )
                              {
                                    $str2 = substr($str, 2, 2);
                                    $str  = substr($str, 0, 2);
                              }
                              $ww = $this->simpleResult[$spos - 1]['w'].$str;
                              $this->simpleResult[$spos - 1]['w'] = $ww;
                              $this->simpleResult[$spos - 1]['t'] = 8;
                              if( !isset($this->newWords[$this->simpleResult[$spos - 1]['w']]) )
                              {
                                     $this->SetWordInfos($ww, array($this->rankStep*10, 'mu'));
                              }
                              unset($this->simpleResult[$spos]);
                              //$this->simpleResult[$spos]['w'] = '';
                              $this->finallyResult[$spos-1][] = array('w' => $ww, 't' => 8);
                              if( $str2 != '' )
                              {
                                  $this->finallyResult[$spos-1][] = array('w' => $str2, 't' => 1);
                              }
                       }
                       else {
                              $this->finallyResult[$spos][] = array('w' => $str, 't' => 1);
                       }
                }
                else
                {
                      $this->_deep_analysis_cn( $str, $ctype, $spos, $slen, $optimize );
                }
            }
            //正常长度的句子，循环进行分词处理
            else
            {
                $this->_deep_analysis_cn( $str, $ctype, $spos, $slen, $optimize );
            }
        }
        //英文句子，转为小写
        else
        {
            if( $this->toLower ) {
                $this->finallyResult[$spos][] = array('w' => strtolower($str), 't' => 2);
            }
            else {
                $this->finallyResult[$spos][] = array('w' => $str, 't' => 2);
            }
        }
    }
    
    /**
     * 中文的深入分词
     * @parem $str
     * @return void
     */
    private function _deep_analysis_cn( &$str, $lastec, $spos, $slen, $optimize=TRUE )
    {
        $quote1 = chr(0x20).chr(0x1C);
        $tmparr = array();
        $hasw = 0;
        //如果前一个词为 “ ， 并且字符串小于5个字符当成一个词处理。
        if( $spos > 0 && $slen < 9 && $this->simpleResult[$spos-1]['w']==$quote1 )
        {
            $tmparr[] = $str;
            if( !isset($this->newWords[$str]) && !$this->IsWord($str) )
            {
                $this->SetWordInfos($str, array($this->rankStep*3, 'nq'));
            }
            $this->finallyResult[$spos][] = array('w' => $str, 't' => 1);
            if( !$this->differMax ) {
                return ;
            }
        }
        //进行切分
        for($i=$slen-1; $i > 0; $i -= 2)
        {
            //单个词
            $nc = $str[$i-1].$str[$i];
            //是否已经到最后两个字
            if( $i <= 2 )
            {
                $tmparr[] = $nc;
                $i = 0;
                break;
            }
            $isok = FALSE;
            $i = $i + 1;
            for($k=$this->dicWordMax; $k>1; $k=$k-2)
            {
                if($i < $k) continue;
                $w = substr($str, $i-$k, $k);
                if( strlen($w) <= 2 )
                {
                    $i = $i - 1;
                    break;
                }
                if( $this->IsWord( $w ) )
                {
                    $tmparr[] = $w;
                    $i = $i - $k + 1;
                    $isok = TRUE;
                    break;
                }
            }
            //echo '<hr />';
            //没适合词
            if(!$isok) $tmparr[] = $nc;
        }
        $wcount = count($tmparr);
        if( $wcount==0 ) return ;
        $sparr = array_reverse($tmparr);
        foreach( $sparr as $w ) {
            $this->finallyResult[$spos][] = array('w' => $w, 't' => 1);
        }
        //优化结果(岐义处理、新词、数词、人名识别等)
        if( $optimize )
        {
            $this->_optimize_result( $this->finallyResult[$spos], $spos );
        }
    }
    
    /**
    * 对最终分词结果进行优化（把simpleresult结果合并，并尝试新词识别、数词合并等）
    * @parem $optimize 是否优化合并的结果
    * @return bool
    */
    //t = 1 中/韩/日文, 2 英文/数字/符号('.', '@', '#', '+'), 3 ANSI符号 4 纯数字 5 非ANSI符号或不支持字符
    private function _optimize_result( &$smarr, $spos )
    {
        $newarr = array();
        $prePos = $spos - 1;
        $arlen = count($smarr);
        $i = $j = 0;
        //检测数量词或中英文混合词条(给合上一句子)
        if( $prePos > -1 && !isset($this->finallyResult[$prePos])  )
        {
            $lastw = $this->simpleResult[$prePos]['w'];
            $lastt = $this->simpleResult[$prePos]['t'];
            if( ($lastt==4 || isset( $this->addonDic['c'][$lastw] )) && isset( $this->addonDic['u'][$smarr[0]['w']] ) )
            {
                 $this->simpleResult[$prePos]['w'] = $lastw.$smarr[0]['w'];
                 $this->simpleResult[$prePos]['t'] = 4;
                 if( !isset($this->newWords[ $this->simpleResult[$prePos]['w'] ]) )
                 {
                     if( strlen($this->simpleResult[$prePos]['w']) > 5 )
                        $this->SetWordInfos($this->simpleResult[$prePos]['w'], array($this->rankStep*10, 'mu'));
                     else
                        $this->SetWordInfos($this->simpleResult[$prePos]['w'], array($this->rankStep*10, 'mu'));
                 }
                 //$smarr[0] = '';
                 unset($smarr[0]);
                 $i++;
            }
       }
       for(; $i < $arlen; $i++)
       {
            
            if( !isset( $smarr[$i+1] ) )
            {
                $newarr[$j] = $smarr[$i];
                break;
            }
            $cw = $smarr[$i]['w'];
            $nw = $smarr[$i+1]['w'];
            $ischeck = FALSE;
            //检测数量词
            if( isset( $this->addonDic['c'][$cw] ) && isset( $this->addonDic['u'][$nw] ) )
            {
                //最大切分时保留合并前的词
                $newarr[$j]['w'] = $cw.$nw;
                $newarr[$j]['t'] = 8;
                if( !isset($this->newWords[$newarr[$j]['w']]) && !$this->IsWord($newarr[$j]['w']) )
                {
                    $this->SetWordInfos($newarr[$j]['w'], array($this->rankStep*10, 'mu'));
                }
                if( $this->differMax )
                {
                       $j++;
                       $newarr[$j] = array('w' => chr(0).chr(0x28), 't' => 3);
                       $j++;
                       $newarr[$j] = array('w' => $cw, 't' => 1);
                       $j++;
                       $newarr[$j] = array('w' => $nw, 't' => 1);
                       $j++;
                       $newarr[$j] = array('w' => chr(0).chr(0x29), 't' => 3);
                       $j++;
                }
                $j++; $i++; $ischeck = TRUE;
            }
            //检测前导词(通常是姓)
            else if( isset( $this->addonDic['n'][ $smarr[$i]['w'] ] ) )
            {
                $is_rs = FALSE;
                //词语是副词或介词或频率很高的词不作为人名
                if( strlen($nw)==4 )
                {
                    $winfos = $this->GetWordInfos($nw);
                    if(isset($winfos[1]) && in_array( $winfos[1], array('r', 'c', 'd', 'p', 't') ) )
                    {
                         $is_rs = TRUE;
                    }
                    else
                    {
                        $newarr[$j] = array('w' => $cw.$nw, 't' => 11);
                        if( !isset($this->newWords[ $newarr[$j]['w'] ]) )
                        {
                            $this->SetWordInfos($newarr[$j]['w'], array($this->rankStep * 3, 'nr'));
                        }
                        //为了防止错误，保留合并前的姓名
                        if( $this->differMax )
                        {
                            $j++;
                            $newarr[$j] = array('w' => chr(0).chr(0x28), 't' => 3);
                            $j++;
                            $newarr[$j] = array('w' => $cw, 't' => 1);
                            $j++;
                            $newarr[$j] = array('w' => $nw, 't' => 1);
                            $j++;
                            $newarr[$j] = array('w' => chr(0).chr(0x29), 't' => 3);
                            $j++;
                        }
                        $j++; $i++; $ischeck = TRUE;
                    }
                }
                //名字为单字直接组合，并检测第三个词
                else if( strlen($nw)==2 )
                {
                    //$tw = $smarr[$i+2]['w'];
                    $newarr[$j] = array('w' => $cw.$nw, 't' => 11);
                    if( isset($smarr[$i+2]['w']) && strlen($smarr[$i+2]['w'])==2 && !isset($this->addonDic['s'][ $smarr[$i+2]['w'] ]) )
                    {
                        $newarr[$j]['w'] .= $smarr[$i+2]['w'];
                        $i++;
                    }
                    if( !isset($this->newWords[ $newarr[$j]['w'] ]) )
                    {
                         $this->SetWordInfos($newarr[$j]['w'], array($this->rankStep * 3, 'nr'));
                    }
                    $j++; $i++; $ischeck = TRUE;
                }
            }
            //检测后缀词(地名等)
            else if( isset($this->addonDic['a'][$nw]) || isset($this->addonDic['e'][$nw]) )
            {
                $is_rs = FALSE;
                //词语是副词或介词不作为前缀
                if( strlen($cw) > 2 )
                {
                    $winfos = $this->GetWordInfos($cw);
                    if(isset($winfos[1]) && (in_array($winfos[1], array('a', 'r', 'c', 'd', 'p')) || $winfos[0] > $this->rankStep * 6) )
                    {
                         $is_rs = TRUE;
                    }
                }
                if( !isset($this->addonDic['s'][$cw]) && !$is_rs )
                {
                    $newarr[$j] = array('w' => $cw.$nw, 't' => 12);
                    //echo $this->_out_string_encoding( $cw ), "--";
                    //echo $this->_out_string_encoding( $newarr[$j]['w'] ), ",";
                    if( !isset($this->newWords[ $newarr[$j]['w'] ]) )
                    {
                        if( isset($this->addonDic['a'][$nw]) ) {
                            $this->SetWordInfos($newarr[$j]['w'], array($this->rankStep * 3, 'na'));
                        } else {
                            $this->SetWordInfos($newarr[$j]['w'], array($this->rankStep*10, 'ne'));
                        }
                    }
                    $i++; $j++; $ischeck = TRUE;
                }
            }
            //新词识别（暂无规则）
            else if($this->unitWord)
            {
                if( isset($this->addonDic['s'][$cw]) || isset($this->addonDic['s'][$nw]))
                {
                    $ischeck = FALSE;
                }
                else if( strlen($cw)==2 && strlen($nw)==2 && ( isset($this->hotChars[$cw]) || isset($this->hotChars[$nw]) )   )
                {
                    $newarr[$j] = array('w' => $cw.$nw, 't' => 1);
                    //尝试检测第三个词
                    if( isset($smarr[$i+2]['w']) && strlen($smarr[$i+2]['w'])==2 && 
                    (isset( $this->addonDic['a'][ $smarr[$i+2]['w'] ] ) || isset( $this->addonDic['u'][ $smarr[$i+2]['w'] ] )) )
                    {
                        $newarr[$j]['w'] .= $smarr[$i+2]['w'];
                        $i++;
                    }
                    if( !isset($this->newWords[ $newarr[$j]['w'] ]) )
                    {
                        $this->SetWordInfos($newarr[$j]['w'], array($this->rankStep * 3, 'ms'));
                    }
                    $i++; $j++; $ischeck = TRUE;
                }
            }
            
            //不符合规则
            if( !$ischeck )
            {
                $newarr[$j] = array('w' => $cw, 't' => 1);
                //高频停止词前面是单字
                if( strlen($cw)==2 && strlen($nw)==2 &&  isset($this->addonDic['h'][$nw]) )
                {
                    if( !isset($newarr[$j-1]['w']) || strlen($newarr[$j-1]['w']) > 2 )
                    {
                        $newarr[$j]['w'] = $cw.$nw;
                        if( !isset($this->newWords[ $newarr[$j]['w'] ]) )
                        {
                            $this->SetWordInfos($newarr[$j]['w'], array($this->rankStep*10, 'va'));
                        }
                        $i++; $j++;
                    }
                }
                //高频停止词优先正向词组
                else if( strlen($cw)==2 && strlen($nw)==4 &&  isset($this->addonDic['h'][substr($nw,-2,2)]) )
                {
                    //echo '[',$this->_out_string_encoding($cw), ' , ', $this->_out_string_encoding($nw), "]\n";
                    $newword = $cw.substr($nw,0,2);
                    //echo '[',$this->_out_string_encoding($newword), "]\n";
                    if( $this->IsWord($newword) )
                    {
                        $newarr[$j] = array('w' => $newword, 't' => 1);
                        $newarr[$j+1] = array('w' => substr($nw,-2,2), 't' => 1);
                        $i++; $j++;
 
                    }
                    
                }
                //二元消岐处理——最大切分模式
                else if( $this->differMax && !isset($this->addonDic['s'][$cw]) && strlen($cw) < 5 && strlen($nw) < 7)
                {
                    $slen = strlen($nw);
                    $hasDiff = FALSE;
                    for($y=2; $y <= $slen-2; $y=$y+2)
                    {
                        $nhead = substr($nw, $y-2, 2);
                        $nfont = $cw.substr($nw, 0, $y-2);
                        if( $this->IsWord( $nfont.$nhead ) )
                        {
                            if( strlen($cw) > 2 ) $j++;
                            $hasDiff = TRUE;
                            $newarr[$j] = array('w' => $nfont.$nhead, 't' => 1);
                            if( !isset($this->newWords[ $newarr[$j]['w'] ]) )
                            {
                                $this->SetWordInfos($newarr[$j]['w'], array($this->rankStep, 'n'));
                            }
                        }
                    }
                }
                $j++;
            }
            
       }//end for
       $smarr =  $newarr;
    }
    
   /**
    * 转换最终分词结果到 finallyResult 数组
    * @return void
    */
    private function _sort_finally_result()
    {
        $newarr = array();
        $i = 0;
        //echo '<xmp>'; print_r($this->finallyResult); exit();
        foreach($this->simpleResult as $k => $v)
        {
            if( empty($v['w']) ) continue;
            if( isset($this->finallyResult[$k]) && count($this->finallyResult[$k]) > 0 )
            {
                foreach($this->finallyResult[$k] as $w)
                {
                    if( !empty( $w['w'] ) )
                    {
                        $newarr[$i]['w'] = $w['w'];
                        $newarr[$i]['t'] = $w['t'];
                        $i++;
                    }
                }
            }
            //21是指书名
            else if($v['t'] != 21)
            {
                $newarr[$i]['w'] = $v['w'];
                $newarr[$i]['t'] = $v['t'];
                $i++;
            }
        }
        $this->finallyResult = $newarr;
        $newarr = '';
      }
      
   /**
    * 检测 finallyResult 数组词汇的属性
    * 需要计算rank或输出词性的时候才需要进行这个操作
    * @return void
    */
    public function CheckWordProperty()
    {
        if( !empty($this->propertyResult) ) return $this->propertyResult;
        $i = 0;
        $frLen = count($this->finallyResult);
        for($i=0; $i < $frLen; $i++)
        {
            //foreach($this->finallyResult as $w ){
            if( !isset($this->finallyResult[$i]) ) continue;
            $w = $this->finallyResult[$i];
            $info = $this->GetWordInfos( $w['w'] );
            if( $info != FALSE )
            {
                $this->propertyResult[ $w['w'] ] = $info;
            }
            else if( isset($this->newWords[ $w['w'] ]) )
            {
                $this->propertyResult[ $w['w'] ] = $this->newWords[ $w['w'] ];
            }
            else
            {
                if( $w['t'] == 13 ) 
                {
                    $this->propertyResult[ $w['w'] ] = array(10, 'n');
                }
                else 
                {
                    $uword = $this->_out_string_encoding( $w['w'] );
                    $luword = strtolower($uword);
                    //没意义的符号或高频的英语或单个汉字
                    if( strlen($uword) < 2 || preg_match("/[@#_%\+\-]/", $uword) || 
                      ( strlen($uword) < 4 && !preg_match("/[a-z]/", $luword) ) || in_array($luword, $this->enBadWords)  )
                    {
                        if( in_array($luword, $this->enBadWords) ) {
                            $this->propertyResult[ $w['w'] ] = array($this->rankStep*10, 'e');
                        } else {
                            $this->propertyResult[ $w['w'] ] = array($this->rankStep*10, 's');
                        }
                    }
                    /*
                    //英语权重词
                    elseif( $w['t'] == 2 ) {
                        
                    }
                    */
                    //常规英文或其它字符
                    else
                    {
                        //$this->propertyResult[ $w['w'] ] = array($this->rankStep*5, 'e');
                        //echo $this->_out_string_encoding($w['w']), ',';
                        if( preg_match("/^[A-Z]/", $uword) && !in_array($luword, $this->enBadWords)  )
                        {
                            if( preg_match("/[0-9]$/", $uword) ) {
                                $this->propertyResult[ $w['w'] ] = array($this->rankStep*3, 'n');
                            } else {
                                $this->propertyResult[ $w['w'] ] = array($this->rankStep*2, 'n');
                            }
                        }
                        else
                        {
                            $this->propertyResult[ $w['w'] ] = array($this->rankStep*5, 'e');
                        }
                    }
                }
            }
        }
    }
    
    /**
     * 把uncode字符串转换为输出字符串
     * @parem str
     * return string
     */
     private function _out_string_encoding( &$str )
     {
        $rsc = $this->_source_result_charset();
        if( $rsc==1 ) {
            $rsstr = $this->_iconv(UCS2, 'utf-8', $str);
        }
        else if( $rsc==2 ) {
            $rsstr = $this->_iconv('utf-8', 'gb18030', $this->_iconv(UCS2, 'utf-8', $str) );
        }
        else{
            $rsstr = $this->_iconv('utf-8', 'big5', $this->_iconv(UCS2, 'utf-8', $str) );
        }
        return $rsstr;
     }
     
    /**
     * 获取粗分结果，不包含粗分属性
     * @return array()
     */
     public function GetSimpleResult()
     {
        $rearr = array();
        foreach($this->simpleResult as $k=>$v)
        {
            if( empty($v['w']) ) continue;
            $w = $this->_out_string_encoding($v['w']);
            if( $w != ' ' ) $rearr[] = $w;
        }
        return $rearr;
     }
     
    /**
     * 获取粗分结果，包含粗分属性（1中文词句、2 ANSI词汇（包括全角），3 ANSI标点符号（包括全角），4数字（包括全角），5 中文标点或无法识别字符）
     * @return array()
     */
     public function GetSimpleResultAll()
     {
        $rearr = array();
        foreach($this->simpleResult as $k=>$v)
        {
            $w = $this->_out_string_encoding($v['w']);
            if( $w != ' ' )
            {
                $rearr[$k]['w'] = $w;
                $rearr[$k]['t'] = $v['t'];
            }
        }
        return $rearr;
     }
     
    /**
     * 获取发现的新词(返回一个数组或整理好的字符串)
     * @ param $is_array 1 返回array 0 返回带词性的字符串  -1 用空格分开的普通字符串
     * @return array or string
     */
     public function GetNewWrods( $is_array=FALSE )
     {
        if( $is_array===TRUE || $is_array==1 )
        {
            return $this->newWords;
        }
        else if( $is_array==-1 )
        {
            $newWordStr = '';
            foreach( $this->newWords as $word => $wordinfos )
            {
                $newWordStr .= $this->_out_string_encoding($word).' ';
            }
            return rtrim($newWordStr);
        }
        else
        {
            $newWordStr = '';
            foreach( $this->newWords as $word => $wordinfos )
            {
                $newWordStr .= $this->_out_string_encoding($word).'/'.$wordinfos[1].', ';
            }
            return $newWordStr;
        }
     }
     
    /**
     * 根据源字符串直接分词并返回分词后的字符串
     * 这个函数就是把 SetSource -> StartAnalysis -> GetFinallyResult 合并一起
     * @param $str 源字符串
     * @param $spword 分隔词（一般用空格）
     * @return string
     */
     public function GetStringResult($str, $spword=' ', $optimize=TRUE)
     {
        $this->SetSource( $str );
        $this->StartAnalysis( $optimize );
        return $this->GetFinallyResult($spword, FALSE);
     }
     
    /**
     * 根据源字符串直接分词并返回分词后的词典内词汇数组
     * 这个函数和GetStringResult不同之处是，它只返回字典存在的词条
     * @param $sortby  count/rank
     * @param $str 源字符串
     * @return array
     */
     public function GetSeoResult( $str, $sortby="count" )
     {
        $this->seoModel = TRUE;
        $this->SetSource( $str );
        $rs = $this->StartSeoAnalysis( $sortby );
        $this->seoModel = FALSE;
        return $rs;
     }
     
    /**
     * 获取最终结果字符串（用空格分开后的分词结果, 按原文顺序）
     * @param $str 源字符串
     * @param $spword 分隔词（一般用空格）
     * @return array(0 => 分好词的内容, 1 => 新词)
     */
     public function GetFinallyResult($spword=' ', $word_meanings=FALSE)
     {
        $rsstr = '';
        foreach($this->finallyResult as $v)
        {
            if( $this->resultType==2 && ($v['t']==3 || $v['t']==5 || isset($this->addonDic['s'][$v['w']]) ) )
            {
                continue;
            }
            $w = $this->_out_string_encoding($v['w']);
            if( $w==' ' || ($this->resultType==2 && (strlen($w) < 2 || substr($w, -1, 1)=='.')) )
            {
                continue;
            }
            if($word_meanings) {
                $rsstr .= $spword.$w.'/'.$this->GetWordProperty($v['w'])[1].$this->GetWordProperty($v['w'])[0];
            }
            else {
                $rsstr .= $spword.$w;
            }
        }
        return $rsstr;
     }
     
     /**
     * 获取包含词条完整信息的词组(去重)
     * @return array
     */
     public function GetFinallyWords( )
     {
        $rsArr = '';
        foreach($this->finallyResult as $v)
        {
            if( $this->resultType==2 && ($v['t']==3 || $v['t']==5 || isset($this->addonDic['s'][$v['w']]) ) )
            {
                continue;
            }
            $w = $this->_out_string_encoding($v['w']);
            if( $w==' ' || ($this->resultType==2 && (strlen($w) < 2 || substr($w, -1, 1)=='.')) )
            {
                continue;
            }
            $rsArr[$w] = $this->GetWordProperty($v['w']);
        }
        return $rsArr;
     }
     
    /**
     * 获取索引hash数组(会去重)
     * @param $sortby 排序方式 count 出现次数  rank 内部评分(TF_IDF)
     * @return array('word'=>count,...)
     */
     public function GetFinallyIndex( $sortby = 'count' )
     {
        $rearr = array();
        $rearr_m = array();
        //echo '<xmp>'; print_r($this->finallyResult); exit();
        foreach($this->finallyResult as $v)
        {
            $w = $this->_out_string_encoding($v['w']);
            if( $w==' ' || strlen($w) < 2 || substr($w, -1, 1)=='.' || $v['t']==5 || (in_array($v['t'],array(1,20)) && strlen($w) < 4) )
            {
                continue;
            }
            //echo $w.'/'.$v['t'],' ';
            if( isset($rearr[$w]) ) {
                 $rearr[$w]++;
            }
            else
            {
                $rearr[$w] = 1;
                if( $sortby == 'rank' )
                {
                    $rearr_m[$w] = $this->GetWordProperty($v['w']);
                    if( !is_array($rearr_m[$w])  )
                    {
                        //if( $v['t'] == 2 && strlen() > 4 )
                        $rearr_m[$w] = array(10000, 'x');
                    }
                }
            }
        }
        if( $sortby == 'count' )
        {
            arsort($rearr);
            return $rearr;
        }
        else 
        {
            foreach( $rearr as $w => $c )
            {
                //降低一些无意义的词的权重
                if( in_array($rearr_m[$w][1], $this->lowRankWords) ) {
                    $rearr_m[$w][0] = $this->rankStep * 20;
                }
                $rearr[$w] = sprintf("%0.3f", $c * 100 / $rearr_m[$w][0]);
            }
            arsort($rearr);
            return $rearr;
        }
     }
     
   /**
    * 获取特定数量按指定规则排序的词条
    * param $num 条数
    * param $sortby count/rank(tf-idf算法权重)
    * @return array
    */
    public function GetFinallyKeywords( $num = 10, $sortby = 'count' )
    {
        $words = $this->GetFinallyIndex( $sortby );
        $n = 1; $rearr = array();
        foreach( $words as $w => $v )
        {
            if( $n > $num ) break;
            $rearr[ $w ] = $v;
            $n++;
        }
        return $rearr;
    }
     
    /**
     * 获得保存目标编码
     * @return int
     */
     private function _source_result_charset()
     {
        if( preg_match("/^utf/", $this->targetCharSet) ) {
           $rs = 1;
        }
        else if( preg_match("/^gb/", $this->targetCharSet) ) {
           $rs = 2;
        }
        else if( preg_match("/^big/", $this->targetCharSet) ) {
           $rs = 3;
        }
        else {
            $rs = 4;
        }
        return $rs;
     }
     
     /**
     * 编译词典
     * @parem $sourcefile utf-8编码的文本词典数据文件<参见范例dict/not-build/base_dic_full.txt>
     * 注意, 需要PHP开放足够的内存才能完成操作
     * @return void
     */
     public function MakeDict( $source_file, $target_file='' )
     {
        $target_file = ($target_file=='' ? $this->mainDicFile : $target_file);
        $allk = array();
        $fp = fopen($source_file, 'r') or die("MakeDict Load dicfile: {$source_file} error!");;
        while( $line = fgets($fp, 512) )
        {
            if( $line[0]=='@' ) continue;
            list($w, $r, $a) = explode(',', $line);
            //$a = trim( $a );
            $w = $this->_iconv('utf-8', UCS2, $w);
            $k = $this->_get_index( $w );
            if( isset($allk[ $k ]) )
                $allk[ $k ][ $w ] = array($r, $a);
            else
                $allk[ $k ][ $w ] = array($r, $a);
        }
        fclose( $fp );
        $fp = fopen($target_file, 'w') or die("MakeDict create target_file: {$target_file} error!");
        $heade_rarr = array();
        $alldat = '';
        $start_pos = $this->mask_value * 8;
        foreach( $allk as $k => $v )
        {
            $dat  = serialize( $v );
            $dlen = strlen($dat);
            $alldat .= $dat;
        
            $heade_rarr[ $k ][0] = $start_pos;
            $heade_rarr[ $k ][1] = $dlen;
            $heade_rarr[ $k ][2] = count( $v );
        
            $start_pos += $dlen;
        }
        unset( $allk );
        for($i=0; $i < $this->mask_value; $i++)
        {
            if( !isset($heade_rarr[$i]) )
            {
                $heade_rarr[$i] = array(0, 0, 0);
            }
            fwrite($fp, pack("Inn", $heade_rarr[$i][0], $heade_rarr[$i][1], $heade_rarr[$i][2]));
        }
        fwrite( $fp, $alldat);
        fclose( $fp );
     }
     
     /**
     * 导出词典的词条
     * @parem $targetfile 保存位置
     * @return void
     */
     public function ExportDict( $targetfile )
     {
        if( !$this->mainDicHand )
        {
            $this->mainDicHand = fopen($this->mainDicFile, 'r') or die("ExportDict open dicfile:{$this->mainDicFile} error!");
        }
        $fp = fopen($targetfile, 'w') or die("ExportDict create targetfile: {$targetfile} error!");
        for($i=0; $i <= $this->mask_value; $i++)
        {
            $move_pos = $i * 8;
            fseek($this->mainDicHand, $move_pos, SEEK_SET);
            $dat = fread($this->mainDicHand, 8);
            $arr = unpack('I1s/n1l/n1c', $dat);
            if( $arr['l'] == 0 )
            {
                continue;
            }
            fseek($this->mainDicHand, $arr['s'], SEEK_SET);
            $data = @unserialize(fread($this->mainDicHand, $arr['l']));
            if( !is_array($data) ) continue;
            foreach($data as $k => $v)
            {
                $w = $this->_iconv(UCS2, 'utf-8', $k);
                fwrite($fp, "{$w},{$v[0]},{$v[1]}\n");
            }
        }
        fclose( $fp );
        return TRUE;
     }
}

