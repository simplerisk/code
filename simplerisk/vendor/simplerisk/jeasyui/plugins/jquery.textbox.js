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
var _1=0;
function _2(_3){
$(_3).addClass("textbox-f").hide();
var _4=$("<span class=\"textbox\">"+"<input class=\"textbox-text\" autocomplete=\"off\">"+"<input type=\"hidden\" class=\"textbox-value\">"+"</span>").insertAfter(_3);
var _5=$(_3).attr("name");
if(_5){
_4.find("input.textbox-value").attr("name",_5);
$(_3).removeAttr("name").attr("textboxName",_5);
}
return _4;
};
function _6(_7){
var _8=$.data(_7,"textbox");
var _9=_8.options;
var tb=_8.textbox;
var _a="_easyui_textbox_input"+(++_1);
tb.addClass(_9.cls);
tb.find(".textbox-text").remove();
if(_9.multiline){
$("<textarea id=\""+_a+"\" class=\"textbox-text\" autocomplete=\"off\"></textarea>").prependTo(tb);
}else{
$("<input id=\""+_a+"\" type=\""+_9.type+"\" class=\"textbox-text\" autocomplete=\"off\">").prependTo(tb);
}
$("#"+_a).attr("tabindex",$(_7).attr("tabindex")||"").css("text-align",_7.style.textAlign||"");
tb.find(".textbox-addon").remove();
var bb=_9.icons?$.extend(true,[],_9.icons):[];
if(_9.iconCls){
bb.push({iconCls:_9.iconCls,disabled:true});
}
if(bb.length){
var bc=$("<span class=\"textbox-addon\"></span>").prependTo(tb);
bc.addClass("textbox-addon-"+_9.iconAlign);
for(var i=0;i<bb.length;i++){
bc.append("<a href=\"javascript:;\" class=\"textbox-icon "+bb[i].iconCls+"\" icon-index=\""+i+"\" tabindex=\"-1\"></a>");
}
}
tb.find(".textbox-button").remove();
if(_9.buttonText||_9.buttonIcon){
var _b=$("<a href=\"javascript:;\" class=\"textbox-button\"></a>").prependTo(tb);
_b.addClass("textbox-button-"+_9.buttonAlign).linkbutton({text:_9.buttonText,iconCls:_9.buttonIcon,onClick:function(){
var t=$(this).parent().prev();
t.textbox("options").onClickButton.call(t[0]);
}});
}
if(_9.label){
if(typeof _9.label=="object"){
_8.label=$(_9.label);
_8.label.attr("for",_a);
}else{
$(_8.label).remove();
_8.label=$("<label class=\"textbox-label\"></label>").html(_9.label);
_8.label.css("textAlign",_9.labelAlign).attr("for",_a);
if(_9.labelPosition=="after"){
_8.label.insertAfter(tb);
}else{
_8.label.insertBefore(_7);
}
_8.label.removeClass("textbox-label-left textbox-label-right textbox-label-top");
_8.label.addClass("textbox-label-"+_9.labelPosition);
}
}else{
$(_8.label).remove();
}
_c(_7);
_d(_7,_9.disabled);
_e(_7,_9.readonly);
};
function _f(_10){
var _11=$.data(_10,"textbox");
var tb=_11.textbox;
tb.find(".textbox-text").validatebox("destroy");
tb.remove();
$(_11.label).remove();
$(_10).remove();
};
function _12(_13,_14){
var _15=$.data(_13,"textbox");
var _16=_15.options;
var tb=_15.textbox;
var _17=tb.parent();
if(_14){
if(typeof _14=="object"){
$.extend(_16,_14);
}else{
_16.width=_14;
}
}
if(isNaN(parseInt(_16.width))){
var c=$(_13).clone();
c.css("visibility","hidden");
c.insertAfter(_13);
_16.width=c.outerWidth();
c.remove();
}
if(_16.autoSize){
$(_13).textbox("autoSize");
_16.width=tb.css("width","").outerWidth();
if(_16.labelPosition!="top"){
_16.width+=$(_15.label).outerWidth();
}
}
var _18=tb.is(":visible");
if(!_18){
tb.appendTo("body");
}
var _19=tb.find(".textbox-text");
var btn=tb.find(".textbox-button");
var _1a=tb.find(".textbox-addon");
var _1b=_1a.find(".textbox-icon");
if(_16.height=="auto"){
_19.css({margin:"",paddingTop:"",paddingBottom:"",height:"",lineHeight:""});
}
tb._size(_16,_17);
if(_16.label&&_16.labelPosition){
if(_16.labelPosition=="top"){
_15.label._size({width:_16.labelWidth=="auto"?tb.outerWidth():_16.labelWidth},tb);
if(_16.height!="auto"){
tb._size("height",tb.outerHeight()-_15.label.outerHeight());
}
}else{
_15.label._size({width:_16.labelWidth,height:tb.outerHeight()},tb);
if(!_16.multiline){
_15.label.css("lineHeight",_15.label.height()+"px");
}
tb._size("width",tb.outerWidth()-_15.label.outerWidth());
}
}
if(_16.buttonAlign=="left"||_16.buttonAlign=="right"){
btn.linkbutton("resize",{height:tb.height()});
}else{
btn.linkbutton("resize",{width:"100%"});
}
var _1c=tb.width()-_1b.length*_16.iconWidth-_1d("left")-_1d("right");
var _1e=_16.height=="auto"?_19.outerHeight():(tb.height()-_1d("top")-_1d("bottom"));
_1a.css(_16.iconAlign,_1d(_16.iconAlign)+"px");
_1a.css("top",_1d("top")+"px");
_1b.css({width:_16.iconWidth+"px",height:_1e+"px"});
_19.css({paddingLeft:(_13.style.paddingLeft||""),paddingRight:(_13.style.paddingRight||""),marginLeft:_1f("left"),marginRight:_1f("right"),marginTop:_1d("top"),marginBottom:_1d("bottom")});
if(_16.multiline){
_19.css({paddingTop:(_13.style.paddingTop||""),paddingBottom:(_13.style.paddingBottom||"")});
_19._outerHeight(_1e);
}else{
_19.css({paddingTop:0,paddingBottom:0,height:_1e+"px",lineHeight:_1e+"px"});
}
_19._outerWidth(_1c);
_16.onResizing.call(_13,_16.width,_16.height);
if(!_18){
tb.insertAfter(_13);
}
_16.onResize.call(_13,_16.width,_16.height);
function _1f(_20){
return (_16.iconAlign==_20?_1a._outerWidth():0)+_1d(_20);
};
function _1d(_21){
var w=0;
btn.filter(".textbox-button-"+_21).each(function(){
if(_21=="left"||_21=="right"){
w+=$(this).outerWidth();
}else{
w+=$(this).outerHeight();
}
});
return w;
};
};
function _22(_23){
var _24=$(_23).textbox("options");
var _25=$(_23).textbox("textbox");
var _26=$(_23).next();
var tmp=$("<span></span>").appendTo("body");
tmp.attr("style",_25.attr("style"));
tmp.css({position:"absolute",top:-9999,left:-9999,width:"auto",fontFamily:_25.css("fontFamily"),fontSize:_25.css("fontSize"),fontWeight:_25.css("fontWeight"),padding:_25.css("padding"),whiteSpace:"nowrap"});
var _27=_28(_25.val());
var _29=_28(_24.prompt||"");
tmp.remove();
var _2a=Math.min(Math.max(_27,_29)+20,_26.width());
var _2a=Math.max(_27,_29);
_25._outerWidth(_2a);
function _28(val){
var s=val.replace(/&/g,"&amp;").replace(/\s/g," ").replace(/</g,"&lt;").replace(/>/g,"&gt;");
tmp.html(s);
return tmp.outerWidth();
};
};
function _c(_2b){
var _2c=$(_2b).textbox("options");
var _2d=$(_2b).textbox("textbox");
_2d.validatebox($.extend({},_2c,{deltaX:function(_2e){
return $(_2b).textbox("getTipX",_2e);
},deltaY:function(_2f){
return $(_2b).textbox("getTipY",_2f);
},onBeforeValidate:function(){
_2c.onBeforeValidate.call(_2b);
var box=$(this);
if(!box.is(":focus")){
if(box.val()!==_2c.value){
_2c.oldInputValue=box.val();
box.val(_2c.value);
}
}
},onValidate:function(_30){
var box=$(this);
if(_2c.oldInputValue!=undefined){
box.val(_2c.oldInputValue);
_2c.oldInputValue=undefined;
}
var tb=box.parent();
if(_30){
tb.removeClass("textbox-invalid");
}else{
tb.addClass("textbox-invalid");
}
_2c.onValidate.call(_2b,_30);
}}));
};
function _31(_32){
var _33=$.data(_32,"textbox");
var _34=_33.options;
var tb=_33.textbox;
var _35=tb.find(".textbox-text");
_35.attr("placeholder",_34.prompt);
_35._unbind(".textbox");
$(_33.label)._unbind(".textbox");
if(!_34.disabled&&!_34.readonly){
if(_33.label){
$(_33.label)._bind("click.textbox",function(e){
if(!_34.hasFocusMe){
_35.focus();
$(_32).textbox("setSelectionRange",{start:0,end:_35.val().length});
}
});
}
_35._bind("blur.textbox",function(e){
if(!tb.hasClass("textbox-focused")){
return;
}
_34.value=$(this).val();
if(_34.value==""){
$(this).val(_34.prompt).addClass("textbox-prompt");
}else{
$(this).removeClass("textbox-prompt");
}
tb.removeClass("textbox-focused");
tb.closest(".form-field").removeClass("form-field-focused");
})._bind("focus.textbox",function(e){
_34.hasFocusMe=true;
if(tb.hasClass("textbox-focused")){
return;
}
if($(this).val()!=_34.value){
$(this).val(_34.value);
}
$(this).removeClass("textbox-prompt");
tb.addClass("textbox-focused");
tb.closest(".form-field").addClass("form-field-focused");
});
for(var _36 in _34.inputEvents){
_35._bind(_36+".textbox",{target:_32},_34.inputEvents[_36]);
}
}
var _37=tb.find(".textbox-addon");
_37._unbind()._bind("click",{target:_32},function(e){
var _38=$(e.target).closest("a.textbox-icon:not(.textbox-icon-disabled)");
if(_38.length){
var _39=parseInt(_38.attr("icon-index"));
var _3a=_34.icons[_39];
if(_3a&&_3a.handler){
_3a.handler.call(_38[0],e);
}
_34.onClickIcon.call(_32,_39);
}
});
_37.find(".textbox-icon").each(function(_3b){
var _3c=_34.icons[_3b];
var _3d=$(this);
if(!_3c||_3c.disabled||_34.disabled||_34.readonly){
_3d.addClass("textbox-icon-disabled");
}else{
_3d.removeClass("textbox-icon-disabled");
}
});
var btn=tb.find(".textbox-button");
btn.linkbutton((_34.disabled||_34.readonly)?"disable":"enable");
tb._unbind(".textbox")._bind("_resize.textbox",function(e,_3e){
if($(this).hasClass("easyui-fluid")||_3e){
_12(_32);
}
return false;
});
};
function _d(_3f,_40){
var _41=$.data(_3f,"textbox");
var _42=_41.options;
var tb=_41.textbox;
var _43=tb.find(".textbox-text");
var ss=$(_3f).add(tb.find(".textbox-value"));
_42.disabled=_40;
if(_42.disabled){
_43.blur();
_43.validatebox("disable");
tb.addClass("textbox-disabled");
ss._propAttr("disabled",true);
$(_41.label).addClass("textbox-label-disabled");
}else{
_43.validatebox("enable");
tb.removeClass("textbox-disabled");
ss._propAttr("disabled",false);
$(_41.label).removeClass("textbox-label-disabled");
}
};
function _e(_44,_45){
var _46=$.data(_44,"textbox");
var _47=_46.options;
var tb=_46.textbox;
var _48=tb.find(".textbox-text");
_47.readonly=_45==undefined?true:_45;
if(_47.readonly){
_48.triggerHandler("blur.textbox");
}
_48.validatebox("readonly",_47.readonly);
if(_47.readonly){
tb.addClass("textbox-readonly");
$(_46.label).addClass("textbox-label-readonly");
}else{
tb.removeClass("textbox-readonly");
$(_46.label).removeClass("textbox-label-readonly");
}
};
function _49(_4a,_4b){
var _4c=$.data(_4a,"textbox");
var _4d=_4c.options;
var tb=_4c.textbox;
var _4e=tb.find(".textbox-text");
_4d.editable=_4b==undefined?true:_4b;
_4e.validatebox("setEditable",_4d.editable);
_e(_4a,_4d.readonly);
};
$.fn.textbox=function(_4f,_50){
if(typeof _4f=="string"){
var _51=$.fn.textbox.methods[_4f];
if(_51){
return _51(this,_50);
}else{
return this.each(function(){
var _52=$(this).textbox("textbox");
_52.validatebox(_4f,_50);
});
}
}
_4f=_4f||{};
return this.each(function(){
var _53=$.data(this,"textbox");
if(_53){
$.extend(_53.options,_4f);
if(_4f.value!=undefined){
_53.options.originalValue=_4f.value;
}
}else{
_53=$.data(this,"textbox",{options:$.extend({},$.fn.textbox.defaults,$.fn.textbox.parseOptions(this),_4f),textbox:_2(this)});
_53.options.originalValue=_53.options.value;
}
_6(this);
_31(this);
if(_53.options.doSize){
_12(this);
}
var _54=_53.options.value;
_53.options.value="";
$(this).textbox("initValue",_54);
});
};
$.fn.textbox.methods={options:function(jq){
return $.data(jq[0],"textbox").options;
},cloneFrom:function(jq,_55){
return jq.each(function(){
var t=$(this);
if(t.data("textbox")){
return;
}
if(!$(_55).data("textbox")){
$(_55).textbox();
}
var _56=$.extend(true,{},$(_55).textbox("options"));
var _57=t.attr("name")||"";
t.addClass("textbox-f").hide();
t.removeAttr("name").attr("textboxName",_57);
var _58=$(_55).next().clone().insertAfter(t);
var _59="_easyui_textbox_input"+(++_1);
_58.find(".textbox-value").attr("name",_57);
_58.find(".textbox-text").attr("id",_59);
var _5a=$($(_55).textbox("label")).clone();
if(_5a.length){
_5a.attr("for",_59);
if(_56.labelPosition=="after"){
_5a.insertAfter(t.next());
}else{
_5a.insertBefore(t);
}
}
$.data(this,"textbox",{options:_56,textbox:_58,label:(_5a.length?_5a:undefined)});
var _5b=$(_55).textbox("button");
if(_5b.length){
t.textbox("button").linkbutton($.extend(true,{},_5b.linkbutton("options")));
}
_31(this);
_c(this);
});
},textbox:function(jq){
return $.data(jq[0],"textbox").textbox.find(".textbox-text");
},button:function(jq){
return $.data(jq[0],"textbox").textbox.find(".textbox-button");
},label:function(jq){
return $.data(jq[0],"textbox").label;
},destroy:function(jq){
return jq.each(function(){
_f(this);
});
},resize:function(jq,_5c){
return jq.each(function(){
_12(this,_5c);
});
},autoSize:function(jq){
return jq.each(function(){
_22(this);
});
},disable:function(jq){
return jq.each(function(){
_d(this,true);
_31(this);
});
},enable:function(jq){
return jq.each(function(){
_d(this,false);
_31(this);
});
},readonly:function(jq,_5d){
return jq.each(function(){
_e(this,_5d);
_31(this);
});
},setEditable:function(jq,_5e){
return jq.each(function(){
_49(this,_5e);
_31(this);
});
},isValid:function(jq){
return jq.textbox("textbox").validatebox("isValid");
},clear:function(jq){
return jq.each(function(){
$(this).textbox("setValue","");
});
},setText:function(jq,_5f){
return jq.each(function(){
var _60=$(this).textbox("options");
var _61=$(this).textbox("textbox");
_5f=_5f==undefined?"":String(_5f);
if($(this).textbox("getText")!=_5f){
_61.val(_5f);
}
_60.value=_5f;
if(!_61.is(":focus")){
if(_5f){
_61.removeClass("textbox-prompt");
}else{
_61.val(_60.prompt).addClass("textbox-prompt");
}
}
if(_60.value){
$(this).closest(".form-field").removeClass("form-field-empty");
}else{
$(this).closest(".form-field").addClass("form-field-empty");
}
$(this).textbox("validate");
if(_60.autoSize){
$(this).textbox("resize");
}
});
},initValue:function(jq,_62){
return jq.each(function(){
var _63=$.data(this,"textbox");
$(this).textbox("setText",_62);
_63.textbox.find(".textbox-value").val(_62);
$(this).val(_62);
});
},setValue:function(jq,_64){
return jq.each(function(){
var _65=$.data(this,"textbox").options;
var _66=$(this).textbox("getValue");
$(this).textbox("initValue",_64);
if(_66!=_64){
_65.onChange.call(this,_64,_66);
$(this).closest("form").trigger("_change",[this]);
}
});
},getText:function(jq){
var _67=jq.textbox("textbox");
if(_67.is(":focus")){
return _67.val();
}else{
return jq.textbox("options").value;
}
},getValue:function(jq){
return jq.data("textbox").textbox.find(".textbox-value").val();
},reset:function(jq){
return jq.each(function(){
var _68=$(this).textbox("options");
$(this).textbox("textbox").val(_68.originalValue);
$(this).textbox("setValue",_68.originalValue);
});
},getIcon:function(jq,_69){
return jq.data("textbox").textbox.find(".textbox-icon:eq("+_69+")");
},getTipX:function(jq,_6a){
var _6b=jq.data("textbox");
var _6c=_6b.options;
var tb=_6b.textbox;
var _6d=tb.find(".textbox-text");
var _6a=_6a||_6c.tipPosition;
var p1=tb.offset();
var p2=_6d.offset();
var w1=tb.outerWidth();
var w2=_6d.outerWidth();
if(_6a=="right"){
return w1-w2-p2.left+p1.left;
}else{
if(_6a=="left"){
return p1.left-p2.left;
}else{
return (w1-w2-p2.left+p1.left)/2-(p2.left-p1.left)/2;
}
}
},getTipY:function(jq,_6e){
var _6f=jq.data("textbox");
var _70=_6f.options;
var tb=_6f.textbox;
var _71=tb.find(".textbox-text");
var _6e=_6e||_70.tipPosition;
var p1=tb.offset();
var p2=_71.offset();
var h1=tb.outerHeight();
var h2=_71.outerHeight();
if(_6e=="left"||_6e=="right"){
return (h1-h2-p2.top+p1.top)/2-(p2.top-p1.top)/2;
}else{
if(_6e=="bottom"){
return (h1-h2-p2.top+p1.top);
}else{
return (p1.top-p2.top);
}
}
},getSelectionStart:function(jq){
return jq.textbox("getSelectionRange").start;
},getSelectionRange:function(jq){
var _72=jq.textbox("textbox")[0];
var _73=0;
var end=0;
if(typeof _72.selectionStart=="number"){
_73=_72.selectionStart;
end=_72.selectionEnd;
}else{
if(_72.createTextRange){
var s=document.selection.createRange();
var _74=_72.createTextRange();
_74.setEndPoint("EndToStart",s);
_73=_74.text.length;
end=_73+s.text.length;
}
}
return {start:_73,end:end};
},setSelectionRange:function(jq,_75){
return jq.each(function(){
var _76=$(this).textbox("textbox")[0];
var _77=_75.start;
var end=_75.end;
if(_76.setSelectionRange){
_76.setSelectionRange(_77,end);
}else{
if(_76.createTextRange){
var _78=_76.createTextRange();
_78.collapse();
_78.moveEnd("character",end);
_78.moveStart("character",_77);
_78.select();
}
}
});
},show:function(jq){
return jq.each(function(){
$(this).next().show();
$($(this).textbox("label")).show();
});
},hide:function(jq){
return jq.each(function(){
$(this).next().hide();
$($(this).textbox("label")).hide();
});
}};
$.fn.textbox.parseOptions=function(_79){
var t=$(_79);
return $.extend({},$.fn.validatebox.parseOptions(_79),$.parser.parseOptions(_79,["prompt","iconCls","iconAlign","buttonText","buttonIcon","buttonAlign","label","labelPosition","labelAlign","width","height",{multiline:"boolean",iconWidth:"number",labelWidth:"number",autoSize:"boolean"}]),{value:(t.val()||undefined),type:(t.attr("type")?t.attr("type"):undefined)});
};
$.fn.textbox.defaults=$.extend({},$.fn.validatebox.defaults,{doSize:true,autoSize:false,width:"auto",height:"auto",cls:null,prompt:"",value:"",type:"text",multiline:false,icons:[],iconCls:null,iconAlign:"right",iconWidth:26,buttonText:"",buttonIcon:null,buttonAlign:"right",label:null,labelWidth:"auto",labelPosition:"before",labelAlign:"left",inputEvents:{blur:function(e){
var t=$(e.data.target);
var _7a=t.textbox("options");
if(t.textbox("getValue")!=_7a.value){
t.textbox("setValue",_7a.value);
}
},keydown:function(e){
if(e.keyCode==13){
var t=$(e.data.target);
t.textbox("setValue",t.textbox("getText"));
}
if($(e.data.target).textbox("options").autoSize){
setTimeout(function(){
$(e.data.target).textbox("resize");
},0);
}
}},onChange:function(_7b,_7c){
},onResizing:function(_7d,_7e){
},onResize:function(_7f,_80){
},onClickButton:function(){
},onClickIcon:function(_81){
}});
})(jQuery);

