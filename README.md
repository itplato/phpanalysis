PHPAnalysis php中文无组件分词类
===========================
一、最新变化
--------------------------
<pre>
1、修改源文件结构支持composer
2、把切分同时优化的操作模式改为独立步骤操作(即是粗分、切分、优化三步完全独立)
3、修改类成员调用支持自身引用，即是 xx()->xx()->xx() 模式
</pre>
二、一个基本的分词操作
------------------------
<pre>
use Tutu\PhpAnalysis;
header('content-type:text/html;charset=utf-8');
$result_str = PhpAnalysis::Instance()
              ->SetSource("composer的出现真是让人们眼前一亮，web开发从此变成了一件很『好玩』的事情。")
              ->Delimiter(' ')
              ->ExecSimpleAnalysis()
              ->ExecDeepAnalysis()
              ->Optimize( true );
echo $result_str;

如果用默认参数，上面也可以简化为：
$result_str = PhpAnalysis::Instance()
              ->SetSource("composer的出现真是让人们眼前一亮，web开发从此变成了一件很『好玩』的事情。")
              ->Exec();
</pre>
三、常用设置及方法
------------------------
* Instance( $force_init = false )
<pre>

</pre>
* SetOptions($unit_special_word=true, $unit_single_word=false, $max_split=false, $high_freq_priority=false, $optimize=true)
<pre>

</pre>
* SetSource($source, $source_encoding = 'utf-8', $target_encoding='utf-8')
<pre>

</pre>
* Delimiter( $str )
<pre>

</pre>
* Exec( $return = true )
<pre>

</pre>
* LoadDict( $main_dic_file = '' )
<pre>

</pre>
* AssistBuildDict( $source_file, $target_file='' )
<pre>

</pre>
* AssistExportDict( $target_file, $dicfile = '' )
<pre>

</pre>
* AssistGetCompare()
<pre>

</pre>
* AssistGetDeep()
<pre>

</pre>
* AssistGetSimple( $string=true )
<pre>

</pre>
* GetNewWords( $is_array=false )
<pre>

</pre>
* GetResult()
<pre>

</pre>
* GetResultProperty()
<pre>

</pre>
* GetTags( $num = 10, $with_rank = false )
<pre>

</pre>
```