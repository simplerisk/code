/**
 * EasyUI for jQuery 1.10.19
 * 
 * Copyright (c) 2009-2024 www.jeasyui.com. All rights reserved.
 *
 * Licensed under the commercial license: http://www.jeasyui.com/license_commercial.php
 * To use it on other terms please contact us: info@jeasyui.com
 *
 */
(function($){
function _1(_2){
var _3=$.data(_2,"drawer").options;
$(_2).dialog($.extend({},_3,{cls:"drawer f-column window-shadow layout-panel layout-collapsed layout-panel-"+_3.region,bodyCls:"f-full",collapsed:false,top:0,left:"auto",right:"auto",onResize:function(w,h){
if(_3.collapsed){
var _4=$(_2).dialog("dialog").width();
$(_2).dialog("dialog").css({display:"",left:_3.region=="east"?"auto":-_4,right:_3.region=="east"?-_4:"auto"});
}
_3.onResize.call(this,w,h);
}}));
$(_2).dialog("header").find(".panel-tool-collapse").addClass("layout-button-"+(_3.region=="east"?"right":"left"))._unbind()._bind("click",function(){
_7(_2);
});
var _5=$(_2).dialog("dialog").width();
$(_2).dialog("dialog").css({display:"",left:_3.region=="east"?"auto":-_5,right:_3.region=="east"?-_5:"auto"});
var _6=$(_2).data("window").mask;
$(_6).addClass("drawer-mask").hide()._unbind()._bind("click",function(){
_7(_2);
});
};
function _8(_9){
var _a=$.data(_9,"drawer").options;
if(_a.onBeforeExpand.call(_9)==false){
return;
}
var _b=$(_9).dialog("dialog").width();
var _c=$(_9).data("window").mask;
$(_c).show();
$(_9).show().css({display:""}).dialog("dialog").animate({left:_a.region=="east"?"auto":0,right:_a.region=="east"?0:"auto"},function(){
$(this).removeClass("layout-collapsed");
_a.collapsed=false;
_a.onExpand.call(_9);
});
};
function _7(_d){
var _e=$.data(_d,"drawer").options;
if(_e.onBeforeCollapse.call(_d)==false){
return;
}
var _f=$(_d).dialog("dialog").width();
$(_d).show().css({display:""}).dialog("dialog").animate({left:_e.region=="east"?"auto":-_f,right:_e.region=="east"?-_f:"auto"},function(){
$(this).addClass("layout-collapsed");
var _10=$(_d).data("window").mask;
$(_10).hide();
_e.collapsed=true;
_e.onCollapse.call(this);
});
};
$.fn.drawer=function(_11,_12){
if(typeof _11=="string"){
var _13=$.fn.drawer.methods[_11];
if(_13){
return _13(this,_12);
}else{
return this.dialog(_11,_12);
}
}
_11=_11||{};
this.each(function(){
var _14=$.data(this,"drawer");
if(_14){
$.extend(_14.options,_11);
}else{
var _15=$.extend({},$.fn.drawer.defaults,$.fn.drawer.parseOptions(this),_11);
$.data(this,"drawer",{options:_15});
}
_1(this);
});
};
$.fn.drawer.methods={options:function(jq){
var _16=$.data(jq[0],"drawer").options;
return $.extend(jq.dialog("options"),{region:_16.region,collapsed:_16.collapsed});
},expand:function(jq){
return jq.each(function(){
_8(this);
});
},collapse:function(jq){
return jq.each(function(){
_7(this);
});
}};
$.fn.drawer.parseOptions=function(_17){
return $.extend({},$.fn.dialog.parseOptions(_17),$.parser.parseOptions(_17,["region"]));
};
$.fn.drawer.defaults=$.extend({},$.fn.dialog.defaults,{border:false,region:"east",title:null,shadow:false,fixed:true,collapsed:true,closable:false,modal:true,draggable:false});
})(jQuery);

