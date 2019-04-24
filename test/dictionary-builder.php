<?php
//编译词库
ini_set('memory_limit', '512M');
error_reporting(E_ALL);
header('Content-Type: text/html; charset=utf-8');
require_once '../Phpanalysis/Phpanalysis.php';
$normalDicSource = '../Phpanalysis/dict/not-build/base_dic_full.txt';
$seoDicSource = '../Phpanalysis/dict/not-build/base_dic_seo.txt';
$normalDic = '../Phpanalysis/dict/base_dic_full.dic';
$seoDic = '../Phpanalysis/dict/base_dic_seo.dic';

$ac = empty($_POST['ac']) ? '' : $_POST['ac'];
$dictype = empty($_POST['dictype']) ? '' : $_POST['dictype'];

if( $ac == 'make' )
{
    $targetfile = ($dictype==1 ? $normalDic  : $seoDic);
    $sourcefile = $_POST['sourcefile'];
    
    PhpAnalysis::$loadInit = false;
    $pa = new PhpAnalysis('utf-8', 'utf-8');
    $pa->MakeDict( $sourcefile, $targetfile );
    
    echo "完成词典创建: {$sourcefile} =&gt; {$targetfile} ";
    exit();
}
else if( $ac=='export' )
{
    $dicfile = ($dictype==1 ? $normalDic : $seoDic);
    $sourcefile = $_POST['sourcefile'];
    
    PhpAnalysis::$loadInit = false;
    $pa = new PhpAnalysis('utf-8', 'utf-8');
    $pa->LoadDict( $dicfile );
    $pa->ExportDict( $sourcefile );
    
    echo "完成反编译词典文件，生成的文件为：{$sourcefile}！";
    exit();
}
?>
<!DOCTYPE html>
<html>
<header>
<meta charset="utf-8" />
<title> 词典管理 </title>
<style>
* {
  font-size:14px;  
}
.hgroup {
    width: 800px;
    margin:auto;
    padding:10px;
    margin-top:20px;
    border:1px solid #ccc;
 }
 .row {
    padding:8px;
 }
 .title {
    font-weight:bold;
    border-bottom:1px solid #ccc;
    padding-bottom:6px;
 }
 .title2 {
    font-weight:bold;
    padding-bottom:6px;
 }
 .info {
    font-size:12px;
    font-weight:normal;
    color:#666;
 }
</style>
<script language="javascript">
    var waitfile = "<?php echo $seoDicSource ?>";
    function changeReadFile()
    {
        var  tmp = document.getElementById('sourcefile').value;
        document.getElementById('sourcefile').value = waitfile;
        waitfile = tmp;
    }
</script>
</header>
<body>

<div class="hgroup">
<div class="title">
    根据源文件创建分词词典：<span class="info">(源文件词条格式： 词条,频率,词性,行业标识,顺序id 后两个值用于研究用途，可以为空值)</span>
</div>
<form name="form1" action="?" method="POST" enctype="application/x-www-form-urlencoded" target="sta">
    <input type="hidden" name="ac" value="make">
    <div class="row">
        源文件： <input type="text" name="sourcefile" id="sourcefile" value="<?php echo $normalDicSource; ?>" style="width:680px;">
    </div>
    <div class="row">
    创建词典类型：<label><input type="radio" name="dictype" onchange="changeReadFile()" value="1" checked> 通用分词(dict/base_dic_full.dic)</label> 
    <label><input type="radio" name="dictype" value="2" onchange="changeReadFile()"> SEO提词(dict/base_dic_seo.dic)</label>
    </div>
    <div class="row">
        <button type="submit">开始操作</button>
    </div>
</form>
</div>

<div class="hgroup">
<div class="title">
    根据分词典反编译出源文件：
</div>
<form name="form1" action="?" method="POST" enctype="application/x-www-form-urlencoded" target="sta">
    <input type="hidden" name="ac" value="export">
    <div class="row">
        词典类型： 
        <label><input type="radio" name="dictype" value="1" checked> 通用分词(dict/base_dic_full.dic)</label> 
        <label><input type="radio" name="dictype" value="2"> SEO提词(dict/base_dic_seo.dic)</label>
    </div>
    <div class="row">
        保存源文件： <input type="text" name="sourcefile" value="../Phpanalysis/dict/not-build/mydic.txt" style="width:650px;">
    </div>
    <div class="row">
        <button type="submit">开始操作</button>
    </div> 
</form>
</div>
<div class="hgroup">
<div class="title2">
    操作状态：
</div>
<iframe style="width:800px;height:300px;" border="0" frameborder="1" name="sta"></iframe>    
</div>

<body>
</html>