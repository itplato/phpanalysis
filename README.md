PHPAnalysis类API文档
===========================
一、比较重要的成员变量
--------------------------
<pre>
$resultType   = 1        生成的分词结果数据类型(1 为全部， 2为 词典词汇及单个中日韩简繁字符及英文及[.@#+-])
                                    这个变量可以用 SetResultType( $rstype ) 这方法进行设置。

$notSplitLen  = 5        初切分句子的最短长度
</pre>
二、主要成员函数列表
------------------------
* public function __construct($source_charset='utf-8', $target_charset='utf-8', $load_all=true, $source='') 
<pre>
函数说明：构造函数
参数列表：
$source_charset      源字符串编码
$target_charset      目录字符串编码
$load_all            是否完全加载词典（此参数已经作废）
$source              源字符串
如果输入输出都是utf-8，实际上可以不必使用任何参数进行初始化，而是通过 SetSource 方法设置要操作的文本
</pre>
* public function SetSource( $source, $source_charset='utf-8', $target_charset='utf-8' )
<pre>
函数说明：设置源字符串
参数列表：
$source              源字符串
$source_charset      源字符串编码
$target_charset      目录字符串编码
返回值：bool
</pre>
* public function SetOptimizeParams( $differMax = FALSE, $unitWord = TRUE, $optimizeResult = TRUE, $differFreq = FALSE )
<pre>
函数说明：设置分词参数
参数列表：
$differMax        最大切分
$unitWord         尝试合并单字为新词
$optimizeResult   优化分词结果
$differFreq       使用热门词优先模式进行消岐
返回值：void
</pre>
* public function StartAnalysis($optimize=true)
<pre>
函数说明：开始执行分词操作
参数列表：
$optimize            分词后是否尝试优化结果
返回值：void
</pre>
* public function SetResultType( $rstype )
<pre>
函数说明：设置返回结果的类型
实际是对成员变量$resultType的操作
参数 $rstype 值为：
1 为全部， 2为 词典词汇及单个中日韩简繁字符及英文， 3 为词典词汇及英文
返回值：void
</pre>
* public function GetFinallyResult($spword=' ')
<pre>
函数说明：获取用指定字符串分隔的分词结果
参数列表：
$spword    词条之间的分隔符
返回值：string
</pre>
* public function GetFinallyIndex( $sortby='count' )
<pre>
函数说明：获取hash索引模式的结果数组
$sortby    排序方式 (count 出现频繁  rank tf-idf权重试验)
返回值：array('word'=>count|rank,...)
</pre>
* public function GetFinallyKeywords( $num = 10, $sortby='count' )
<pre>
函数说明：获取出现频率最高的指定词条数（通常用于提取文档关键字，实际相当于对 GetFinallyIndex 结果进行限制）
参数列表：
$num = 10  返回词条个数
$sortby    排序方式 (count 出现频繁  rank tf-idf权重试验)
返回值：array
</pre>
* public function GetSimpleResult()
<pre>
函数说明：获得粗分结果
返回值：array
</pre>
* public function GetSimpleResultAll()
<pre>
函数说明：获得包含属性信息的粗分结果
属性（1中文词句、2 ANSI词汇（包括全角），3 ANSI标点符号（包括全角），4数字（包括全角），5 中文标点或无法识别字符）
返回值：array
</pre>
* public function MakeDict( $source_file, $target_file='' )
<pre>
函数说明：把文本文件词库编译成词典
参数列表：
$source_file   源文本文件
$target_file   目标文件(如果不指定，则为当前词典)
返回值：void
</pre>
* public function ExportDict( $targetfile )
<pre>
函数说明：导出当前词典全部词条为文本文件
参数列表：
$targetfile  目标文件
返回值：void
</pre>