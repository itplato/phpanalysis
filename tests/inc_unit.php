<?php
ini_set('display_errors', 'On');
ini_set('memory_limit', '1024M');
error_reporting(E_ALL);

//测试时用的一些常用函数
function save_array($filename, $array)
{
    $c = count($array);
    $tmpstr = '';
    $fp = fopen($filename, 'w');
    flock( $fp, LOCK_EX);
    for($i=0; $i < $c; $i++)
    {
        $tmpstr .= $array[$i];
        if( $i+1 % 1000 == 0 ) {
            fwrite($fp, $tmpstr);
            $tmpstr = '';
        }
    }
    if( $tmpstr != '' ) {
        fwrite($fp, $tmpstr);
    }
    flock( $fp, LOCK_UN);
    fclose( $fp );
}

function load_add_dic( $file )
{
    $hw = '';
    $ds = file( $file );
    $add_dic = array();
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
            $ws = explode(',', $d);
            foreach($ws as $estr) {
                $add_dic[$hw][$estr] = strlen($estr);
            }
        }
    }
    return $add_dic;
}

