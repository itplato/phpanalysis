<?php
// 严格开发模式
ini_set('display_errors', 'On');
ini_set('memory_limit', '128M');
error_reporting(E_ALL);

require_once '../Phpanalysis/Phpanalysis.php';

$t1 = $ntime = microtime(true);
$endtime = '未执行任何操作，不统计！';
function print_memory($rc, &$infostr)
{
    global $ntime;
    $cutime = microtime(true);
    $etime = sprintf('%0.4f', $cutime - $ntime);
    $m = sprintf('%0.2f', memory_get_usage()/1024/1024);
    $infostr .= "{$rc}: &nbsp;{$m} MB 用时：{$etime} 秒<br />\n";
    $ntime = $cutime;
}

header('Content-Type: text/html; charset=utf-8');

$memory_info = '';
print_memory('没任何操作', $memory_info);

$str = (isset($_POST['source']) ? $_POST['source'] : '');

$loadtime = $endtime1  = $endtime2 = $slen = 0;

$do_fork = $do_unit = true;
$do_multi = $do_prop = $do_seo = false;

//限制字数
$str = mb_substr($str, 0, 1024 * 3, 'UTF-8');

if($str != '')
{
    //岐义处理
    $do_fork = empty($_POST['do_fork']) ? false : true;
    //新词识别
    $do_unit = empty($_POST['do_unit']) ? false : true;
    //多元切分
    $do_multi = empty($_POST['do_multi']) ? false : true;
    //词性标注
    $do_prop = empty($_POST['do_prop']) ? false : true;
    
    //SEO提词
    $do_seo = empty($_POST['do_seo']) ? false : true;
    
    $tall = microtime(true);
    
    //初始化类
    $pa = new PhpAnalysis('utf-8', 'utf-8');
    print_memory('初始化对象', $memory_info);
    
    //载入词典
    $pa->LoadDict();
    print_memory('载入基本词典', $memory_info);  
    
    if( $do_seo )  
    {
        print_memory('执行分词', $memory_info);
        $okresult = '';
        $pa->LoadDict( "../PhpAnalysis/dict/base_dic_seo.dic" );
        $rs = $pa->GetSeoResult( $str, 'rank' );
        foreach($rs as $w => $c)
        {
            $okresult .= "{$w}/{$c} ";
        }
        $pa_foundWordStr = "";
    }
    else
    {    
        //执行分词
        $pa->SetResultType( 1 );
        $pa->SetOptimizeParams( $do_multi, $do_unit, $do_fork, FALSE );
    
        $pa->SetSource( $str );
        $pa->StartAnalysis( TRUE );
        print_memory('执行分词', $memory_info);

        
        //$words = $pa->GetFinallyIndex( 'rank' );
        $words = $pa->GetFinallyKeywords(20, 'rank');
        $rank_result = '';
        foreach($words as $w => $r )  $rank_result .= "{$w}/{$r} ";
        
        $str_result = $pa->GetFinallyResult(' ', $do_prop);
        print_memory('输出分词结果', $memory_info);
    
        $pa_foundWordStr = $pa->GetNewWrods();
    }
    
    
    
    $t2 = microtime(true);
    $endtime = sprintf('%0.4f', $t2 - $t1);
    
    $slen = strlen($str);
    $slen = sprintf('%0.2f', $slen/1024);
    
    $pa = '';
    
}

$teststr = "2010年1月，美国国际消费电子展 (CES)上，联想将展出一款基于ARM架构的新产品，这有可能是传统四大PC厂商首次推出的基于ARM架构的消费电子产品，也意味着在移动互联网和产业融合趋势下，传统的PC芯片霸主英特尔正在遭遇挑战。
11月12日，联想集团副总裁兼中国区总裁夏立向本报证实，联想基于ARM架构的新产品正在筹备中。
英特尔新闻发言人孟轶嘉表示，对第三方合作伙伴信息不便评论。
ARM内部人士透露，11月5日，ARM高级副总裁lanDrew参观了联想研究院，拜访了联想负责消费产品的负责人，进一步商讨基于ARM架构的新产品。ARM是英国芯片设计厂商，全球几乎95%的手机都采用ARM设计的芯片。
据悉，这是一款采用高通芯片(基于ARM架构)的新产品，高通产品市场总监钱志军表示，联想对此次项目很谨慎，对于产品细节不方便透露。
夏立告诉记者，联想研究院正在考虑多种方案，此款基于ARM架构的新产品应用邻域多样化，并不是替代传统的PC，而是更丰富的满足用户的需求。目前，客户调研还没有完成，“设计、研发更前瞻一些，最终还要看市场、用户接受程度。”";

?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>PhpAnalysis分词测试</title>
<style>
button { font-size:14px; }
</style>
</head>
<body>
<table width='80%' align='center'>
<tr>
    <td>    
<h3>PhpAnalysis分词测试 &nbsp; <a href="dictionary-builder.php"><span style="font-size:14px">[词典管理]</span></a></h3>
<hr size='1'>

<form id="form1" name="form1" method="post" action="?ac=done" style="margin:0px;padding:0px;line-height:24px;">
  <b>源文本：</b> <br>
    <textarea name="source" style="width:98%;height:180px;font-size:14px;"><?php echo (isset($_POST['source']) ? $_POST['source'] : $teststr); ?></textarea>
    <br/>
    <label><input type='checkbox' name='do_fork' value='1' <?php echo ($do_fork ? "checked='checked'" : ''); ?>/>岐义处理</label>
    <label><input type='checkbox' name='do_unit' value='1' <?php echo ($do_unit ? "checked='checked'" : ''); ?>/>新词识别</label>
    <label><input type='checkbox' name='do_multi' value='1' <?php echo ($do_multi ? "checked='checked'" : ''); ?>/>最大切分</label>
    <label><input type='checkbox' name='do_prop' value='1' <?php echo ($do_prop ? "checked='checked'" : ''); ?>/>词性标注</label>
    <label><input type='checkbox' name='do_seo' value='1' <?php echo ($do_seo ? "checked='checked'" : ''); ?>/>SEO提词(使用本选项前面的选项全都作废)</label>
    <br/>
    <button type="submit" name="Submit">提交进行分词</button>
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
    <button type="reset" name="Submit2">重设表单数据</button>
</form>
<br>分词结果：<br>
<textarea name="result" id="result" style="width:98%;height:180px;font-size:14px;color:#555"><?php echo (isset($str_result) ? $str_result : ''); ?></textarea>
<br>权重TF-IDF试验：<br>
<textarea name="result" id="result" style="width:98%;height:60px;font-size:14px;color:#555"><?php echo (isset($rank_result) ? $rank_result : ''); ?></textarea>
<br><b>调试信息：</b>
<hr>
<font color='blue'>字串长度：</font><?php echo $slen; ?>K <font color='blue'>自动识别词：</font><?php echo (isset($pa_foundWordStr)) ? $pa_foundWordStr : ''; ?><br>
<hr>
<font color='blue'>内存占用及执行时间：</font>(表示完成某个动作后正在占用的内存)<hr>
<?php echo $memory_info; ?>
总用时：<?php echo $endtime; ?> 秒
</td>
</tr>
</table>
</body>
</html>

