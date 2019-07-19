<?php
include __DIR__."/inc_unit.php";
//$normalDicSource = '../dict/not-build/base_dic_full.txt';
$dicDir = '../dict/not-build';
$dicFile = '../dict/not-build/base_dic_full.txt';
$maxpage = 161;
$id = isset($_REQUEST['id']) ? $_REQUEST['id'] : 1;
if( !empty($_POST) )
{
    $pagenext = isset($_REQUEST['pagenext']) ? $_REQUEST['pagenext'] : 0;
    $_REQUEST = array();
    $old_dicts = file( $dicFile );
    $str = '';
    $start = false;
    foreach($old_dicts as $wl)
    {
        if( $wl[0]=='#' )
        {
            $str .= $wl;
            $ps = explode('#', $wl);
            $p = $ps[1];
            if( $p == $id )
            {
                $start = true;
                foreach($_POST['word'] as $k => $w)
                {
                    if( $w=='' ) continue;
                    $str .= "{$w},{$_POST['rank'][$k]},{$_POST['type'][$k]},{$_POST['trade'][$k]},{$_POST['level'][$k]},{$_POST['info'][$k]},\n";
                }
            }
            else
            {
                $start = false;
            }
        }
        else
        {
            if( !$start ) $str .= $wl;
        }
    }
    unset( $old_dicts );
    $_POST = array();
    //echo '<pre>', $str , '</pre>'; exit();
    file_put_contents($dicFile, rtrim($str));
    if( $pagenext==1 ) $id++;
    else if( $pagenext==2 ) $id--;
    $str = '';
    header("location:word-edit.php?id={$id}");
    exit();
}
//读取特定段落内容
$_words = array();
$fp = fopen($dicFile, 'r');
$start = false;
while( $wl = fgets($fp, 512) ) {
    if( $wl[0]=='#' ) {
        if( $start ) break;
        $ps = explode('#', $wl);
        $p = $ps[1];
        if( $p == $id ) $start = true;
    }
    else if( $start )
    {
        $_words[] = $wl;
    } 
}
fclose($fp);

$words1 = $words2 = array();
$n = $c = 0;
//先算总数
foreach( $_words as $word )
{
    $word = rtrim( $word );
    $ws = explode(',', $word);
    if( $word[0].$word[1]=='@@') continue;
    $c++;
}
$max = ceil($c / 2);
foreach( $_words as $word )
{
    $word = rtrim( $word );
    $ws = explode(',', $word);
    if( $word[0].$word[1]=='@@') continue;
    /*
    if( $n > $max ) {
        $words2[$n] = ['w' => $ws[0], 'r' => $ws[1], 't' => $ws[2], 'tr' => $ws[3], 'l' => $ws[4], 'a' => $ws[5] ];
    } else {
        $words1[$n] = ['w' => $ws[0], 'r' => $ws[1], 't' => $ws[2], 'tr' => $ws[3], 'l' => $ws[4], 'a' => $ws[5] ];
    }
    */
    $words1[$n] = ['w' => $ws[0], 'r' => $ws[1], 't' => $ws[2], 'tr' => $ws[3], 'l' => $ws[4], 'a' => $ws[5] ];
    $n++;
}
$jstr1 = json_encode($words1);
$jstr2 = json_encode($words2);
unset($_words);
unset($words1);
unset($words2);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset=“utf-8” />
<title> word-edit </title>
<link rel="stylesheet" href="https://cdn.staticfile.org/twitter-bootstrap/4.1.0/css/bootstrap.min.css">
<script src="https://cdn.staticfile.org/jquery/3.2.1/jquery.min.js"></script>
<script src="https://cdn.staticfile.org/twitter-bootstrap/4.1.0/js/bootstrap.min.js"></script>
<script src="https://cdn.staticfile.org/vue/2.2.2/vue.min.js"></script>
<style>
  .pc {
       width: 1260px;
       /* border:2px solid #bbb;
       border-radius:10px; */
       padding:10px;
       margin:auto;
       margin-top:30px;
       margin-bottom:30px;
   }
   .hr-line-dashed {
       height: 8px;
       border-bottom: 1px solid #ccc;
       margin-bottom: 8px;
       clear:both;
   }
   .hr-line-dashed2 {
       clear:both;
   }   
   .form-group {
      width:1200px;
      height:48px;
      border-bottom: 1px solid #eee;
   }   
   .form-group .s {
      width: 90px
   }
   .form-group .s1 {
      width: 70px;
      color: #aaa;
   }
   .form-group .s0 {
      width: 180px
   }
   .form-group .s11 {
      border-color:#11a800;
   }
   .form-group .s12 {
      border-color:#b6e0bf;
   }
   .form-group .s13 {
      border-color:#eee;
   }
   .title div { text-align:center }
</style>
</head>
<body>
<form class="form-inline" id="mainform" method="post"> 
<input type='hidden' name='id' id='pageid' value='<?php echo $id; ?>'>
<input type='hidden' name='pagenext' id='pagenext' value='0'>   
<div class="pc" id="app">
<h3>Dictionary Edit <span style="font-size:14px;">(当前页：<?php echo $id; ?> )</span></h3>
<div class="hr-line-dashed"></div>
<!--
<div class="row">
  <div class="col-sm-6">
      <div class="form-group">
    <div class="col-sm-1">频</div>
    <div class="col-sm-4">词组</div>
    <div class="col-sm-2">类别</div>
    <div class="col-sm-2">行业</div>
    <div class="col-sm-2">权</div>
    <div class="col-sm-1">附</div>
      </div>
    <div class="hr-line-dashed2"></div>
 </div>
 <div class="col-sm-6">
      <div class="form-group">
    <div class="col-sm-1">频</div>
    <div class="col-sm-4">词组</div>
    <div class="col-sm-2">类别</div>
    <div class="col-sm-2">行业</div>
    <div class="col-sm-2">权</div>
    <div class="col-sm-1">附</div>
      </div>
    <div class="hr-line-dashed2"></div>
 </div>
</div>
-->

<div class="row">
      <div class="form-group title">
    <div class="col-sm-1">ID</div>
    <div class="col-sm-2">词组</div>
    <div class="col-sm-1">类别</div>
    <div class="col-sm-1">行业</div>
    <div class="col-sm-1">权实</div>
    <div class="col-sm-1">同义</div>
    <div class="col-sm-1">词频</div>
      </div>
      <div class="hr-line-dashed2"></div>
</div>

<div class="row">
<template v-for="(word, key) in words1">
<div class="form-group">
    <label class="col-sm-1 control-label">
        {{key}}
    </label>
    <div class="col-sm-2">
        <input name="word[]" class="form-control s0" v-model="word.w" type="text">
    </div>
    <div class="col-sm-1">
        <input name="type[]" class="form-control s" v-model="word.t" type="text">
    </div>
    <div class="col-sm-1">
        <input name="trade[]" class="form-control s s11" v-model="word.tr" type="text">
    </div>
    <div class="col-sm-1">
        <input name="level[]" class="form-control s s12" v-model="word.l" type="text">
    </div>
    <div class="col-sm-1">
        <input name="info[]" class="form-control s s13" v-model="word.a" type="text">
    </div>
    <label class="col-sm-1 control-label">
        <input name="rank[]" class="form-control s1 s13" v-model="word.r" type="text">
    </label>
</div>
<div class="hr-line-dashed2"></div>
</template>
</div> <!-- //end row  -->

<div class="form-group" style="line-height:60px;height:60px">
    <?php if( $id > 1) { ?>
    <button type="button" id="sb3" class="btn btn-primary">保存并返回上一页</button>
    &nbsp; &nbsp; &nbsp;
    <?php } ?>
    <button type="submit" id="sb1" class="btn btn-primary">保存当前页</button>
    <?php if( $id < $maxpage ) { ?>
     &nbsp; &nbsp; &nbsp;
    <button type="button" id="sb2" class="btn btn-primary">保存并进入下一页</button>
    <?php } ?>
</div>
</div>
</form> 
<script type="text/javascript">
var vm = new Vue({
    el: '#app',
    data: {
        title:'Dictionary Edit',
        words1:<?php echo $jstr1; ?>,
        words2:<?php echo $jstr2; ?>
    },
    methods: {
    }
});
$(function(){
    $('#sb2').click(function(){
        $('#pagenext').val('1');
        $('#mainform').submit();
    });
    $('#sb3').click(function(){
        $('#pagenext').val('2');
        $('#mainform').submit();
    });
});
</script>
<body>
</html>