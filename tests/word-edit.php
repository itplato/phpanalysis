<?php
require __DIR__.'/lib/inc_unit.php';
require __DIR__.'/lib/tp.mysql.php';
$dicfile = __DIR__.'/../dict/not-build/db-explode.txt';
$db = new TpMysql("127.0.0.1", 'nlp', 'nlp123456', 'nlp');
$maxpage = 0;
$pagesize = 100;

//查询百度
$keyword = isset($_REQUEST['keyword']) ? $_REQUEST['keyword'] : '';
$ac = isset($_REQUEST['ac']) ? $_REQUEST['ac'] : '';
if( $ac=='viewbaidu' )
{
    if( $keyword == '' )  exit('Keyword empty!');
    $opts = array( "http"=>array(
                    "method"=>"GET",
                    "header"=>"Referer:https://m.baidu.com",
                    "timeout"=> 15
               ),
        );
    $context = stream_context_create($opts);
    //http://m.baidu.com/s?word=中国
    $ct = file_get_contents("https://m.baidu.com/s?word=".urlencode($keyword), false, $context);
    if( $ct=='' ) {
        echo "取不到内容！";
    } else {
        $ct = preg_replace("~<div class=\"se-page-hd-content\">(.*)<div class=\"se-head-tabcover\"></div></div></div>~sU", '', $ct);
        echo $ct;
    }
    exit();
}
else if( $ac == 'explode' )
{
    $str = '';
    $_words = $db->get("words");
    foreach($_words as $word)
    {
        array_shift($word);
        $str .= join(',', $word)."\n";
    }
    file_put_contents($dicfile, rtrim($str));
    
    //更新字典
    $normalDic = __DIR__.'/../dict/base_dic_full.dic';
    require_once __DIR__.'/../src/PhpAnalysis.php';
    $pa = Tutu\Phpanalysis::Instance()->AssistBuildDict( $dicfile, $normalDic);
    
    header('content-type:text/html;charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="utf-8" /><title> 操作完成提示</title> </head><body>';
    echo "<div style='width:800px;margin:auto;margin-top:100px;padding:20px;font-size:1em;text-align:center;border:1px solid #bbb;border-radius:20px;'>";
    echo "成功导出词库为文件: {$dicfile}，并更新主词典...</div><body></html>";
    exit();
}

$pageno  = isset($_REQUEST['pageno']) ? $_REQUEST['pageno'] : 1;
$offset = ($pageno - 1) * $pagesize;
$sort = isset($_REQUEST['sort']) ? $_REQUEST['sort'] : 'id';
$regtype = isset($_REQUEST['regtype']) ? $_REQUEST['regtype'] : 0;
$where = '';
//-------------------------------
// 关键字规则：
// |type  |#trade |!add_prop  |@add_info   :|=长度(可叠加，只能放在结尾，如：人|#A:12)
// ^ 等于限定符，放在类型结尾，如 |n^ |#a^
//-------------------------------
if( $keyword != '' )
{
    $ks = explode('|', $keyword);
    $keyword = $ks[0];
    if( preg_match("/[a-z]/i", $keyword) ) {
        $where = $regtype == -1 ? " `type` = '{$keyword}' " : " LOCATE('{$keyword}', `type`) ";
    }
    else if( $regtype == -1 ) {
        $where = " `word` = '{$keyword}' ";
    }
    else if( $regtype == 0 ) {
        $where = " LOCATE('{$keyword}', `word`) ";
    } else {
        $where = $regtype==1 ? " `word` like '{$keyword}%' " : " `word` like '%{$keyword}' ";
    }
    if( isset($ks[1]) )
    {
        $_link = $where=='' ? '' : ' AND ';
        $start_ks = $ks;
        //长度可作为附加在结尾的通用条件
        //:n 大于或等于   =n  等于这长度
        if( preg_match('/[:=]/', $ks[1]) )
        {
            $kss = preg_match('/:/', $ks[1]) ? explode(':', $ks[1]) : explode('=', $ks[1]);
            $where .= preg_match('/:/', $ks[1]) ? $_link." length(`word`) >= '{$kss[1]}' " : $_link." length(`word`) = '{$kss[1]}' ";
            $ks[1] = preg_replace("/[:=](.*)$/", '', $ks[1]);
        }
        //其它条件，暂时不允许叠加
        if( preg_match('/[a-z#!@]/i', $ks[1]) )
        {
            if( preg_match('/#/', $ks[1]) )
            {
                $ks_l = str_replace('#', '', $ks[1]);
                if( strpos($ks_l, '^') !== false ) {
                    $ks_l = str_replace('^', '', $ks_l);
                    $where .= $_link." LOCATE('{$ks_l}', `trade` = '{$ks_l}' ";
                } else {
                    $where .= $_link." LOCATE('{$ks_l}', `trade`) ";
                }
            }
            else if( preg_match('/!/', $ks[1]) )
            {
                $ks_l = str_replace('!', '', $ks[1]);
                if( strpos($ks_l, '^') !== false ) {
                    $ks_l = str_replace('^', '', $ks_l);
                    $where .= $_link." LOCATE('{$ks_l}', `add_prop` = '{$ks_l}' ";
                } else {
                    $where .= $_link." LOCATE('{$ks_l}', `add_prop`) ";
                }
            }
            else if( preg_match('/@/', $ks[1]) )
            {
                if( $ks[1]=='@' ) {
                    $where .= $_link." LOCATE('@', `add_info`) ";
                } else {
                    $where .= $_link." LOCATE('".str_replace('@', '', $ks[1])."', `add_info`) ";
                }
            }
            else
            {
                if( strpos($ks[1], '^') !== false ) {
                    $ks_l = str_replace('^', '', $ks[1]);
                    $where .= $_link." `type` = '{$ks_l}' ";
                } else {
                    $where .= $_link." LOCATE('{$ks[1]}', `type`) ";
                }
            }
        }
        $keyword = join('|', $start_ks);
    }
}
//保存数据
if( !empty($_POST) )
{
    $pagenext = isset($_REQUEST['pagenext']) ? $_REQUEST['pagenext'] : 0;
    $_REQUEST = array();
    $str = '';
    $start = false;
    //读取旧数据进行对比
    $old_words = [];
    if( $where != '' ) {
        $db->where( $where );
    }
    if( $sort=='freq' ) $db->orderBy("freq", "DESC");
    else if( $sort=='type' ) $db->orderBy("type", "ASC");
    $_words = $db->get("words", array($offset, $pagesize), "*");
    foreach( $_words as $w ) {
        $old_words[ $w['id'] ] = $w;
    }
    //echo '<xmp>'; print_r( $old_words ); echo '</xmp>';
    unset($_words);
    $ids = [];
    foreach($_POST['id'] as $seq => $id)
    {
        $ids[$id] = $seq;
    }
    //echo '<xmp>'; print_r( $ids ); echo '</xmp>'; exit();
    $del_num = 0;
    $update_num = 0;
    $insert_num = 0;
    ///////////////////////////////////////////
    foreach($old_words as $id => $wl)
    {
        //删除的条目
        if( trim($_POST['word'][$ids[$id]])=='' )
        {
            $db->where('id', $id)->delete("words");
            $del_num++;
            continue;
        }
        //检测是否需要更新
        array_shift($wl);
        $old_str = join(',', $wl);
        $new_str = trim($_POST['word'][$ids[$id]]).','.trim($_POST['rank'][$ids[$id]]).','.trim($_POST['type'][$ids[$id]]).','.trim($_POST['trade'][$ids[$id]]).','.trim($_POST['level'][$ids[$id]]).','.trim($_POST['info'][$ids[$id]]).','.trim($_POST['retain_info'][$ids[$id]]);
        //更新的条目
        //echo $old_str."!=".$new_str."<br>\n";
        if( $old_str != $new_str )
        {
            $w = trim($_POST['word'][$ids[$id]]);
            $data = ['word' => $w, 'freq' => trim($_POST['rank'][$ids[$id]]), 
                     'type' => trim($_POST['type'][$ids[$id]]), 'trade' => trim($_POST['trade'][$ids[$id]]), 
                     'add_prop' => trim($_POST['level'][$ids[$id]]), 'add_info' => trim($_POST['info'][$ids[$id]]), 
                     'retain_info' => trim($_POST['retain_info'][$ids[$id]]) ];
            //:开头的为新增词条(可以在任何位置加，并不会删除或改变原来位置的词条)
            if( $w[0]==':' )
            {
                if( $w != ':' ) {
                    $data['word'] = str_replace(':', '', $data['word']);
                    $db->insert("words", $data);
                    $insert_num++;
                }
            }
            else
            {
                $db->where('id', $id)->update("words", $data);
                $update_num++;
            }
        }
    }
    $addurl = "";
    if( $keyword != '' )  $addurl .= "&keyword=".urlencode($keyword);
    $addurl .= "&sort=".urlencode($sort);
    $addurl .= "&regtype=".urlencode($regtype);
    unset( $old_dicts );
    if( $pagenext==1 ) $pageno++;
    else if( $pagenext==2 ) $pageno--;
    header('content-type:text/html;charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="utf-8" /><title> 操作完成提示</title>';
    echo "<meta http-equiv=\"refresh\" content=\"1; url=word-edit.php?pageno={$pageno}{$addurl}\"></head><body>";
    echo "<div style='width:800px;margin:auto;margin-top:100px;padding:20px;font-size:1em;text-align:center;border:1px solid #bbb;border-radius:20px;'>";
    echo "删除 {$del_num} 条，更新 {$update_num}，插入：{$insert_num} 条数据，1秒后跳转到下一个操作...</div>";
    echo '<body></html>';
    exit();
}
//读取列表
else
{
    if( $where != '' ) {
        $db->where( $where );
    }
    $info = $db->getOne("words", "count(*) as cnt");
    $maxpage = ceil($info['cnt'] / $pagesize);
    $words = array();
    $db->setTrace( true );
    if( $where != '' ) {
        $db->where( $where );
    }
    if( $sort=='freq' ) $db->orderBy("freq", "DESC");
    else if( $sort=='type' ) $db->orderBy("type", "ASC");
    $_words = $db->get("words", array($offset, $pagesize), "*");
    //echo '<xmp>'; print_r ($db->trace); echo '</xmp>';
    foreach( $_words as $w )
    {
        $words[] = ['id' => $w['id'], 'w' => $w['word'], 'r' => $w['freq'], 't' => $w['type']
                    ,'tr' => $w['trade'], 'l' => $w['add_prop'], 'a' => $w['add_info'], 'ri' => $w['retain_info'] ];
    }
    unset($_words);
    $jstr1 = json_encode($words);
    unset($words);
    $jstr2 = '[]';
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset=“utf-8” />
<title> word-edit </title>
<link rel="stylesheet" href="static/bootstrap.min.css">
<script src="static/jquery.min.3.2.js"></script>
<script src="static/bootstrap.min.4.1.js"></script>
<script src="static/vue.min.2.2.js"></script>
<script src="static/acunit.js"></script>
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
<div class="pc" id="app">
<h3 style="padding-right:20px;">Dictionary Edit <span style="font-size:14px;">(当前页：<?php echo $pageno.'/'.$maxpage; ?> )</span></h3>
<div class="hr-line-dashed"></div>
<form class="form-inline" id="searchform" method="get"  action=""> 
<input type='hidden' name='ac' value='list'>
<div class="row">
    <div class="form-group">
        <label class="col-sm-9" style="padding-left:20px;">
            <strong>关键字：</strong><input name="keyword" class="form-control s0" value="<?php echo $keyword; ?>" type="text"> &nbsp;&nbsp;&nbsp;
            <strong>匹配方式：</strong>
            <label><input type='radio' name='regtype' value='-1' <?php if($regtype==-1) echo "checked"; ?>>&nbsp;绝对 &nbsp;&nbsp;</label>
            <label><input type='radio' name='regtype' value='0' <?php if($regtype==0) echo "checked"; ?>>&nbsp;模糊 &nbsp;&nbsp;</label>
            <label><input type='radio' name='regtype' value='1' <?php if($regtype==1) echo "checked"; ?>>&nbsp;开头% &nbsp;&nbsp; </label>
            <label><input type='radio' name='regtype' value='2' <?php if($regtype==2) echo "checked"; ?>>&nbsp;%结尾 &nbsp;&nbsp;</label>
            <strong>排序：</strong>
            <label><input type='radio' name='sort' value='id' <?php if($sort=='id') echo "checked"; ?>>&nbsp;顺序 &nbsp;&nbsp;</label>
            <label><input type='radio' name='sort' value='freq' <?php if($sort=='freq') echo "checked"; ?>>&nbsp;频率 &nbsp;&nbsp; </label>
            <label><input type='radio' name='sort' value='type' <?php if($sort=='type') echo "checked"; ?>>&nbsp;类型 &nbsp;&nbsp;</label>
        </label>
        <label class="col-sm-2">
            <button type="submit" id="sb01" class="btn btn-primary">搜索</button>
            &nbsp; &nbsp;
            <button type="button" id="sb02" onclick="location.href='word-edit.php'" class="btn">主页</button>
            &nbsp; &nbsp;
            <button type="button" id="sb03" onclick="location.href='demo.php'" class="btn">演示</button>
            &nbsp; &nbsp;
            <button type="button" id="sb04" onclick="window.open('word-edit.php?ac=explode');" class="btn">导出</button>
        </label>
    </div>
</div>
</form> 
<form class="form-inline" id="mainform" method="post"> 
<input type='hidden' name='pageno' id='pageid' value='<?php echo $pageno; ?>'>
<input type='hidden' name='pagenext' id='pagenext' value='0'>
<input name="keyword" value="<?php echo $keyword; ?>" type="hidden">
<input name="freq" value="<?php echo $freq; ?>" type="hidden">
<input name="type" value="<?php echo $type; ?>" type="hidden">
<div class="row">
      <div class="form-group title">
    <div class="col-sm-1">ID</div>
    <div class="col-sm-2">词组</div>
    <div class="col-sm-1">类别</div>
    <div class="col-sm-1">行业</div>
    <div class="col-sm-1">权实</div>
    <div class="col-sm-2">同义</div>
    <div class="col-sm-1">词频</div>
    <div class="col-sm-1">保留</div>
      </div>
      <div class="hr-line-dashed2"></div>
</div>
<div class="row"> 
<template v-for="(word, key) in words1">
<div class="form-group">
    <label class="col-sm-1 control-label">
    <a style="cursor:pointer" v-on:click="showBaidu(word.w)">{{word.id}}</a>
    <input name="id[]" v-model="word.id" type="hidden">
    </label>
    <div class="col-sm-2">
        <input name="word[]" class="form-control s0" v-model="word.w" autocomplete="off" type="text">
    </div>
    <div class="col-sm-1">
        <input name="type[]" class="form-control s s11" v-model="word.t" autocomplete="off" type="text">
    </div>
    <div class="col-sm-1">
        <input name="trade[]" class="form-control s s12" v-model="word.tr" autocomplete="off" type="text">
    </div>
    <div class="col-sm-1">
        <input name="level[]" class="form-control s s13" v-model="word.l" autocomplete="off" type="text">
    </div>
    <div class="col-sm-2">
        <input name="info[]" class="form-control s0 s13" v-model="word.a" autocomplete="off" type="text">
    </div>
    <label class="col-sm-1 control-label">
        <input name="rank[]" class="form-control s1 s13" v-model="word.r" autocomplete="off" type="text">
    </label>
    <label class="col-sm-1 control-label">
        <input name="retain_info[]" class="form-control s1 s13" v-model="word.ri" autocomplete="off" type="text">
    </label>
</div>
<div class="hr-line-dashed2"></div>
</template>
</div> <!-- //end row  -->

<div class="form-group" style="line-height:60px;height:60px">
    <?php if( $pageno > 1) { ?>
    <button type="button" id="sb3" class="btn btn-primary">保存并返回上一页</button>
    &nbsp; &nbsp; &nbsp;
    <?php } ?>
    <button type="submit" id="sb1" class="btn btn-primary">保存当前页</button>
    <?php if( $pageno < $maxpage ) { ?>
     &nbsp; &nbsp; &nbsp;
    <button type="button" id="sb2" class="btn btn-primary">保存并进入下一页</button>
    <?php } ?>
</div>
</form> 

</div>
<script type="text/javascript">
var vm = new Vue({
    el: '#app',
    data: {
        title:'Dictionary Edit',
        words1:<?php echo $jstr1; ?>,
        words2:<?php echo $jstr2; ?>
    },
    methods: {
        showBaidu:function(keyword){
            acUnit.ShowMessageBox({
                id: "modal",
                title: "dialog",
                cancelTxt:"取消",
                okTxt:"确定",
                width: "600",
                height: "700",
                backdrop: true,
                keyboard: true,
                src: "?ac=viewbaidu&keyword="+keyword,
                //src: "https://m.baidu.com/s?word="+keyword,
            });
        },
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