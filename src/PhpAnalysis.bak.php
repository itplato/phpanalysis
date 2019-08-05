<?php
namespace Tutu;
/****************************************************************************
*  居于Unicode编码词典的php分词器
*  1、只适用于php5以上，必要函数iconv或substring其中一种
*  2、本程序是使用RMM逆向匹配算法进行分词的，词库需要特别编译，本类里提供了 MakeDict() 方法
*  3、对主词典使用特殊格式进行编码, 不需要预先载入词典到内存操作
*
* Copyright IT柏拉图  Email: 2500875#qq.com
*
* @version 5.0
*
* 基本分词过程如下
use Tutu\PhpAnalysis;
* $result_str = PhpAnalysis::Instance()
*                 ->SetSource($str)
*                 ->SetOptions()
*                 ->Delimiter(' ')
*                 ->ExecSimpleAnalysis()
*                 ->ExecDeepAnalysis()
*                 ->Optimize( true );
* 上面是整个设置与分词的过程，当然实际使用中,如果不调整任何参数，只需要简单的写成：
* $result_str = PhpAnalysis::Instance()
*                 ->SetSource($str)
*                 ->Delimiter(' ')
*                 ->Exec();
*
* 如果你要获取的是tag： 
* $tags = PhpAnalysis::Instance()
*            ->SetSource($str)
*            ->Exec( false )
*            ->GetTags(10);
*
* 你也可以按传统的写法，做别的用途（如修改词典等）
* 对文本进行简单粗分：
* $pa = PhpAnalysis::Instance();
* $simple_result = $pa->SetSource($str)->ExecSimpleAnalysis( true );
* 
* 导出词典
* $pa = PhpAnalysis::Instance();
* $pa->AssistExportDict( $target_file, $dicfile );
*
********************************************************************/
//常量定义
define('_PA_SP_', chr(0xFF) . chr(0xFE));
define('_PA_UCS2_', 'ucs-2be');

class PhpAnalysis
{
    
    //工厂化单例模式的实例对像
    protected static $_instance = NULL;
    
    //hash算法选项
    protected $_mask_value = 0xEFFF;
    
    //输入和输出的字符编码（只允许 utf-8、gbk/gb2312/gb18030、big5 三种类型）  
    protected $_source_encoding = 'utf-8';
    protected $_target_encoding = 'utf-8';
    
    //句子长度小于这个数值时不拆分，notSplitLen = n(个汉字) * 2 + 1
    public $not_split_len = 5;
    
    //把英文单词全部转小写(仅在获取分词结果时才会进行这个操作，
    //尽量不要直接对整个源字符串使用strtolower，php这函数存在bug)
    public $en_to_lower = false;
    
    //生成的分词结果数据类型 1 全部， 2 排除$_ansi_word_match包含的特殊符号外的正常字词  3 仅词典词汇
    public $result_type = 2;
    
    //书名最大长度(对于中文来说把这个除以2就是实际长度，英文这个不一定适合)
    public $book_name_length = 30;
    
    //对分词后的结果尝试优化
    public $optimize = true;
    
    //识别专有词（数量、地名、人名等）
    public $unit_special_word = true;
    
    //使用最大切分模式对二元词进行消岐
    public $max_split = false;
    
    //尝试合并单字
    public $unit_single_word = true;
    
    //使用热门词优先模式进行消岐
    public $high_freq_priority = false;
    
    //被转换为unicode的源字符串
    private $_source_string = '';
    
    //字符串形式的分词结果的间隔符(默认是空格，如果输出是gb类编
    //不要乱用间隔符，不然把结果进行二次处理时，有出乱码的可能性)
    protected $_delimiter_word = ' ';
    
    //附加词典
    protected $_addon_dic = array();
    protected $_addon_dic_file = 'words_addons.dic';
    
    //半角与全角ASCII对照表
    protected $_sbc_array = array();
    
    //输出编码类型
    protected $_out_encoding_type = 1;
    
    //词典主目录
    public $dic_root = "";
    
    //未编译词条目录(相对主目录)
    public $dic_source = "not-build";
    
    //主词典 
    protected $_main_dic = array();
    protected $_main_dic_hand = NULL;
    protected $_main_dic_file = 'base_dic_full.dic';
    
    //英语词典
    //protected $_en_dic = array(); 这个直接放到 _main_dic 进行分组，减少内存占用
    protected $_en_dic_hand = NULL;
    protected $_en_dic_file = 'base_dic_english.dic';
    
    //主词典词语最大长度 x / 2
    protected $_dic_word_max = 16;
    
    //粗分后的数组（通常是截取句子等用途）
    protected $_simple_result = array();
    
    //经过切分后的分词结果
    protected $_deep_result = array();
    
    //经过优化后的分词结果
    protected $_finally_result = array();
    
    //标注了属性后的结果(仅适用当次分词)
    protected $_property_result = array();
    
    //是否已经载入词典
    public $_has_loaddic = false;
    
    //系统识别或合并的新词
    protected $_new_words = array();
    
    //二元消岐记录
    protected $_ambiguity_words = array();
    
    //当前操作合成的姓名
    protected $_names = array();
    
    //英语高频词(暂时不用这个，因为这个在英语词典中本身有体现)
    protected $_en_break_word_file = 'english-bad-words.txt';
    protected $_en_break_words = array();
    
    //最热门的前1000个汉字(尝试单词合并时，排除过于冷门的字，可以防止乱码的情况)
    protected $_hot_char_dic_file = 'char_rank.txt';
    protected $_hot_chars = array();
    
    //权重因子
    public $rank_step = 100;
    
    //对照符号(快速分词时会以这些符号作为粗分依据)
    protected $_symbol_compares = array(
                ['“', '”', '‘', '’', '，', '！', '。', '；', '？', '：', '《', '》', '——', '（', '）', '【', '】', '、'],
                ['"', '"', '\'', '\'', ',', '!', '.', ',', '?', ':', '<', '>', '--', '(', ')', '[', ']', '.'],
           );
    
    //处理英语或英语和数字混合的东西
    protected $_ansi_word_match = "[0-9a-z@#%\.\-]";
    protected $_not_number_match = "[a-z@#%]";
    
    //保留的符号(这几个符号是和网址有关的东西，但似乎也没什么用)
    protected $_need_symbol = "[:/&\?!]";
    
    //不适合太高权重的词性
    protected $_low_rank_propertys = array('mq', 'd', 'p', 'r', 'vc', 'nk');
    
    //使用mb_convert
    protected $_use_mb_convert = false;
    
    /**
     * 工厂化实例
     * @param $force_init  强制重新初始化实例
     * @return this object
     */
    public static function Instance( $force_init = false )
    {
        if( $force_init || (!self::$_instance Instanceof self) )
        {
            self::$_instance = new self();
        }
        return self::$_instance;
    }
    
    /**
     * 构造函数
     * @param $source_encoding
     * @param $target_encoding
     * @return void
     */
    public function __construct($source_encoding='utf-8', $target_encoding='utf-8')
    {
        $this->dic_root = dirname(__FILE__).'/../dict';
        $this->source_encoding = $source_encoding;
        $this->target_encoding = $target_encoding;
        //检测使用哪个函数转换字符编码
        if( function_exists('mb_convert_enencoding') ) {
            $this->_use_mb_convert = true;
        }
        else if( !function_exists('iconv') ) {
            throw new Exception("iconv or mb_substring is necessary!");
        }
    }
    
   /**
    * 析构函数
    */
    function __destruct()
    {
        if( $this->_main_dic_hand ) {
            fclose( $this->_main_dic_hand );
        }
        if( $this->_en_dic_hand ) {
            fclose( $this->_en_dic_hand );
        }
    }
    
   /**
    * 用substring代替iconv
    * @return string    
    */
    public function ConvertEncoding($in, $out, $str)
    {
        if( $this->_use_mb_convert ) {
            return mb_convert_enencoding($str, $out, $in);
        }
        else {
            return iconv($in, $out, $str);
        }
    }
    
    /**
     * 把uncode字符串转换为输出字符串
     * @parem str
     * return string
     */
    private function _get_out_encoding( $str )
    {
        if ( $this->_out_encoding_type == 1 ) {
            $str = $this->ConvertEncoding(_PA_UCS2_, 'utf-8', $str);
        } else if ( $this->_out_encoding_type == 2 ) {
            $str = $this->ConvertEncoding('utf-8', 'gb18030', $this->ConvertEncoding(_PA_UCS2_, 'utf-8', $str));
        } else {
            $str = $this->ConvertEncoding('utf-8', 'big5', $this->ConvertEncoding(_PA_UCS2_, 'utf-8', $str));
        }
        return $str;
    }
    //懒人调用_get_out_encoding
    private function _out( $str )
    {
        return $this->_get_out_encoding( $str );
    }
    
    /**
     * 设置源字符串
     * @param $source 需要分词的源字符串
     * @param $source_encoding   输入字符编码
     * @param $target_encoding  输出字符编码
     *
     * @return this
     */
    public function SetSource($source, $source_encoding = 'utf-8', $target_encoding='utf-8')
    {
        $this->_source_encoding = strtolower( $source_encoding );
        $this->_target_encoding = strtolower( $target_encoding );
        $this->_simple_result = array();
        $this->_deep_result = array();
        $this->_property_result = array();
        $this->_finally_result = array();
        $this->_new_words = array();
        $this->_ambiguity_words = array();
        $this->_names = array();
        $rs = true;
        //检查输入编码
        if( $source != '' )
        {
            if( preg_match("/^utf/", $source_encoding) ) {
                $this->source_string = $this->ConvertEncoding('utf-8', _PA_UCS2_, $source);
            }
            else if( preg_match("/^gb/", $source_encoding) ) {
                $this->source_string = $this->ConvertEncoding('utf-8', _PA_UCS2_, $this->ConvertEncoding('gb18030', 'utf-8', $source));
            }
            else if( preg_match("/^big/", $source_encoding) ) {
                $this->source_string = $this->ConvertEncoding('utf-8', _PA_UCS2_, $this->ConvertEncoding('big5', 'utf-8', $source));
            }
            else {
                $rs = false;
            }
        }
        else
        {
           $rs = false;
        }
        if( !$rs ) {
            throw new Exception( __FUNCTION__.": source encoding {$source_encoding} is not allow!" );
        }
        //输出编码类型
        if( preg_match("/^utf/", $target_encoding) ) {
            $this->_out_encoding_type = 1;
        }
        else if( preg_match("/^gb/", $target_encoding) ) {
            $this->_out_encoding_type = 2;
        }
        else if( preg_match("/^big/", $target_encoding) ) {
            $this->_out_encoding_type = 3;
        }
        else {
            $this->_out_encoding_type = 4;
        }
        if( $this->_out_encoding_type == 4 ) {
            throw new Exception( __FUNCTION__.": target encoding {$target_encoding} is not allow!" );
        }
        if( !$this->_has_loaddic ) {
            $this->LoadDict();
        }
        return $this;
    }
    
    /**
     * 分词结果分隔符
     * @param $str
     *
     * @return this
     */
    public function Delimiter( $str )
    {
        $this->_delimiter_word = $str;
        return $this;
    }
    
   /**
    * 设置分词优化参数
    * @param $unit_special_word=true     识别专用词(人名、地名、数量)
    * @param $unit_single_word=false    合并切分后无法识别的单个中文为猜测词汇 
    * @param $max_split=false           最大切分
    * @param $high_freq_priority=false  热门词优先的方式处理岐义
    * @param $optimize=true      优化结果(前面三个参数依赖这个参数)
    * @return this
    */
    public function SetOptions($unit_special_word=true, $unit_single_word=false, $max_split=false, $high_freq_priority=false, $optimize=true)
    {
        //任何一个优化参数为的开启，前提都是必须开启 $optimize
        if( $optimize == false ) {
            $unit_single_word = $max_split = $high_freq_priority = false;
        }
        $this->optimize = $optimize;
        $this->unit_special_word = $unit_special_word;
        $this->unit_single_word = $unit_single_word;
        $this->max_split = $max_split;
        $this->high_freq_priority = $high_freq_priority;
        return $this;
    }
    
    /**
     * 指定主词典
     * @param $main_dic_file  文件用绝对路径或放在词典目录时用文件名
     *                        如果要重新加载默认的词典，名称用 base_dic_full.dic 即可
     * @return this
     */
    public function LoadDict( $main_dic_file = '' )
    {
        if( $main_dic_file == '' && $this->_has_loaddic ) {   //词典只加载一次
            return $this;
        }
        else if( $main_dic_file == '' ) {
            $this->_main_dic_file = $this->dic_root.'/'.$this->_main_dic_file;
        } else {
            $this->_main_dic_file = file_exists($main_dic_file) ? $main_dic_file : $this->dic_root.'/'.$main_dic_file;
        }
        
        //加载主词典（只打开）
        if( $this->_has_loaddic )  fclose( $this->_main_dic_hand );
        $this->_main_dic_hand = fopen($this->_main_dic_file, 'r') or die( __FUNCTION__.": main_dic_file not exists!" );
        
        //载英语词典
        if( !$this->_en_dic_hand )
        {
            if( !file_exists($this->_en_dic_file) ) $this->_en_dic_file = $this->dic_root.'/'.$this->_en_dic_file;
            $this->_en_dic_hand = fopen($this->_en_dic_file, 'r') or die( __FUNCTION__.": en_dic_file not exists!" );
        }
        
        //载入副词典
        if( !$this->_has_loaddic )
        {
            $hw = '';
            $ds = file( $this->dic_root.'/'.$this->_addon_dic_file );
            foreach($ds as $d)
            {
                $d = trim($d);
                if($d=='') continue;
                if( $d[0]==';' || $d[0]=='#' )
                {
                    list($hw, $_comment) = explode(':', $d);
                    $hw = preg_replace("/[;#]/", '', $hw);
                }
                else
                {
                    $spstr = _PA_SP_;
                    $spstr = $this->ConvertEncoding(_PA_UCS2_, 'utf-8', $spstr);
                    $ws = explode(',', $d);
                    $wall = $this->ConvertEncoding('utf-8', _PA_UCS2_, join($spstr, $ws));
                    $ws = explode(_PA_SP_, $wall);
                    foreach($ws as $estr)
                    {
                        $this->_addon_dic[$hw][$estr] = strlen($estr);
                    }
                }
            }
            
            //全角与半角字符对照表
            $j = 0;
            for($i=0xFF00; $i < 0xFF5E; $i++)
            {
                $scb = 0x20 + $j;
                $j++;
                $this->_sbc_array[$i] = $scb;
            }
            
            //热门汉字
            $n = 1;
            $fp = fopen( $this->dic_root.'/'.$this->_hot_char_dic_file , 'r');
            while($n < 1000)
            {
                $line = rtrim(fgets($fp, 64));
                list($c, $r) = explode(' ', $line);
                $c = $this->ConvertEncoding('utf-8', _PA_UCS2_, $c);
                $this->_hot_chars[$c] = sprintf('%0.2f', log($r) - 10);
                $n++;
            }
            fclose($fp);
            
        } //load addDic
        
        $this->_has_loaddic = true;
        return $this;
    }
    
    /**
     * 执行一个标准的分词操作
     * @param $return  是否马上返回结果
     *        如果想要获取其它分词方式的结果，这个设置为 false，然后通过 ->get_xxx 方式获取其它类型结果
     * @return $result_string || this
     */
    public function Exec( $return = true )
    {
        $this->ExecSimpleAnalysis()->ExecDeepAnalysis()->Optimize();
        if( $return ) {
            return $this->GetResult();
        } else {
            return $this;
        }
    }
    
    /**
     * 执行简单分词操作（把文本通过一些特殊符号之类特征进行断句，英语单词则是由空格分开，形成一个粗分词组）
     * @param $return  是否马上返回结果
     *        如果想要获取其它分词方式的结果，这个设置为 false，然后通过 ->get_xxx 方式获取其它类型结果
     * @return array simple_result || this
     */
    public function ExecSimpleAnalysis( $return = false )
    {
        $this->_simple_result = $this->_deep_result = $this->_property_result = array();
        $this->source_string .= chr(0).chr(0x20);
        $source_len = strlen( $this->source_string );
        
        //对字符串进行粗分
        $onstr = '';
        //1 中/韩/日文, 2 英文/数字/符号('.', '@', '#', '+'), 3 ANSI符号 4 纯数字 5 非ANSI符号或不支持字符 7 '·'   8 书名
        $last_char_type = 1;
        $s = 0;
        $_ansi_word_match = $this->_ansi_word_match;
        $_not_number_match = $this->_not_number_match;
        for($i=0; $i < $source_len; $i++)
        {
            $c = $this->source_string[$i].$this->source_string[++$i];
            $cn = hexdec(bin2hex($c));
            $cn = isset($this->_sbc_array[$cn]) ? $this->_sbc_array[$cn] : $cn;
            //ANSI字符(全角的也会转义半角)
            if( $cn < 0x80 )
            {
                if( preg_match('~'.$_ansi_word_match.'~i', chr($cn)) )
                {
                    if( $last_char_type != 2 && $onstr != '') {
                        $this->_simple_result[$s]['w'] = $onstr;
                        $this->_simple_result[$s]['t'] = $last_char_type;
                        $this->_simple_result[$s]['d'] = 0;
                        $s++;
                        $onstr = '';
                    }
                    $last_char_type = 2;
                    $onstr .= chr(0).chr($cn);
                }
                else
                {
                    if( $onstr != '' )
                    {
                        $this->_simple_result[$s]['w'] = $onstr;
                        if( $last_char_type == 2 )
                        {
                            if( !preg_match('/'.$_not_number_match.'/i', $this->ConvertEncoding(_PA_UCS2_, 'utf-8', $onstr)) ) $last_char_type = 4;
                        }
                        $this->_simple_result[$s]['t'] = $last_char_type;
                        $this->_simple_result[$s]['d'] = 0;
                        $s++;
                    }
                    $onstr = '';
                    $last_char_type = 3;
                    if( $cn < 0x21 || $cn == 0x7F ) //没意义的ascii符号
                    {
                        continue;
                    }
                    else
                    {
                        $this->_simple_result[$s]['w'] = chr(0).chr($cn);
                        $this->_simple_result[$s]['t'] = 3;
                        $this->_simple_result[$s]['d'] = 0;
                        $s++;
                    }
                }
            }
            //普通字符
            else
            {
                //正常文字(分别中文、韩文、日假名)
                //阿拉伯文、梵文之类全会被当成符号(标识5)，需要当成字符的可以自行在这里补上区间值
                //$cn == 0x00B7 姓名的(·)，不好处理，不放这地方
                if(  ($cn > 0x33FF && $cn < 0x9FB0) || ($cn > 0xABFF && $cn < 0xD7A4) || ($cn > 0x3040 && $cn < 0x312C) )
                {
                    if( $last_char_type != 1 && $onstr != '')
                    {
                        $this->_simple_result[$s]['w'] = $onstr;
                        if( $last_char_type == 2 )
                        {
                            if( !preg_match('/'.$_not_number_match.'/i', $this->ConvertEncoding(_PA_UCS2_, 'utf-8', $onstr)) ) $last_char_type = 4;
                        }
                        $this->_simple_result[$s]['t'] = $last_char_type;
                        $this->_simple_result[$s]['d'] = 0;
                        $s++;
                        $onstr = '';
                    }
                    $last_char_type = 1;
                    $onstr .= $c;
                }
                //特殊符号
                else
                {
                    if( $onstr != '' )
                    {
                        $this->_simple_result[$s]['w'] = $onstr;
                        if( $last_char_type==2 )
                        {
                            if( !preg_match('/'.$_not_number_match.'/i', $this->ConvertEncoding(_PA_UCS2_, 'utf-8', $onstr)) ) $last_char_type = 4;
                        }
                        $this->_simple_result[$s]['t'] = $last_char_type;
                        $this->_simple_result[$s]['d'] = 0;
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
                            if( !isset($this->source_string[$i+$n+1]) )  break;
                            $w = $this->source_string[$i+$n].$this->source_string[$i+$n+1];
                            if( $w == $ew )
                            {
                                $this->_simple_result[$s]['w'] = $c;
                                $this->_simple_result[$s]['t'] = 7;
                                $this->_simple_result[$s]['d'] = 0;
                                $s++;
                        
                                $this->_simple_result[$s]['w'] = $tmpw;
                                $this->_simple_result[$s]['t'] = 8;
                                $this->_simple_result[$s]['d'] = 0;
                                $s++;

                                
                                $this->_simple_result[$s]['w'] = $ew;
                                $this->_simple_result[$s]['t'] =  7;
                                $this->_simple_result[$s]['d'] = 0;
                                $s++;
                        
                                $i = $i + $n + 1;
                                $isok = TRUE;
                                $onstr = '';
                                $last_char_type = 7;
                                break;
                            }
                            else
                            {
                                $n = $n+2;
                                $tmpw .= $w;
                                if( strlen($tmpw) > $this->book_name_length )
                                {
                                    break;
                                }
                            }
                        }//while
                        if( !$isok )
                        {
                              $this->_simple_result[$s]['w'] = $c;
                              $this->_simple_result[$s]['t'] = 5;
                              $this->_simple_result[$s]['d'] = 0;
                              $s++;
                              $onstr = '';
                              $last_char_type = 5;
                        }
                        continue;
                    }
                    
                    $onstr = '';
                    $last_char_type = 5;
                    $this->_simple_result[$s]['w'] = $c;
                    $this->_simple_result[$s]['t'] = $last_char_type;
                    $this->_simple_result[$s]['d'] = 0;
                    $s++;
                }//2byte symbol
                
            }//end 2byte char
        
        }//end for
        
        if( $return ) {
            return $this->assist_get_simple();
        } else {
            return $this;
        }
    }
    
    /**
     * 获取最终分词结果
     * @return string
     */
    public function GetResult()
    {
        $result = '';
        $okwords = array();
        foreach( $this->_finally_result as $k => $words )
        {
            if( $words['t'] == 250 ) continue;
            $_w = $this->_get_out_encoding( $words['w'] );
            $okwords[] = $_w;
        }
        return join($this->_delimiter_word, $okwords);
    }
    
    /**
     * 获取最终分词结果包含词性标注
     * @return string
     */
    public function GetResultProperty()
    {
        $okwords = array();
        $this->_check_word_property();
        foreach( $this->_finally_result as $k => $words )
        {
            if( $words['t'] == 250 ) continue;
            $_w = $this->_get_out_encoding( $words['w'] );
            $p = $this->_property_result[ $words['w'] ][1];
            $okwords[] = $_w."/{$p}";
        }
        return join($this->_delimiter_word, $okwords);
    }
    
    /**
    * 获取词频或权重排序
    * @param $sort   rank 通过内部评分(TF_IDF)结果排序，count 通过词条在文章出现次数排序
    * @param $num 数量（0表示返回全部）
    * @return $array( word => count | rank)
    */
    public function GetWordRanks( $sort='rank', $num = 0 )
    {
        $this->_check_word_property();
        $ok_result = $tmp = array();
        $n = 1;
        foreach($this->_finally_result as $v)
        {
            if( $v['t'] == 250 ) continue;
            if( $v['l'] < 2 )
            {
                if( $v['t'] != 1 || isset($this->_addon_dic['s'][$v['w']]) ) {
                     continue;
                }
            }
            else if(  substr($v['w'], -1, 1)=='.' || in_array($v['t'], array(3,5,7)) )
            {
                continue;
            }
            if( isset( $tmp[ $v['w'] ] ) )
            {
                $tmp[ $v['w'] ]++;
            } else {
                $tmp[ $v['w'] ] = 1;
            }
        }
        if( $sort == 'count' )
        {
            arsort( $tmp );
            foreach($tmp as $w => $c) {
                $cn = $this->_get_out_encoding( $w );
                $ok_result[ $cn ] = $c;
                if( $num > 0 ) {
                    if( $n >= $num ) break;
                    else $n++;
                }
            }
            unset( $tmp );
        } 
        else
        {
            $str = '';
            foreach( $this->_hot_chars as $w => $v) {
                $str .= $w;
            }
            $hot_250_chars = array_slice($this->_hot_chars, 0, 250, true);
            $hot_750_chars = array_slice($this->_hot_chars, 250, 750, true);
            foreach($tmp as $w => $c)
            {
                $cn = $this->_get_out_encoding( $w );
                $dc = $this->_check_rank($w, 0);
                if( $dc==0 )
                {
                    //单个汉字的权重
                    if( strlen($w)==2 )
                    {
                        if( isset($hot_250_chars[$w]) ) {
                            $dc = $this->rank_step * 40;
                        } else {
                            $dc = isset($hot_750_chars[$w])  ? $this->rank_step * 20 : $this->rank_step * 10;
                        }
                    }
                    //正常词汇
                    else
                    {
                        $dc = (isset($this->_property_result[$w][0]) ? $this->_property_result[$w][0] : $this->rank_step * 40);
                    }
                }
                $ok_result[ $cn ] = array(sprintf("%0.3f", $c * 100 / $dc), $c, $dc);
            }
            unset( $tmp );
            arsort( $ok_result );
            if( $num > 0 )
            {
                $ok_result = array_slice($ok_result, 0, $num, true);
            }
        }
        return $ok_result;
    }
    
   /**
    * 检测最终词条，人工干预降权重
    * @param $w  没转码前的词条
    * @param $dc 原权重 
    * @return $new_dc
    */
    private function _check_rank($w, $dc)
    {
        $uw = $this->ConvertEncoding(_PA_UCS2_, 'utf-8', $w);
        //强制降低权重的情况
        if( preg_match("/(某|姓)$/", $uw) ) {
             $dc = $this->rank_step * 20;
        }
        return $dc;
    }
    
    /**
    * 获取特定数量的高权重词作为标签
    * @param $num   词条数量
    * @param $with_rank 是否包含评分
    * @return string  分隔符由Delimiter()决定，默认空格
    */
    public function GetTags( $num = 10, $with_rank = false )
    {
        $result = $this->GetWordRanks( 'rank' );
        $okstr = '';
        $n = 1;
        foreach( $result as $w => $v )
        {
            if( $with_rank ) {
                $okstr .= "{$w}/({$v[0]},{$v[1]},{$v[2]}) ";
            } else {
                $okstr .= $w.' ';
            }
            $n++;
            if( $n > $num ) break;
        }
        return rtrim($okstr);
    }
    
    /**
     * 获取发现的新词(返回一个数组或整理好的字符串)
     * @ param $is_array 1 返回array 0 返回带词性的字符串  -1 用空格分开的普通字符串
     * @return array or string
     */
     public function GetNewWords( $is_array=false )
     {
        if( $is_array )
        {
            $outWords = array();
            foreach( $this->_new_words as $word => $wordinfos )
            {
                $word =  $this->_get_out_encoding($word);
                $outWords[ $word ] = $wordinfos[0];
            }
            return $outWords;
        }
        else
        {
            $_new_wordstr = '';
            foreach( $this->_new_words as $word => $wordinfos )
            {
                $_new_wordstr .= $this->_get_out_encoding($word).'/'.$wordinfos[1].', ';
            }
            return $_new_wordstr;
        }
     }
    
    /**
     * 获取转码后的粗分结果
     * @return string | array
     */
    public function AssistGetSimple( $string=true )
    {
        $simple_result = '';
        foreach( $this->_simple_result as $k => $v )
        {
            if( $string ) {
                $w = $this->_get_out_encoding( $v['w'] );
                $simple_result .= "{$w}/{$v['t']}  ";
            } else {
                $simple_result[$k] = array('w' => $this->_get_out_encoding($v['w']), $v['t']);
            }
        }
        return $simple_result;
    }
    
    /**
     * 获取经过岐义处理的词组及结果
     * @return string
     */
    public function AssistGetAmbiguitys()
    {
        return join($this->_delimiter_word, $this->_ambiguity_words);
    }
    
    /**
     * 获取没进行过优化的分词结果
     * @return string
     */
    public function AssistGetDeep()
    {
        $result = '';
        foreach( $this->_deep_result as $k => $words )
        {
            foreach($words as $ws) {
                $result .= $this->_get_out_encoding( $ws['w'] ).' ';
            }
        }
        return rtrim($result);
    }
    
    /**
     * 获取粗分与切分后的结果对比
     * @return string
     */
    public function AssistGetCompare()
    {
        $simple_result = '';
        foreach( $this->_simple_result as $k => $v )
        {
            $w = $this->_get_out_encoding( $v['w'] );
            $simple_result .= "{$w}";
            
            $deep_result = '';
            $okrs = isset($this->_deep_result[$k]) ? $this->_deep_result[$k] : array();
            foreach($okrs as $ws) {
                if( !$ws['d'] ) continue;
                $_w = $this->_get_out_encoding( $ws['w'] );
                $deep_result .= "{$_w} ";
            }
            
            if( $deep_result != '' ) {
                $simple_result = $simple_result." -- {$deep_result}";
            }
            $simple_result .= "\n";
        }
        return $simple_result;
    }
    
    /**
     * 深入分词
     * 粗分之后对 $this->_simple_result 进行操作
     * _simple_result 和 _deep_result 中的 t 属性：
     * 1 中/韩/日文, 2 英文/数字/符号('.', '@', '#', '+'), 3 ANSI符号 4 纯数字 5 非ANSI符号或不支持字符 7书名括号 8 书名  
     * 组合词属性 11 数量、年分词  12 自动认为是一个词的词(只有两个字的粗分结果) 13 中文组合数量词   15 人名  16 称呼 17 地名 18 称号或专用词条，如集结号、成功率
     * 19 单字强制组词
     * 两个结果数组的数据结构：_simple_result => array(index1 => array('w', 't', 'd')...)
     *                     _deep_result => array(index1 => array(array('w', 't', 'd')...)...)
     * 即是 _deep_result 的子元素相对于把 _simple_result 对应元素的'w'再拆分的结果，其实 'd'==0 表示没经过切词就分出的结果
     * @return bool
     */
    public function ExecDeepAnalysis( $return = false )
    {
        $total = count( $this->_simple_result );
        if( $total == 0 ) {
            $this->ExecSimpleAnalysis();
        }
        for( $index = 0; $index < $total; $index++)
        {
            $ws = $this->_simple_result[$index];
            //非数字类型直接处理
            if( $ws['t'] != 4 )
            {
                //符号或英文
                if( in_array($ws['t'], [2,3,5]) )
                {
                    $this->_deep_result[ $index ][] = $ws;
                }
                //书名
                else if( $ws['t']==8 )
                {
                    $this->_deep_result[ $index ][] = $ws;
                    if( $this->max_split ) {
                        $this->_deep_analysis_cn($ws['w'], $index, 7);
                    }
                }
                //普通中文
                else
                {
                    if( strlen($ws['w']) < 5 && !isset($this->_addon_dic['s'][substr($ws['w'], -2, 2)]) )
                    {
                        $ws['t'] = 12;
                        $this->_deep_result[ $index ][] = $ws;
                        
                    } else {
                        $this->_deep_analysis_cn($ws['w'], $index, 1);
                    }
                }
            }
            //尝试对数字和数量词进行组合
            else
            {
                //下一个词大于4(大于两个中文，不处理)
                if( !isset($this->_simple_result[$index+1]) || $this->_simple_result[$index+1]['t'] != 1 
                    || strlen($this->_simple_result[$index+1]['w']) > 4  )
                {
                    $this->_deep_result[ $index ][] = $ws;
                }
                else if( isset( $this->_addon_dic['ms'][$this->_simple_result[$index+1]['w']] ) )
                {
                    $ws['w'] = $ws['w'].$this->_simple_result[$index+1]['w'];
                    $ws['t'] = 11;
                    $this->_deep_result[ $index ][] = $ws;
                    $this->_set_new_word($ws['w'], array($this->rank_step * 20, 'mu'));
                    $index++;
                }
                else
                {
                    $this->_deep_result[ $index ][] = $ws;
                }
            }
        }
        //返回默认结果或当前对象
        if( $return ) {
            return $this->AssistGetSimple();
        } else {
            return $this;
        }
    }
    
    /**
     * 中文的深入分词
     * @parem $str
     * @return void
     */
    protected function _deep_analysis_cn( $str, $index, $lasttype )
    {
        $tmparr = array();
        $hasw = 0;
        //进行切分
        $slen = strlen( $str );
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
            for($k=$this->_dic_word_max; $k>1; $k=$k-2)
            {
                if($i < $k) continue;
                $w = substr($str, $i-$k, $k);
                if( strlen($w) <= 2 )
                {
                    $i = $i - 1;
                    break;
                }
                if( $this->_is_dic_word( $w ) )
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
        if( $wcount==0 )
        {
            $this->_deep_result[$index][] = array('w' => $str, 't' => 12, 'd' => 1);
        }
        else
        {
            $sparr = array_reverse($tmparr);
            foreach( $sparr as $w ) {
                $this->_deep_result[$index][] = array('w' => $w, 't' => 1, 'd' => 1);
            }
        }
    }
    
   /**
    * 优化分词结果
    * _finally_result
    */
    public function Optimize( $return = false )
    {
        $deep_result = array();
        $blank = array('w' => chr(0).' ', 't' => 250, 'l' => 3, 'd' => 1, 'm' => '');
        //加一个干扰的开始符
        $deep_result[] = $blank;
        foreach( $this->_deep_result as $k => $words )
        {
            foreach($words as $ws) {
                $ws['l'] = strlen( $ws['w'] ) / 2;
                $ws['m'] = '';
                if( $ws['l'] > 1 && $ws['t']==1 ) {
                    $info = $this->_get_words( $ws['w'] );
                    $ws['m'] = isset($info[1]) ?  $info[1] : '';
                }
                $deep_result[] = $ws;
                //echo $this->_out( $ws['w'] ).'/'.$ws['m']." ";
            }
            //段落之类做个间隔
            //if( $ws['t'] == 1 ) {
            $deep_result[] = $blank;
            //}
        }
        //echo "<br>---------------------<br>";
        //加一个干扰的结束符
        $deep_result[] = $blank;
        $rslen = count($deep_result);
        for($i=0; $i < $rslen; $i++)
        {
            $match = false;
            //因为最后一个是干扰结束符，因此可以直接跳过
            if( !isset($deep_result[$i+1])  )
            {
                continue;
            }
            //特殊符号、书名等直接跳过
            else if( !in_array($deep_result[$i]['t'], [1,4,12]) )
            {
                $this->_finally_result[] = $deep_result[$i];
                continue;
            }
            
            //---------------------------
            //符合特定规则的词检查
            //数量词组合
            if( isset($this->_addon_dic['m'][$deep_result[$i]['w']]) || $deep_result[$i]['t'] == 4 )
            {
                $match = $this->_optimize_test_quantity($i, $deep_result);
            }
            //人名检测
            else if( $deep_result[$i+1]['t']==1 
                && (isset($this->_addon_dic['n'][$deep_result[$i]['w']]) || isset($this->_addon_dic['nf'][$deep_result[$i]['w']]))  )
            {
                $match = $this->_optimize_test_name($i, $deep_result);
            }
            //检测后缀词(地名等)
            else if( isset($this->_addon_dic['a'][$deep_result[$i+1]['w']]) || isset($this->_addon_dic['e'][$deep_result[$i+1]['w']])
                || isset($this->_addon_dic['z'][$deep_result[$i+1]['w']]) )
            {
                
                $match = $this->_optimize_test_suffix($i, $deep_result);
            }
            
            //-----------------------------
            //当次循环中没有可以合并的词
            if( !$match )
            {
                $this->_finally_result[] = $deep_result[$i];
            }
        }
        
        //岐义处理，单字尝试组词
        $c  =  count( $this->_finally_result );
        $this->_finally_result[] = $blank;
        
        //岐义处理
        if( $this->high_freq_priority )
        {
            for( $i=0; $i < $c; $i++ )
            {
                if( !isset($this->_finally_result[$i]) ) continue;
                $w = $this->_finally_result[$i];
                //岐义处理(长词优先，特殊结尾放弃)
                if(  $w['l'] < 3 && $w['t']==1 && $this->_finally_result[$i+1]['l']==2 ) {
                    $this->_optimize_test_priority($i, $w);
                }
            }
        }
        //尝试把单字合并成词组
        if( $this->unit_single_word )
        {
            for( $i=0; $i < $c; $i++ )
            {
                if( !isset($this->_finally_result[$i]) ) continue;
                $w = $this->_finally_result[$i];
                //合并外国人名
                if( $w['l'] > 1 )
                {
                    $winfo = $this->_get_words($w['w'], true);
                    if( isset($winfo[1]) && $winfo[1]=='nr' && isset($this->_finally_result[$i+2]) 
                         && isset($this->_finally_result[$i+4]) && bin2hex($this->_finally_result[$i+2]['w']) == '00b7' )
                    {
                         $winfo = $this->_get_words($this->_finally_result[$i+4]['w'], true);
                         if( isset($winfo[1]) && $winfo[1]=='nr' )
                         {
                              $this->_finally_result[$i]['w'] = $w['w'].$this->_finally_result[$i+2]['w'].$this->_finally_result[$i+4]['w'];
                              $this->_finally_result[$i]['m'] = 'nr';
                              if( !isset($this->_new_words[$this->_finally_result[$i]['w']]) ) {
                                  $this->_set_new_word($this->_finally_result[$i]['w'], array($this->rank_step * 3, 'nr'));
                              }
                              for($j=1; $j<5; $j++) {
                                  unset($this->_finally_result[$i+$j]);
                              }
                              $i += 4;
                              continue;
                         }
                    }
                }
                //单字合并
                if(  $w['t']==1 && $w['l']==1 
                && ( (!isset($this->_addon_dic['s'][$w['w']]) && !isset($this->_addon_dic['nh'][$w['w']]) ) || isset($this->_addon_dic['af'][$w['w']]) )  )
                {
                    $this->_optimize_merge_single($i, $w);
                }
            }
        }
        
        //返回默认结果或当前对象
        if( $return ) {
            return $this->GetResult();
        } else {
            return $this;
        }
    }
    
    /**
     * 二元消岐
     * @param $res   _finally_result
     * @return boolean
     */
    protected function _optimize_test_priority( &$i, $cur_w )
    {
        $next_w = $this->_finally_result[$i+1];
        $next_wh = substr($next_w['w'], 0, 2);
        $new_word = $cur_w['w'].$next_wh;
        $info = $this->_get_words( $new_word, true );
        if( $cur_w['l']==1 && ( isset($this->_addon_dic['a'][$cur_w['w']])
            || isset($this->_addon_dic['z'][$cur_w['w']]) || isset($this->_addon_dic['e'][$cur_w['w']]) ) )
        {
            return false;
        }
        if( ( isset($info[1]) && !preg_match('/[m]/', $cur_w['m']) ) || ($cur_w['l']==1 && isset($this->_addon_dic['em'][$next_wh])) )
        {
            $next_e = substr($next_w['w'], 2, 2);
            $is_save = false;
            $_w_debug = $this->_out($cur_w['w'])." ".$this->_out($next_w['w'])."(".$this->_out($new_word)." ".$this->_out($next_e);
            $cur_info = $this->_get_words( $next_w['w'] );
            //强制保留的后置词
            if( isset($this->_addon_dic['sw'][$next_w['w']]) ) {
                $is_save = false;
            }
            //长词优先(如果后面的词太过热门，也不处理)
            else if( $cur_w['l']==2 && $cur_info[0] < 600 && !isset($this->_addon_dic['se'][$next_wh]) )
            {
                $is_save = true;
                $this->_ambiguity_words[$_w_debug] = $_w_debug.' :max)';
            }
            else if( !isset($this->_addon_dic['s'][$next_wh]) && !isset($this->_addon_dic['m'][$next_wh]) && !isset($this->_addon_dic['af'][$next_wh]) 
                 && !isset($this->_addon_dic['se'][$cur_w['w']]) && !isset($this->_addon_dic['sl'][$cur_w['w']]))
            {
                //非中止词优先
                if( isset($this->_addon_dic['s'][$next_e]) && !isset($this->_addon_dic['en'][$next_wh]) )
                {
                    $is_save = true;
                    $this->_ambiguity_words[$_w_debug] = $_w_debug.' :stop)';
                }
                //中间组合词
                elseif( $cur_w['l']==1 && isset($this->_addon_dic['em'][$next_wh]) )
                {
                    $new_word = $cur_w['w'].$this->_finally_result[$i+1]['w'];
                    $this->_finally_result[$i]['w'] = $new_word;
                    $this->_finally_result[$i]['d'] = 2;
                    $this->_finally_result[$i]['l'] = strlen($cur_w['w'].$this->_finally_result[$i+1]['w']) / 2;
                    $this->_finally_result[$i]['m'] = $this->_finally_result[$i+1]['m'];
                    unset($this->_finally_result[$i+1]);
                    $i++;
                    if( !isset($this->_new_words[$new_word]) ) {
                        $rank = isset($cur_info[0]) ? $cur_info[0] : $this->rank_step * 5;
                        $type = isset($cur_info[1]) ? $cur_info[1] : 'x';
                        $this->_set_new_word($new_word, array($rank, $type));
                    }
                    return true;
                }
                //热门优先
                else
                {
                    if( $info[0] - $cur_info[0] > 100 )
                    {
                        $is_save = true;
                        //如果结尾词符合条件，组合成整个词
                        if( $cur_w['l']==1 && (isset($this->_addon_dic['a'][$next_e]) 
                            || isset($this->_addon_dic['z'][$next_e]) || isset($this->_addon_dic['e'][$next_e])) )
                        {
                            if( isset($this->_addon_dic['a'][$next_e]) ) {
                                $type = 'na';
                                $rank = $this->rank_step * 2;
                            }
                            elseif( isset($this->_addon_dic['z'][$next_e]) ) {
                                $type = 'nz';
                                $rank = $this->rank_step * 4;
                            } else {
                                $type = 'ne';
                                $rank = $this->rank_step * 6;
                            }
                            $new_word = $cur_w['w'].$this->_finally_result[$i+1]['w'];
                            $this->_finally_result[$i]['w'] = $new_word;
                            $this->_finally_result[$i]['d'] = 2;
                            $this->_finally_result[$i]['l'] = strlen($new_word) / 2;
                            $this->_finally_result[$i]['m'] = $type;
                            unset($this->_finally_result[$i+1]);
                            $i++;
                            if( !isset($this->_new_words[$new_word]) ) {
                                $this->_set_new_word($new_word, array($rank, $type));
                            }
                            return true;
                        }
                        else
                        {
                            $is_save = true;
                            $this->_ambiguity_words[$_w_debug] = $_w_debug.' :hot)';
                        }
                    }
                }
            }
            if( $is_save )
            {
                $this->_finally_result[$i]['w'] = $new_word;
                $this->_finally_result[$i]['d'] = 2;
                $this->_finally_result[$i]['l'] = 2;
                $this->_finally_result[$i+1]['w'] = $next_e;
                $this->_finally_result[$i+1]['d'] = 2;
                $this->_finally_result[$i+1]['l'] = 1;
                $i++;
            }
        }
    }
    
    /**
     * 单字合并模式 
     * @param $res   _finally_result
     * @return boolean
     */
    protected function _optimize_merge_single( &$i, $cur_w )
    {
        $next_w = $this->_finally_result[$i+1];
        $cur_pos = $i;
        if( $next_w['t'] != 1 )
        {
            return false;
        }
        //return false;
        //常用前缀词合并
        if( $next_w['l'] > 1 )
        {
            if( isset($this->_addon_dic['af'][$cur_w['w']]) )
            {
                $new_word = $cur_w['w'].$next_w['w'];
                
                $info = $this->_get_words($next_w['w']);
                $type = isset($info[1]) ? $info[1] : 'nf';
                $rank = isset($info[0]) ? $info[0] : $rank;
                $this->_finally_result[$i+1] = array('w' => $new_word, 't' => 50, 'd' => 2, 'l' => 2, 'm' => $type  );
                $this->_set_new_word($new_word, array($rank, $type));
                if( !$this->max_split )  unset($this->_finally_result[$i]); //如果不是最大切分，删除前一个词
                return true;
            }
            else
            {
                return false;
            }
        }
        if( false && isset($this->_addon_dic['es'][$next_w['w']]) )   //x了, x的
        {
            $new_word = $cur_w['w'].$next_w['w'];
            $this->_finally_result[$i+1] = array('w' => $new_word, 't' => 51, 'd' => 2, 'l' => 2, 'm' => 'd'  );
            $this->_set_new_word($new_word, array($this->rank_step * 10, 'd'));
            if( !$this->max_split )  unset($this->_finally_result[$i]);
            return true;
        }
        //尝试组合后面四个单字
        else
        {
            $new_word = $cur_w['w'];
            $j = 0;
            while($j < 4)
            {
                if( isset($this->_finally_result[$i+1]) )
                {
                    $_cur_c = $this->_finally_result[$i+1]['w'];
                    $tpm_word = $new_word.$_cur_c;
                    if( $this->_finally_result[$i+1]['l']==1 && $this->_finally_result[$i+1]['t']==1 
                        && !isset($this->_addon_dic['s'][$_cur_c]) && !isset($this->_addon_dic['af'][$_cur_c])
                        && (isset($this->_finally_result[$i+2]) && !isset($this->_addon_dic['es'][$this->_finally_result[$i+2]['w']]))
                        || (isset($this->_new_words[$tpm_word]) && !preg_match('/[m]/', $this->_new_words[$tpm_word][1])) )
                    {
                        $new_word .= $_cur_c;
                        unset($this->_finally_result[$i]);
                        $i++;
                        $next_w = isset($this->_finally_result[$i+1]) ? $this->_finally_result[$i+1]['w'] : '  ';
                        if(  isset($this->_new_words[$new_word]) && !$this->_has_suffix($next_w) )
                        {
                            break;
                        }
                    }
                    else
                    {
                        break;
                    }
                }
                else
                {
                    break;
                }
                $j++;
            }
            if( strlen($new_word) / 2 > $cur_w['l'] )
            {
                $this->_finally_result[$i]['w'] = $new_word;
                $this->_finally_result[$i]['l'] = strlen($new_word) / 2;
                $this->_finally_result[$i]['t'] = 53;
                $this->_finally_result[$i]['d'] = 2;
                $this->_finally_result[$i]['m'] = 'x';
                if( !isset($this->_new_words[$new_word]) ) {
                    $rank = $this->_finally_result[$i]['l'] > 2 ? 3 : 9;
                    $this->_set_new_word($new_word, array($this->rank_step * $rank, 'x'));
                }
                return true;
            }
            else
            {
                return false;
            }
        }
    }
    
    /**
     * 尝试检测后缀名(通常是地名)
     * @parem $i
     * @param $res
     * @return boolean (是否匹配到新词)
     */
    protected function _optimize_test_suffix(&$i, &$res)
    {
        $cur_pos = $i;
        $cur_w  = $res[$i];
        if( $res[$i]['m'] == 'nr' || preg_match("/[ordcmtb]/", $res[$i]['m']) ) {
            return false;
        }
        if( !isset($this->_addon_dic['s'][ $cur_w['w'] ]) 
           && ($cur_w['l'] > 1 && !isset($this->_addon_dic['a'][ substr($cur_w['w'], -2 , 2) ])) )
        {
            $t = 17;
            $pre_word = $cur_w['w'];
            //向前合并单字
            for($k = 1; $k < 4; $k++)
            {
                  if( isset($res[$i-$k]) && $res[$i-$k]['t']==1 && $res[$i-$k]['l']==1 
                     && !isset($this->_addon_dic['s'][$res[$i-$k]['w']]) && !isset($this->_addon_dic['a'][$res[$i-$k]['w']]) 
                     && !isset($this->_addon_dic['z'][$res[$i-$k]['w']])&& !isset($this->_addon_dic['e'][$res[$i-$k]['w']]) )
                  {
                        $pre_word = $res[$i-$k]['w'].$pre_word;
                        if( !$this->max_split ) {
                            array_pop($this->_finally_result);
                        }
                   }
                   else
                   {
                        break;
                   }
            }
            $next_w = $res[$cur_pos + 1];
            if( isset($this->_addon_dic['a'][ $next_w['w'] ]) ) {
                $type = 'na';
                $this->_set_new_word($pre_word.$next_w['w'], array($this->rank_step * 2, $type));
            }
            elseif( isset($this->_addon_dic['z'][ $next_w['w'] ]) ) {
                $type = 'nz';
                $this->_set_new_word($pre_word.$next_w['w'], array($this->rank_step * 4, $type));
            } else {
                $t = 18;
                $type = 'ne';
                $this->_set_new_word($pre_word.$next_w['w'], array($this->rank_step * 6, $type));
            }
            //echo $this->_get_out_encoding( $pre_word.chr(0).','.$next_w['w'] ), '<br>';
            $this->_finally_result[] = array('w' => $pre_word.$next_w['w'], 't' => $t, 'd' => 2, 'l' => strlen($pre_word.$next_w['w'])/2, 'm' => $type  );
            if( $this->max_split )  $this->_finally_result[] = array('w' => $next_w['w'], 't' => 1, 'd' => 1, 'l' => strlen($next_w['w'])/2  );
            $i++;
            return true;
        } else {
            return false;
        }
    }
    
    /**
     * 尝试检测人名
     * @parem $i
     * @param $res
     * @return boolean (是否匹配到新词)
     */
    protected function _optimize_test_name(&$i, &$res)
    {
        $cur_pos = $i;
        $cur_w  = $res[$i];
        $next_w = $res[$i+1];
        
        //中断词的姓，只在段落开始才用
        if( isset($this->_addon_dic['nh'][$cur_w['w']]) && $res[$i-1]['t'] != 250 )
        {
            return false;
        }
        //echo $this->_out($cur_w['w']), '--', $res[$i-1]['t'], '<br>';
        
        //称呼
        if( isset($this->_addon_dic['ns'][$next_w['w']]) )
        {
            $this->_finally_result[] = array('w' => $cur_w['w'].$next_w['w'], 't' => 16, 'd' => 2, 'l' => strlen($cur_w['w'].$next_w['w'])/2, 'm' => 'nr');
            $this->_set_new_word($cur_w['w'].$next_w['w'], array($this->rank_step * 20, 'nr'));
            $res[$i]['m'] = 'nr';
            $i++;
        }
        //下一个词超过两个汉字，如果为nr属性才组合
        else if( $next_w['l'] > 2 )
        {
            if( $next_w['m'] == 'nr' ) {
                $this->_finally_result[] = array('w' => $cur_w['w'].$next_w['w'], 't' => 15, 'd' => 2, 'l' => strlen($cur_w['w'].$next_w['w'])/2, 'm' => 'nr');
                $this->_set_new_word($cur_w['w'].$next_w['w'], array($this->rank_step * 3, 'nr'));
                $res[$i]['m'] = 'nr';
                $i++;
            } else {
                return false;
            }
        }
        //下一个为双字词
        else if( $next_w['l'] == 2 )
        {
            //词语是副词或介词、连词等杂词不作为名字
            //|| (intval(bin2hex($cur_w['w'])) == 0x66FE && preg_match("/v/", $next_w['m']))  ||  intval(bin2hex($cur_w['w'])) == 0x4E8E  )
            //上一个词为250通常表示段落的结尾
            //echo $this->_out( $next_w['w'] ).'|'.$next_w['m'].'|'.$res[$i-1]['t']."<br>";
            if( $next_w['m'] != 'nr' && preg_match('/[vdmtrco]/', $next_w['m']) )
            {
                return false;
            }
            else
            {
                $this->_finally_result[] = array('w' => $cur_w['w'].$next_w['w'], 't' => 15, 'd' => 2, 'l' => strlen($cur_w['w'].$next_w['w'])/2, 'm' => 'nr');
                $this->_set_new_word($cur_w['w'].$next_w['w'], array($this->rank_step * 3, 'nr'));
                //为了防止错误，保留合并前的姓名
                if( $this->max_split ) {
                    $this->_finally_result[] = array('w' => $cur_w['w'], 't' => 1, 'd' => 1, 'l' => strlen($cur_w['w'])/2);
                    $this->_finally_result[] = array('w' => $next_w['w'], 't' => 1, 'd' => 1, 'l' => strlen($next_w['w'])/2);
                }
                $res[$i]['m'] = 'nr';
                $i++;
            }
        }
        //单字或多字组合
        else if( !isset($this->_addon_dic['s'][$next_w['w']]) && !isset($this->_addon_dic['se'][$next_w['w']]) )
        {
            $max = 5;
            $tmpword = $cur_w['w'].$next_w['w'];
            if( $this->max_split )  {
                $this->_finally_result[] = array('w' => $next_w['w'], 't' => 1, 'd' => 1, 'l' => strlen($cur_w['w'])/2 );
            }
            $i++;
            $num = isset($this->_addon_dic['nf'][$cur_w['w']]) ? $max-2 : 1;
            //echo $this->_out($cur_w['w'])."|{$num}--";
            for($_i=0; $_i < $num; $_i++) //人名最多为五个字
            {
                $cinfo = [];
                if( isset($res[$i+1]) && $res[$i+1]['t']==1 && strlen($tmpword)/2+$res[$i+1]['l'] <= $max )
                {
                    //非单字符，只组合属于名字类型的(通常是外籍名)
                    //echo $this->_out($tmpword).'--'.$this->_out($res[$i+1]['w'])."(".$res[$i+1]['l']."/".$res[$i+1]['m'].")<br />";
                    if( $res[$i+1]['l'] > 1 && $res[$i+1]['m']=='nr' )
                    {
                        $tmpword .= $res[$i+1]['w'];
                        $i++;
                        if( $this->max_split )  {
                            $this->_finally_result[] = array('w' => $res[$i+1]['w'], 't' => 1, 'd' => 1, 'l' => strlen($res[$i+1]['w'])/2 );
                        }
                    }
                    else if( $res[$i+1]['l'] == 1  && !isset($this->_addon_dic['s'][$res[$i+1]['w']]) 
                      && !isset($this->_addon_dic['se'][$res[$i+1]['w']]) )
                    {
                        $tmpword .= $res[$i+1]['w'];
                        if( $this->max_split )  {
                            $this->_finally_result[] = array('w' => $res[$i+1]['w'], 't' => 1, 'd' => 1, 'l' => strlen($res[$i+1]['w'])/2 );
                        }
                        $i++;
                        //可能是地名
                        if( $this->_has_suffix($res[$i+1]['w']) ) {
                            break;
                        }
                    }
                    else
                    {
                        break;
                    }
                }
                //如果上面已经匹配了一个更短的名字，中断匹配，保障词条的统一性
                if( isset($this->_names[$tmpword]) ) {
                    break;
                }
            }
            if( isset($this->_addon_dic['a'][$res[$i]['w']]) ) {
                $type = 'na';
            } else {
                $type = 'nr';
            }
            $res[$i]['m'] = 'nr';
            $this->_names[$tmpword] = $tmpword;
            $this->_set_new_word($tmpword, array($this->rank_step * 3, $type));
            $this->_finally_result[] = array('w' => $tmpword, 't' => 15, 'd' => 2, 'l' => strlen($tmpword)/2, 'm' => $type );
        }
        return $cur_pos==$i ? false : true;
    }
     
    
    /**
     * 尝试合并数量词
     * @parem $i
     * @param $res
     * @return boolean (是否匹配到新词)
     */
    protected function _optimize_test_quantity(&$i, &$res)
    {
        $cur_pos = $i;
        $numstr = $res[$i]['w'];
        if( $this->max_split )  {
            $this->_finally_result[] = array('w' => $numstr, 't' => 31, 'd' => 1, 'l' => strlen($numstr)/2 );
        }
        //echo $this->_out($res[$i]['w']).' -- '.$this->_out($res[$i+1]['w']).'<br>';
        //测试后五个进行组合
        for($j=1; $j < 8; $j++)
        {
            if( !isset($res[$i+$j]) ) break;
            if( $res[$i+$j]['t'] == 250 && (isset($res[$i+$j+1]) && $res[$i+$j+1]['t']==1) ) {
                continue;
            }
            if( isset( $this->_addon_dic['m'][$res[$i+$j]['w']] ) || $res[$i+$j]['t'] == 4 )
            {
                $numstr .= $res[$i+$j]['w'];
                if( $this->max_split )  {
                    $this->_finally_result[] = array('w' => $res[$i+$j]['w'], 't' => 31, 'd' => 1, 'l' => strlen($numstr)/2 );
                }
            }
            else
            {
                if( $res[$i+$j]['t'] == 250 && (isset($res[$i+$j+1]) && $res[$i+$j+1]['t'] != 4) )
                {
                    //$numstr .= $res[$i+$j]['w'];
                    continue;
                }
                else {
                    break;
                }
            }
        }
        $i += $j - 1;
        //检测后面是否有单位词
        if( isset($res[$i+1]) && isset($this->_addon_dic['ms'][$res[$i+1]['w']]) )
        {
             $numstr .= $res[$i+1]['w'];
             if( $this->max_split )  {
                $this->_finally_result[] = array('w' => $res[$i+1]['w'], 't' => 31, 'd' => 1, 'l' => strlen($numstr)/2 );
             }
             //防止后缀识别操作调用当前数量词
             $res[$i+1]['t'] = 14;
             $i++;
        }
        if( $i > $cur_pos )
        {
            if( $numstr != $res[$cur_pos]['w'] ) {
                $res[$i]['m'] = 'm';
                $this->_finally_result[] = array('w' => $numstr, 't' => 13, 'd' => 1, 'l' => strlen($numstr)/2, 'm' => 'mu' );
                $this->_set_new_word($numstr, array($this->rank_step * 20, 'mu'));
            } else {
                $this->_finally_result[] = $res[$cur_pos];
            }
            return true;
        } else {
             return false;
        }
    }
    
    /**
     * 检查词条是否属于字典里的词条
     * @parem $word (unicode编码)
     * @return boolean
     */
    protected function _is_dic_word( $word )
    {
        $infos = $this->_get_words( $word );
        return ($infos !== false);
    }
    
    //检测中断词
    protected function _has_suffix($w, $max_len=0, $len=0)
    {
        if( isset($this->_addon_dic['a'][$w]) || isset($this->_addon_dic['z'][$w]) || isset($this->_addon_dic['e'][$w]) )
        {
            if( $max_len > 0 && $len < $max_len) {
                return false;
            } else {
                return true;
            }
        }
        else
        {
            return false;
        }             
    }
    
    /**
     * 从字典里获取词条信息
     * @parem $word (unicode编码)
     * @return boolean
     */
    protected function _get_words( $word, $use_new_word = false )
    {
        if( $word=='' ) return array();
        if( $use_new_word )
        {
            if( isset($this->_new_words[$word]) ) {
                return $this->_new_words[$word];
            }
        }
        $keynum = $this->_get_index( $word );
        if( !isset($this->_main_dic[$keynum]['cn']) )
        {
            $this->_main_dic[$keynum]['cn'] = $this->_dic_read($this->_main_dic_hand, $keynum);
        }
        return isset($this->_main_dic[$keynum]['cn'][$word]) ? $this->_main_dic[$keynum]['cn'][$word] : false;
    }
    
    /**
     * 从字典里获取词条信息
     * @parem $word (unicode编码)
     * @return boolean
     */
    protected function _get_english_words( $word )
    {
        $keynum = $this->_get_index( $word );
        if( !isset($this->_main_dic[$keynum]['en']) )
        {
            $this->_main_dic[$keynum]['en'] = $this->_dic_read($this->_en_dic_hand, $keynum);
        }
        return isset($this->_main_dic[$keynum]['en'][$word]) ? $this->_main_dic[$keynum]['en'][$word] : false;
    }
    
    /**
     * 指定某词的词性信息（通常是新词）
     * @parem $word unicode编码的词
     * @parem $infos array(自定义的词频, 词性);
     * @return void;
     */
    public function _set_new_word($word, $infos)
    {
        if( strlen($word) < 4 )
        {
            return ;
        }
        if( !isset($this->_new_words[$word]) )
        {
            $dinfo = $this->_get_words( $word );
            if( $dinfo !== false ) {
                $this->_new_words[$word] = $dinfo;
            }
            else {
                $this->_new_words[$word] = $infos;
            }
        }
    }
    
    /**
    * 检测 finally_result 数组词汇的属性
    * 需要完成分词动作后计算rank或输出词性的时候才需要进行这个操作
    * @return void
    */
    protected function _check_word_property()
    {
        if( !empty($this->_property_result) ) {
            return $this->_property_result;
        }
        $all_count = count( $this->_finally_result );
        $is_head = false;
        $n = 1;
        foreach($this->_finally_result as  $k => $w)
        {
            if( $w['t'] == 250 ) continue;
            //防止逻辑中没有包含len的情况 
            if( !isset($w['l']) )  {
                $this->_finally_result[$k]['l'] = strlen($w['w']) / 2;
            }
            $info = $this->_get_words( $w['w'] );
            if( $info != FALSE )
            {
                //对含有特定属性的关键字强行降权
                if( preg_match("/[kadmtqrco]/", $info[1]) && $info[1] != 'nr' ) {
                    $info[0] = $this->rank_step * 50;
                }
                $this->_property_result[ $w['w'] ] = $info;
            }
            else if( isset($this->_new_words[ $w['w'] ]) )
            {
                $this->_property_result[ $w['w'] ] = $this->_new_words[ $w['w'] ];
            }
            else
            {
                 $uword = $this->_get_out_encoding( $w['w'] );
                 $luword = strtolower($uword);
                 //没意义的符号或高频的英语或单个汉字
                 if( strlen($uword) < 2 || preg_match("/[@#_%\+\-]/", $uword) || 
                   ( strlen($uword) < 4 && !preg_match("/[a-z]/", $luword) ) )
                  {
                      //echo $this->_out($w['w']).'--';
                      $this->_property_result[ $w['w'] ] = array($this->rank_step*10, 's');
                  }
                  //英语权重词
                  elseif( $w['t'] == 2  )
                  {
                      $einfo = $this->_get_english_words( $w['w'] );
                      if( $einfo )
                      {
                          //考虑系统分词并不是以中文为主，降低常规英文词条的权重
                          if( !preg_match("/^[A-Z]/", $uword) ) $einfo[0] = ceil($einfo[0] * 2);
                          $this->_property_result[ $w['w'] ] = $einfo;
                      }
                      //没在词典的单词
                      else  
                      {
                          //小于4个字节
                          if( strlen($uword) < 4 )  //纯数字或数英混合
                          {
                               $this->_property_result[ $w['w'] ] = array($this->rank_step*10, 's');
                          }
                          //首个字母为大小并且长度超过3的词
                          elseif( preg_match("/^[A-Z]/", $uword) )
                          {
                              //英数组合（通常是某种型号）
                              if( preg_match("/[0-9]$/", $uword) ) {
                                  $this->_property_result[ $w['w'] ] = array($this->rank_step*4, 'n');
                              } 
                              //大写开头的加强词汇或人名等
                              else {
                                  $this->_property_result[ $w['w'] ] = array($this->rank_step*3, 'n');
                              }
                          }
                          else
                          {
                               //通常是 111cm 之类的词或超过3位的纯数字
                               if( preg_match("/[^A-Za-z]/", $uword) ) 
                               {
                                   $this->_property_result[ $w['w'] ] = array($this->rank_step*10, 's');
                               }
                               //词典内没有的普通英文(认为是低频词)
                               else
                               {
                                   $this->_property_result[ $w['w'] ] = array($this->rank_step*3, 'e');
                               }
                           }
                      }
                      
                  }
                  //书名
                  elseif( $w['t'] == 8 )
                  {
                      $r = $k < 10 ? 1 : 3;
                      $this->_property_result[ $w['w'] ] = array($this->rank_step * $r, 'nb');
                  }
                  //常规英文或其它字符
                  else
                  {
                        $this->_property_result[ $w['w'] ] = array($this->rank_step*10, 's');
                  }
              } //end not new word
              //增加靠前的词权重
              if( $all_count > 200 && $n / $all_count < 0.2 )
              {
                  if( $all_count > 2000 && $n / $all_count < 0.1 ) {
                      $this->_property_result[ $w['w'] ][0]  = $this->_property_result[$w['w']][0] * 0.4;
                  } else {
                      $this->_property_result[ $w['w'] ][0]  = $this->_property_result[$w['w']][0] * 0.5;
                  }
              }
              //增加地名权重 
              if( $this->_property_result[ $w['w'] ][1] == 'ns') {
                  $this->_property_result[ $w['w'] ][0]  = $this->_property_result[$w['w']][0] * 0.5;
              }
        } //end for
    }
    
    /**
     * 编译词典
     * 注意: 需要足够的内存才能完成操作
     * @param $source_file    词典源文件必须为绝对路径 
     * @param $target_file    如果为空，默认更新主词典
     * @return void
     */
     public function AssistBuildDict( $source_file, $target_file='' )
     {
        $target_file = ($target_file=='' ? $this->_dic_root.'/'.$this->_main_dic_file : $target_file);
        $allk = array();
        $fp = fopen($source_file, 'r') or die("build_dic load file: {$source_file} error!");
        while( $line = fgets($fp, 512) )
        {
            if( $line[0]=='@' ) continue;
            list($w, $r, $a) = explode(',', $line);
            if( !is_numeric($r) ) {
                echo '"', $w, "\" error count not numeric<br>\n";
                exit();
            }
            $w = $this->ConvertEncoding('utf-8', _PA_UCS2_, $w);
            $k = $this->_get_index( $w );
            if( isset($allk[ $k ]) )
                $allk[ $k ][ $w ] = array($r, $a);
            else
                $allk[ $k ][ $w ] = array($r, $a);
        }
        fclose( $fp );
        $fp = fopen($target_file, 'w') or die("build_dic create file: {$target_file} error!");
        chmod($target_file, 0666);
        $heade_rarr = array();
        $alldat = '';
        $start_pos = $this->_mask_value * 8;
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
        for($i=0; $i < $this->_mask_value; $i++)
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
     * 导出词典中的词条
     * @parem $target_file 导出的文件保存位置（绝对路径）
     * @param $dicfile 词典文件(如果不指定，则默认会尝试使用默认加载的主词典，因此如果编译的不是主词典，此项必须指定)
     * @return void
     */
     public function AssistExportDict( $target_file, $dicfile = '' )
     {
        if( $dicfile == '' )
        {
            if( !$this->_main_dic_hand )
            {
                $this->LoadDict();
            }
            $FPD = $this->maindicHand;
        }
        else
        {
            $FPD = fopen($dicfile, 'r') or die("Exportdict open dicfile:{$dicfile} error!");
        }
        $fp = fopen($target_file, 'w') or die("Exportdict create targetfile: {$targetfile} error!");
        chmod($target_file, 0666);
        for($i=0; $i <= $this->_mask_value; $i++)
        {
            //读取数据
            $move_pos = $i * 8;
            fseek($FPD, $move_pos, SEEK_SET);
            $dat = fread($FPD, 8);
            $arr = unpack('I1s/n1l/n1c', $dat);
            if( $arr['l'] == 0 )
            {
                continue;
            }
            fseek($FPD, $arr['s'], SEEK_SET);
            $data = @unserialize(fread($FPD, $arr['l']));
            if( !is_array($data) ) continue;
            //保存导出的数据
            foreach($data as $k => $v)
            {
                $k = $this->ConvertEncoding(_PA_UCS2_, 'utf-8', $k);
                fwrite($fp, "{$k},{$v[0]},{$v[1]}\n");
            }
        }
        fclose( $fp );
        return true;
     }
    
    /**
     * 根据字符串计算key索引
     * @param $key
     * @return short int
     */
    protected function _get_index( $key )
    {
        $l = strlen($key);
        $h = 0x238f13af;
        while ($l--)
        {
            $h += ($h << 5);
            $h ^= ord($key[$l]);
            $h &= 0x7fffffff;
        }
        return ($h % $this->_mask_value);
    }
    
   /***
    * 从词典里读取一个hash对应的词条(返回是这个hash对应的一组词)
    * @param $keynum  由key生成的hash
    * @return array $data
    */
    public function _dic_read(&$fp, $keynum)
    {
        $move_pos = $keynum * 8;
        fseek($fp, $move_pos, SEEK_SET);
        $dat = fread($fp, 8);
        $arr = unpack('I1s/n1l/n1c', $dat);
        if( $arr['l'] == 0 ) {
            return [];
        }
        fseek($fp, $arr['s'], SEEK_SET);
        $data = @unserialize(fread($fp, $arr['l']));
        return empty($data) ? [] : $data;
    }
    
}
