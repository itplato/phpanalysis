<?php
// 严格开发模式
ini_set('display_errors', 'On');
ini_set('memory_limit', '128M');
error_reporting(E_ALL);

require_once '../src/Phpanalysis.php';
use Tutu\Phpanalysis;

$t1 = $ntime = microtime(true);
$endtime = '未执行任何操作，不统计！';
function print_memory($rc, &$infostr)
{
    global $ntime;
    $cutime = microtime(true);
    $etime = sprintf('%0.4f', $cutime - $ntime);
    $m = sprintf('%0.2f', memory_get_usage()/1024/1024);
    $infostr .= "{$rc}: &nbsp;{$m} MB 用时：{$etime} 秒<br>\n";
    $ntime = $cutime;
}

header('Content-Type: text/html; charset=utf-8');

$memory_info = '';
print_memory('没任何操作', $memory_info);

$str = (isset($_POST['source']) ? $_POST['source'] : '');

$loadtime = $endtime1  = $endtime2 = $slen = 0;

$do_unit_single = $do_unit_special = $do_fork = true;

$max_split = $do_prop = false;

//限制字数
$str = mb_substr($str, 0, 1024 * 3, 'UTF-8');

if($str != '')
{
    //二元消岐
    $do_fork = empty($_POST['do_fork']) ? false : true;
    
    //专用词合并
    $do_unit_special = empty($_POST['do_unit_special']) ? false : true;
    
    //合并单词
    $do_unit_single = empty($_POST['do_unit_single']) ? false : true;
    
    //多元切分
    $max_split = empty($_POST['max_split']) ? false : true;
    
    //词性标注
    $do_prop = empty($_POST['do_prop']) ? false : true;

    
    $tall = microtime(true);
    
    //初始化类
    $pa = PhpAnalysis::Instance();
    print_memory('初始化对象', $memory_info);
    
    //执行分词
    $pa->SetOptions( $do_unit_special, $do_unit_single, $max_split, $do_fork );
    $pa->SetSource( $str )->Delimiter(' ')->Exec();
    print_memory('执行分词', $memory_info);
    
    //$rs = $pa->AssistGetSimple();
    //echo "<div style='width:1000px'>", $rs, '</div>';

    $rank_result = $pa->GetTags(20, true);
        
    //带词性标注
    if( $do_prop ) {
        $result = json_encode($pa->GetResultProperty( true, 2 ));
    } else {
        $result = json_encode($pa->GetResult( 2 ));
    }
    
    print_memory('输出分词结果', $memory_info);
    
    $pa_foundWordStr = $pa->GetNewWords();
    
    $pa_ambiguity_words = $pa->Delimiter('; ')->AssistGetAmbiguitys();
    
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
<title> Analysis testing... </title>
<link rel="stylesheet" href="http://www.it.com/static/bootstrap.min.css">
<script src="http://www.it.com/static/jquery.min.3.2.js"></script>
<script src="http://www.it.com/static/bootstrap.min.4.1.js"></script>
<script src="http://www.it.com/static/vue.min.2.2.js"></script>
<style>
#main { width:1200px;margin:auto }
.row { padding: 6px; }
label { margin-right:6px;}
.contents { 
    background: #fafafa; 
    padding:18px; 
    border:1px solid  #eaeaea; 
    border-radius:10px;
    margin-bottom:8px;
}
.contents2 { 
    padding:18px; 
    border:1px solid  #eaeaea; 
    border-radius:10px;
    margin-bottom:8px;
}
dl { width:100%; }
.debug { padding-left:10px; padding-right:10px; margin-bottom:0px; padding-bottom:0px }
.debug dd { font-size:0.9em; line-height:150%; border-bottom: 1px dashed #ccc}
.debug dd span { font-size:1.1em; font-weight:bold }
.debug-msg { font-size:0.9em; padding:10px; }
.debug-msg span { font-size:1.1em; font-weight:bold }
/*  词性颜色  */
.wd { margin-bottom:6px; margin-right:6px; background: #ddd; font-size:0.9em; padding:3px; float:left }
.wd-en  { background: #5cfcae; }
.wd-s  { color:#aaa; }
.wd-xs  { color:#999; }
.wd-e  { background: #5cfcae; }
.wd-es  { background: #a9fff0; }
.wd-a  { background: #509ee3; }
.wd-d  { background: #d1d1d1; }
.wd-c  { background: #d1d1d1; }
.wd-b  { background: #64c2ff; }
.wd-n  { background: #9aff02; }
.wd-nN  { background: #c2ff68; }
.wd-nS, .wd-na  { background: #deffac ; }
.wd-nT  { background: #efffd7; }
.wd-nM  { background: #efef67; }
.wd-nA   { background: #efffdt; }
.wd-nC  { background: #5cadad; }
.wd-nP  { background: #81c0c0; }
.wd-nz  { background: #ffff37; }
.wd-nB  { background: #ffffb9; }
.wd-nj  { background: #ffe66f; }
.wd-nr  { background: #bceefc; }
.wd-v  { background: #ff8f59; }
.wd-vu  { background: #fde29b; }
.wd-vn  { background: #eefda3; }
.wd-m  { background: #a3d1d1; }
.wd-mQ, { background: #c7c7e2; }
.wd-mt  { background: #a6a6d2; }
.wd-t  { background: #d1e9e9; }
.wd-p  { background: #d1d1d1 ; }
.wd-q  { background: #d1d1d1; }
.wd-r  { background: #bceefc; }
.wd-sb { background: #eee; color:#ccc}
.wd-u  { background: #d1d1d1; }
.wd-i  { background: #80ffff; }
.wd-l  { background: #caffff; }
.wd-f  { background: #d2c2ac; }
.wd-x, .wd-X  { background: #69ba5c; color:#333 }
.wd-o  { background: #d1d1d1; }
.wd-F, .wd-NF  { background: #ecf5ff; }
.wd-E  { background: #fbfbff; }
.wd-mq, .wd-mu  { background: #c7c7e2; }
.wd-nE  { background: #ceceff; }
.wd-nZ  { background: #ebd3a8; }
.ws-0 { font-size: 0.5em }
.ws-1 { font-size: 0.6em }
.ws-2 { font-size: 0.7em }
.ws-3 { font-size: 0.8em }
.ws-4 { font-size: 0.9em }
.ws-5 { font-size: 1em }
.ws-6 { font-size: 1.1em }
.ws-7 { font-size: 1.2em }
.ws-8 { font-size: 1.3em }
.ws-9 { font-size: 1.4em }
.ws-10 { font-size: 1.5em }
h5 { font-size: 1.1em }
.prop dd {cursor:pointer}
</style>
</head>
<body>
<div id="main">
    <div class="row">  
        <h3>{{title}} &nbsp; <a href="dictionary-builder.php"><span style="font-size:14px">[词典管理]</span></a></h3>
    </div>
<div class="contents">
    <form id="form1" name="form1" method="post" action="?ac=done" style="margin:0px;padding:0px;line-height:24px;">
    <h5>源文本：</h5>
    <div class="row">
        <textarea name="source" class="form-control" style="height:180px;font-size:0.9em;"><?php echo (isset($_POST['source']) ? $_POST['source'] : $teststr); ?></textarea>
    </div>
    <div class="row">
        <label><input type='checkbox' name='do_unit_special' value='1' <?php echo ($do_unit_special ? "checked='checked'" : ''); ?>/>专用词识别</label>
        <label><input type='checkbox' name='do_unit_single' value='1' <?php echo ($do_unit_single ? "checked='checked'" : ''); ?>/>单字合并</label>
        <label><input type='checkbox' name='max_split' value='1' <?php echo ($max_split ? "checked='checked'" : ''); ?>/>最大切分</label>
        <label><input type='checkbox' name='do_fork' value='1' <?php echo ($do_fork ? "checked='checked'" : ''); ?>/>二元消岐</label>
        <label style='display:none'><input type='checkbox' name='do_prop' value='1' checked='checked' />词性标注</label>
    </div>
    <div class="row">
        <button type="submit" class="btn btn-primary" name="Submit">提交进行分词</button>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
        <button type="reset" class="btn" name="Submit2">重设表单数据</button>
    </div>
    </form>
</div>
<div class="contents">
    <div class="row">
        <div class='col-sm-9'>
        <h5>分词结果</h5>
        </div>
        <div class='col-sm-3'>
        <h5>词性标注</h5>
        </div>
        <div class="hr-line-dashed"></div>
    </div>
    <div class="row">
        <!-- ?php echo (isset($str_result) ? $str_result : ''); ? -->
        <dl class='wordlist col-sm-9'>
        <template v-for="(word, key) in rswords.w">
        <dd :title="reSetTitle(word)" :class="reSetClass(word)">{{word[0]}}</dd>
        </template>
        </dl>
        <dl class='wordlist prop col-sm-3'>
            <template v-for="(word, key) in rswords.p">
            <dd :class="reSetClass(word)" v-on:click="propClick(word)">{{word[0]}}</dd>
            </template>
        </dl>
        <div class="hr-line-dashed"></div>
    </div>
</div>
<div class="contents2">
    <h5>
    权重TF-IDF计算试验：
    </h5>
    <div class="row">
        <textarea name="result" id="result" class="form-control" style="height:80px;font-size:0.9em;"><?php echo (isset($rank_result) ? $rank_result : ''); ?></textarea>
    </div>
    <h5>
        调试信息：
    </h5>
    <div class="row">
      <dl class="debug">
        <dd><span>字串长度：</span><?php echo $slen; ?>K</dd>
        <dd><span>自动识别词：</span><?php echo (isset($pa_foundWordStr)) ? $pa_foundWordStr : ''; ?></dd>
        <dd><span>岐义处理：</span><?php echo (isset($pa_ambiguity_words)) ? $pa_ambiguity_words : ''; ?></dd>
        <dd><span>内存占用及执行时间：(表示完成某个动作后正在占用的内存)</span></dd>
        <dd>
        <?php echo $memory_info; ?> 总用时：<?php echo $endtime; ?> 秒
        </dd>
      </dl>
    </div>
</div>
</div>
<script type="text/javascript">
var vm = new Vue({
    el: '#main',
    lastProp: '',
    lastbg: '',
    lastColor: '',
    data: {
        title:'Analysis Test',
        rswords:<?php echo (isset($result) ? $result : '[]'); ?>,
    },
    methods: {
        reSetClass: function(word){
            if( word[1] ) {
                return "wd wd-"+word[1];
            } else {
                return 'wd';
            }
        },
        reSetTitle: function(word){
            if( word[2] ) return word[2];
            else return '';
        },
        propClick : function(word){
            //alert( word[1] );
            var thisProp = word[1];
            var thisBg = $(".wd-"+word[1]).css("background");
            var thisColor = $(".wd-"+word[1]).css("color");
            $(".wd-"+word[1]).css("color", "yellow");
            $(".wd-"+word[1]).css("background", "#222");
            if( this.lastProp != '' && this.lastProp != word[1] )
            {
                $(".wd-"+this.lastProp).css("color", this.lastColor);
                $(".wd-"+this.lastProp).css("background", this.lastBg);
                this.lastProp = thisProp;
                this.lastColor = thisColor;
                this.lastBg = thisBg;
            }
        }
    }
});
$(function(){
  //
});
</script>
</body>
</html>

