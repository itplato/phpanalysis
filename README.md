# PHPAnalysis php 无组件中文分词类

#一、比较重要的成员变量
$resultType   = 1        生成的分词结果数据类型(1 为全部， 2为 词典词汇及单个中日韩简繁字符及英文， 3 为词典词汇及英文)
                                    这个变量一般用 SetResultType( $rstype ) 这方法进行设置。
$notSplitLen  = 5        切分句子最短长度
$toLower      = false    把英文单词全部转小写
$differMax    = false    使用最大切分模式对二元词进行消岐
$unitWord     = true     尝试合并单字(即是新词识别)
$differFreq   = false    使用热门词优先模式进行消岐

#二、主要成员函数列表
1、public function __construct($source_charset='utf-8', $target_charset='utf-8', $load_all=true, $source='') 
函数说明：构造函数
参数列表：
$source_charset      源字符串编码
$target_charset      目录字符串编码
$load_all            是否完全加载词典（此参数已经作废）
$source              源字符串
如果输入输出都是utf-8，实际上可以不必使用任何参数进行初始化，而是通过 SetSource 方法设置要操作的文本
2、public function SetSource( $source, $source_charset='utf-8', $target_charset='utf-8' )
函数说明：设置源字符串
参数列表：
$source              源字符串
$source_charset      源字符串编码
$target_charset      目录字符串编码
返回值：bool
3、public function StartAnalysis($optimize=true)
函数说明：开始执行分词操作
参数列表：
$optimize            分词后是否尝试优化结果
返回值：void
一个基本的分词过程：
//////////////////////////////////////
$pa = new PhpAnalysis();

$pa->SetSource('需要进行分词的字符串');

//设置分词属性
$pa->resultType = 2;
$pa->differMax  = true;

$pa->StartAnalysis();

//获取你想要的结果
$pa->GetFinallyIndex();
////////////////////////////////////////
4、public function SetResultType( $rstype )
函数说明：设置返回结果的类型
实际是对成员变量$resultType的操作
参数 $rstype 值为：
1 为全部， 2为 词典词汇及单个中日韩简繁字符及英文， 3 为词典词汇及英文
返回值：void
5、public function GetFinallyKeywords( $num = 10 )
函数说明：获取出现频率最高的指定词条数（通常用于提取文档关键字）
参数列表：
$num = 10  返回词条个数
返回值：用","分隔的关键字列表
6、public function GetFinallyResult($spword=' ')
函数说明：获得最终分词结果
参数列表：
$spword    词条之间的分隔符
返回值：string
7、public function GetSimpleResult()
函数说明：获得粗分结果
返回值：array
8、public function GetSimpleResultAll()
函数说明：获得包含属性信息的粗分结果
属性（1中文词句、2 ANSI词汇（包括全角），3 ANSI标点符号（包括全角），4数字（包括全角），5 中文标点或无法识别字符）
返回值：array
9、public function GetFinallyIndex()
函数说明：获取hash索引数组
返回值：array('word'=>count,...) 按出现频率排序
10、public function MakeDict( $source_file, $target_file='' )
函数说明：把文本文件词库编译成词典
参数列表：
$source_file   源文本文件
$target_file   目标文件(如果不指定，则为当前词典)
返回值：void
11、public function ExportDict( $targetfile )
函数说明：导出当前词典全部词条为文本文件
参数列表：
$targetfile  目标文件
返回值：void
