//----------------------------------
// Js实用方法简易封装
//----------------------------------
var acUnit = {};
//这个用于保存不同id的窗口的html，那样不用每次都加载新的html
//但调用的时候，不同内容的窗口务必要指定不同的id
//如果不指定id，使用默认的 modal 名称，则每次会修改html
var box_ids = {};
/*****************************************************************
 * 调用bootstrap的模态框直接加载一个url或指定的html消息显示对话框
 * 参数参见 var defaults 的定义
 * @auth itplato
*****************************************************************/

function sleep_tmp(millisecond) {
    return new Promise(resolve => {
        setTimeout(() => {
                resolve()
        }, millisecond)
    });
}

acUnit.ShowMessageBox = function ( custom_configs ) {
    
    //参数（通过 custom_configs 传递可以直接改变某个参数 ）
    var defaults = {
        id: "modal",
        title: "dialog",
        cancelTxt:"取消",
        okTxt:"确定",
        width: "500", //不支持%
        height: "250",//不支持%
        backdrop: true,
        keyboard: true,
        url: "", //加载远程url，和原生bootstrap remote一致
        html: "",//要显示的html(无法支持内部事件，这个和remote二选一)
        src: "", //在一个iframe里显示网址
        okEvent: null,
        cancelEvent: null,
        showEvent: null, //显示触发时
        hideEvent: null, //完全隐藏后
        myEvent: null, //用于定义窗口内部加载的html里的元素的事件([ [".xx1|#xx2", "click|change..", function(){}],... ])
    };
    
    var opts = defaults;
    
    //var modal = null;

    //动态创建窗口
    var creatDialog = {
        
        init: function (opts) {
            if( opts.id == 'model' || box_ids[ opts.id] == undefined ) {
                if( opts.id != 'model' ) {
                    box_ids[ defaults.id ] = 1;
                }
            
                //动态绘制窗口
                var wdom = this.getModelHtml( opts );
                $("body").append( wdom );
                
                var modal = $("#" + opts.id);

                //初始化model属性及事件     
                modal.modal({
                    backdrop: opts.backdrop,
                    keyboard: opts.keyboard
                });
            
                //是从url里获取得窗口内容还是直接放一些内容在里面
                if( opts.url != "" ) {
                    $("#"+opts.id+" .modal-body").load( opts.url );
                } else if( opts.src=="" ) {
                    $("#"+opts.id+" .modal-body").html( opts.html );
                }
            
            }
            else
            {
                var modal = $("#" + opts.id);
                //初始化model属性及事件     
                modal.modal({
                    backdrop: opts.backdrop,
                    keyboard: opts.keyboard
                });
            }
            
            //自定义绑定的事件
             if( opts.myEvent ) {
                    var elen = opts.myEvent.length;
                    for(var i=0; i < elen; i++) {
                        $( opts.myEvent[i][0] ).on(opts.myEvent[i][1], opts.myEvent[i][2]);
                    }
             }
             //隐藏窗口后删除窗口html
             modal.on('hide.bs.modal', function(){
                    $(this).off('hide.bs.modal');
                    if(opts.id == 'model') {
                        $("#" + opts.id + ".modal").remove();
                    }
                    $(".modal-backdrop").remove();
                    if( typeof(opts.hideEvent)=='string' ) {
                        eval(opts.hideEvent+"()");
                    } else if( typeof(opts.hideEvent)=='function' ) {
                        opts.hideEvent();
                    }
             });
            
             //窗口显示后
             modal.on('shown.bs.modal', function(){
                    $(this).off('shown.bs.modal');
                    if( typeof(opts.showEvent)=='string' ) {
                        eval(opts.showEvent+"()");
                    } else if( typeof(opts.showEvent)=='function' ) {
                        opts.showEvent();
                    }
                    if( opts.src != '' ) {
                        $('.dlg-frame').attr('src', opts.src);
                    }
             });
        
           //点击窗口的确定按钮
            $(".ok").unbind("click").click(function () {
                if (opts.okEvent) {
                     var ret = false;
                     if( typeof(opts.okEvent)=='string' ) {
                        ret = eval(opts.okEvent+"()");
                     } else if( typeof(opts.okEvent)=='function' ) {
                        ret = opts.okEvent();
                     }
                     if (ret) {
                         modal.modal('hide');
                     }
                } else {
                    modal.modal('hide');
                }
            });
            
            //点击窗口的取消按钮(或上面的关闭X)
            $(".cancel").unbind("click").click(function () {
                if( typeof(opts.cancelEvent)=='string' ) {
                    eval(opts.cancelEvent+"()");
                } else if( typeof(opts.cancelEvent)=='function' ) {
                    opts.cancelEvent();
                }
                modal.modal('hide');
            });
            
            //显示窗口
            modal.modal('show');
        },
        
        //model的html
        getModelHtml: function (o) {
            var df_content;
            if( o.src=='' ) 
            {
                df_content = "<p>正在加载...</p>";
            }
            else {
                var fw = o.width - 30;
                var fh = o.height - 180;
                df_content = "<iframe name='dlgframe' class='dlg-frame' src='' style='width:"+fw+"px;height:"+fh+"px;'"+
                             " frameborder='no' border='0' marginwidth='0' marginheight='0'></iframe>";
            }
            var rehtml = '<div id="'+ o.id +'" class="modal fade" role="dialog" tabindex="-1" aria-labelledby="myModalLabel" aria-hidden="true">'+
                '<div class="modal-dialog"><div class="modal-content" style="width:'+o.width+'px;height:'+o.height+'px"><div class="modal-header">'+
                '<h4 class="modal-title" id="myModalLabel">'+ o.title +'</h4>'+
                '<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button></div>'+
                '<div class="modal-body"> '+ df_content +' </div>'+
                '<div class="modal-footer"><button type="button" class="btn btn-default cancel" data-dismiss="modal">'+ o.cancelTxt +'</button>&nbsp; &nbsp; &nbsp;'+
                '<button type="button" class="btn btn-primary ok">'+ o.okTxt +'</button></div></div></div></div>';
            return rehtml;
        }
    };
 
    //导入用户定义的变量
    Object.keys( defaults ).forEach(function(key){
        if( custom_configs.hasOwnProperty(key) ) {
            opts[key] = custom_configs[key];
        }
    });
    if(opts.id == 'model') {
        $("#" + opts.id + ".modal").remove();
    }
    creatDialog.init(opts);
};

//代替alert的对话框
acUnit.Alert = function( msg ){
    acUnit.ShowMessageBox({
        id:'model_',
        title:"系统提示",
        html:"<h5>"+ msg +"</h5>",
        width:350,
        height:180});
};