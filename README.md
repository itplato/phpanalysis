PHPAnalysis php中文无组件分词类
===========================
一、比较重要的成员变量
--------------------------
<pre>
$notSplitLen  = 5       初切分句子的最短长度

//这个变量可以用 SetResultType( $rstype ) 这方法进行设置。
$resultType   = 1       生成的分词结果数据类型(1 为全部， 2为 词典词汇及单个中日韩简繁字符及英文及[.@#+-])

//这几个变量可以直接用 SetOptimizeParams() 统一设置
$differMax = FALSE      使用最大切分模式对二元词进行消岐
$unitWord = TRUE        尝试合并单字为新词
$differFreq = FALSE     使用热门词优先模式进行消岐
$optimizeResult = TRUE   对分词后的结果尝试优化
</pre>
二、主要成员函数列表
------------------------
* __construct($source_charset='utf-8', $target_charset='utf-8', $load_all=true, $source='') 
<pre>
函数说明：构造函数
参数列表：
string $source_charset      源字符串编码
string $target_charset      目录字符串编码
bool   $load_all            是否完全加载词典（此参数已经作废）
string $source              源字符串(如果类初始使化后是要进行多次分词的，不建议一开始设定这个值)
如果输入输出都是utf-8，实际上可以不必使用任何参数进行初始化，而是通过 SetSource 方法设置要操作的文本
</pre>
* SetSource( $source, $source_charset='utf-8', $target_charset='utf-8' )
<pre>
函数说明：设置源字符串
参数列表：
string $source              源字符串
string $source_charset      源字符串编码
string $target_charset      目录字符串编码
返回值：bool
</pre>
* SetOptimizeParams( $differMax = FALSE, $unitWord = TRUE, $optimizeResult = TRUE, $differFreq = FALSE )
<pre>
函数说明：设置分词参数
参数列表：
bool $differMax        是否进行最大切分
bool $unitWord         是否尝试合并单字为新词
bool $optimizeResult   是否优化分词后的结果（前后词对比岐义处理）
bool $differFreq       是否使用热门词优先模式进行消岐
返回值：void
</pre>
* StartAnalysis($optimize=true)
<pre>
函数说明：开始执行分词操作
参数列表：
bool $optimize            分词后是否尝试优化结果
返回值：void
</pre>
* SetResultType( $rstype )
<pre>
函数说明：设置返回结果的类型(实际是对成员变量$resultType的操作)
参数列表：
int $rstype 值为：1 为全部， 2为 词典词汇及单个中日韩简繁字符及英文
返回值：void
</pre>
* GetFinallyResult($spword=' ')
<pre>
函数说明：获取用指定字符串分隔的分词结果
参数列表：
string $spword    词条之间的分隔符
返回值：string
</pre>
* GetFinallyIndex( $sortby='count' )
<pre>
函数说明：获取hash索引模式的结果数组
参数列表：
string $sortby    排序方式 (count 出现频繁  rank tf-idf权重试验)
返回值：array('word'=>count|rank,...)
</pre>
* GetFinallyKeywords( $num = 10, $sortby='count' )
<pre>
函数说明：获取出现频率最高的指定词条数（通常用于提取文档关键字，实际相当于对 GetFinallyIndex 结果进行限制）
参数列表：
int $num = 10  返回词条个数
string $sortby    排序方式 (count 出现频繁  rank tf-idf权重试验)
返回值：array
</pre>
* GetSimpleResult()
<pre>
函数说明：获得粗分结果
返回值：array
</pre>
* GetSimpleResultAll()
<pre>
函数说明：获得包含属性信息的粗分结果
属性（1中文词句、2 ANSI词汇（包括全角），3 ANSI标点符号（包括全角），4数字（包括全角），5 中文标点或无法识别字符）
返回值：array
</pre>
* MakeDict( $source_file, $target_file='' )
<pre>
函数说明：把文本文件词库编译成词典
参数列表：
string $source_file   源文本文件
string $target_file   目标文件(如果不指定，则为当前打开的词典)
返回值：void
</pre>
* ExportDict( $targetfile, $dicfile='' )
<pre>
函数说明：导出当前词典全部词条为文本文件
参数列表：
string $targetfile  目标文件
string $dicfile     原字典文件（如果为空，则默认用类加载的主词典）
返回值：void
* GetWordInfos( $key )
<pre>
函数说明：获取主词典某词条的属性
参数列表：
string $key   词条(unicode编码)
返回值：array(0 => 频率, 1=> 词性)
</pre>
* GetEnWordInfos( $key )
<pre>
函数说明：获取英文主词典某词条的属性
参数列表：
string $key   词语(ascii编码)
返回值：array(0 => 频率, 1=> 词性)
</pre>
* GetWordProperty( $key )
<pre>
函数说明：获取某词条的属性
        这个和GetWordInfos的区别是它必须完成分词动作后才能使用，
        它不仅能获取词典词条的属性，还能获取当前分词时系统自动周处理的词的属性
参数列表：
string $key   词条
返回值：array(0 => 频率, 1=> 词性)
</pre>
三、实例演示
-------------------
* 常规分词
```php
<?php
require_once 'Phpanalysis/Phpanalysis.php';
header('content-type:text/html;charset=utf-8');
echo "<xmp>";

//utf-8编码
$str = "2010年1月，美国国际消费电子展 (CES)上，联想将展出一款基于ARM架构的新产品，这有可能是
       传统四大PC厂商首次推出的基于ARM架构的消费电子产品，也意味着在移动互联网和产业融合趋势下，
       传统的PC芯片霸主英特尔正在遭遇挑战。";

//初始化(如果同一进程需多次分词，不要初始化多个实例，否则无法利用内存缓存词典)
$pa = new PhpAnalysis();
//设置优化参数
$pa->SetOptimizeParams( FALSE, TRUE, TRUE, FALSE );

//设置分割的字符串
$pa->SetSource( $str );
$pa->StartAnalysis();

//常规分词结果
$split_str = $pa->GetFinallyResult( ' ' );
echo $split_str;

//获取按权重排序词条
$arr = $pa->GetFinallyKeywords( 10, 'rank' );
print_r( $arr );
?>
```
* 重新生成词典
```php
<?php
require_once 'Phpanalysis/Phpanalysis.php';
header('content-type:text/html;charset=utf-8');

$pa = new PhpAnalysis();
$pa->MakeDict( "Phpanalysis/dict/not-build/base_dic_full.txt" );

echo "OK";
?>
```
* SEO提词器(使用这种切分模式支持英文词汇，但是这个纯粹是切词，不在词典的词条会自动放弃)
```php
<?php
require_once 'Phpanalysis/Phpanalysis.php';
header('content-type:text/html;charset=utf-8');

$str = "2010年1月，美国国际消费电子展 (CES)上，联想将展出一款基于ARM架构的新产品，这有可能是
       传统四大PC厂商首次推出的基于ARM架构的消费电子产品，也意味着在移动互联网和产业融合趋势下，
       传统的PC芯片霸主英特尔正在遭遇挑战。";

//初始化(如果同一进程需多次分词，不要初始化多个实例，否则无法利用内存缓存词典)
$pa = new PhpAnalysis();
$pa->LoadDict( "Phpanalysis/dict/base_dic_seo.dic" );


$words = $pa->GetSeoResult( "rank" );

echo '<xmp>';
print_r( $words );
?>
```