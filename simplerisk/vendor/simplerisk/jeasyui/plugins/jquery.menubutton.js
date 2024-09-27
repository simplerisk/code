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
var _3=$.data(_2,"menubutton").options;
var _4=$(_2);
_4.linkbutton(_3);
if(_3.hasDownArrow){
_4.removeClass(_3.cls.btn1+" "+_3.cls.btn2).addClass("m-btn");
_4.removeClass("m-btn-small m-btn-medium m-btn-large").addClass("m-btn-"+_3.size);
var _5=_4.find(".l-btn-left");
$("<span></span>").addClass(_3.cls.arrow).appendTo(_5);
$("<span></span>").addClass("m-btn-line").appendTo(_5);
}
$(_2).menubutton("resize");
if(_3.menu){
if(typeof _3.menu=="string"){
$(_3.menu).menu({duration:_3.duration});
}else{
if(!(_3.menu instanceof jQuery)){
var _6=_3.menu;
_3.menu=$("<div></div>").appendTo("body").menu({duration:_3.duration});
_3.menu.menu("appendItems",_6);
}
}
var _7=$(_3.menu).menu("options");
var _8=_7.onShow;
var _9=_7.onHide;
$.extend(_7,{onShow:function(){
var _a=$(this).menu("options");
var _b=$(_a.alignTo);
var _c=_b.menubutton("options");
_b.addClass((_c.plain==true)?_c.cls.btn2:_c.cls.btn1);
_8.call(this);
},onHide:function(){
var _d=$(this).menu("options");
var _e=$(_d.alignTo);
var _f=_e.menubutton("options");
_e.removeClass((_f.plain==true)?_f.cls.btn2:_f.cls.btn1);
_9.call(this);
}});
}
};
function _10(_11){
var _12=$.data(_11,"menubutton").options;
var btn=$(_11);
var t=btn.find("."+_12.cls.trigger);
if(!t.length){
t=btn;
}
t._unbind(".menubutton");
var _13=null;
t._bind(_12.showEvent+".menubutton",function(){
if(!_14()){
_13=setTimeout(function(){
_15(_11);
},_12.duration);
return false;
}
})._bind(_12.hideEvent+".menubutton",function(){
if(_13){
clearTimeout(_13);
}
$(_12.menu).triggerHandler("mouseleave");
});
function _14(){
return $(_11).linkbutton("options").disabled;
};
};
function _15(_16){
var _17=$(_16).menubutton("options");
if(_17.disabled||!_17.menu){
return;
}
$("body>div.menu-top").menu("hide");
var btn=$(_16);
var mm=$(_17.menu);
if(mm.length){
mm.menu("options").alignTo=btn;
mm.menu("show",{alignTo:btn,align:_17.menuAlign});
}
btn.blur();
};
$.fn.menubutton=function(_18,_19){
if(typeof _18=="string"){
var _1a=$.fn.menubutton.methods[_18];
if(_1a){
return _1a(this,_19);
}else{
return this.linkbutton(_18,_19);
}
}
_18=_18||{};
return this.each(function(){
var _1b=$.data(this,"menubutton");
if(_1b){
$.extend(_1b.options,_18);
}else{
$.data(this,"menubutton",{options:$.extend({},$.fn.menubutton.defaults,$.fn.menubutton.parseOptions(this),_18)});
$(this)._propAttr("disabled",false);
}
_1(this);
_10(this);
});
};
$.fn.menubutton.methods={options:function(jq){
var _1c=jq.linkbutton("options");
return $.extend($.data(jq[0],"menubutton").options,{toggle:_1c.toggle,selected:_1c.selected,disabled:_1c.disabled});
},destroy:function(jq){
return jq.each(function(){
var _1d=$(this).menubutton("options");
if(_1d.menu){
$(_1d.menu).menu("destroy");
}
$(this).remove();
});
}};
$.fn.menubutton.parseOptions=function(_1e){
var t=$(_1e);
return $.extend({},$.fn.linkbutton.parseOptions(_1e),$.parser.parseOptions(_1e,["menu",{plain:"boolean",hasDownArrow:"boolean",duration:"number"}]));
};
$.fn.menubutton.defaults=$.extend({},$.fn.linkbutton.defaults,{plain:true,hasDownArrow:true,menu:null,menuAlign:"left",duration:100,showEvent:"mouseenter",hideEvent:"mouseleave",cls:{btn1:"m-btn-active",btn2:"m-btn-plain-active",arrow:"m-btn-downarrow",trigger:"m-btn"}});
})(jQuery);

