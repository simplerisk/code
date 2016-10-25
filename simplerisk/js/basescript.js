/*
 Javascript enabled or not.
 This Function Test Cookie is enabled or not.
 XMLHTTP Object or ActiveX is enabled or not.
*/
function workSpace()
{
	//Test Cookie
	var testcookie='jscookietest=valid';
	document.cookie=testcookie;
	if (document.cookie.indexOf(testcookie)==-1) 
	{
		top.location="html/cookieoff.html";
		return false;
	}
	
	//Test XMLHTTP
	var agt=navigator.userAgent.toLowerCase();
	if (agt.indexOf('msie')!=-1 && document.all && agt.indexOf('opera')==-1 && agt.indexOf('mac')==-1) 
	{
		eval('var c=(agt.indexOf("msie 5")!=-1)?"Microsoft.XMLHTTP":"Msxml2.XMLHTTP";try{new ActiveXObject(c);}catch(e){top.location="html/noactivex.html";}');
	}
	return true;
}



// Basic function
function getRef(name)
{
	return document.all ? document.all[name] : document.getElementById(name);
}
function showDiv(divname)
{
	getRef(divname).style.visibility = "visible";
	getRef(divname).style.display = "";
}
function hideDiv(divname)
{
	getRef(divname).style.visibility = "hidden";
	getRef(divname).style.display = "none";									
}
function Redirect(url)
{
   showLoading();
   window.location.href=url;
}
function RedirectClient(url)
{   
   window.location.href=url;
}
function showLoading()
{
    showDiv("loading");
}
function preloader() 
{
 //loading
	hideDiv("loading");
} 
// end pop up
var IsolLang =
{
	// Language direction : "ltr" (left to right) or "rtl" (right to left).
	Dir					: "ltr",
	Preview				: "Preview",
	BrowseServerBlocked : "The resources browser could not be opened. Make sure that all popup blockers are disabled.",
	DialogBlocked		: "It was not possible to open the dialog window. Make sure all popup blockers are disabled."
	
}
function OpenPopUpBrowser( url, width, height )
{
	// oEditor must be defined.
	var iLeft = ( screen.width  - width ) / 2 ;
	var iTop  = ( screen.width - height ) / 2 ;

	var sOptions = "toolbar=no,status=yes,resizable=yes,dependent=yes" ;
	sOptions += ",width=" + width ;
	sOptions += ",height=" + height ;
	sOptions += ",left=" + iLeft ;
	sOptions += ",top=" + iTop ;
		var oWindow = window.open( url, 'IsolPopUpWindow', sOptions ) ;
		
		if ( oWindow )
		{
			try
			{
				
				var sTest = oWindow.name ; 
				oWindow.opener = window ;
				oWindow.focus();
			}
			catch(e)
			{
				alert(IsolLang.DialogBlocked) ;
			}
		}
		else
			alert(IsolLang.DialogBlocked) ;
}
// end pop up


function isSelectCheckBox(chkboxname)
{
    var frm = document.forms['aspnetForm'];
    if (!frm) 
    {
        frm = document.aspnetForm;
    }
	var isChecked=false;
	for (var i=0;i < frm.elements.length;i++)
	{
		var e = frm.elements[i];
		if (e.type == "checkbox"  && e.name==chkboxname && e.checked==true )
		{
			isChecked=true;
		}
	} 
	return isChecked;
}



// Created by Shankho

function selectAllChildCheckBox(objMstr,chkChildCheckBox)
{
    var frm = document.forms['aspnetForm'];
    if (!frm) 
    {
        frm = document.aspnetForm;
    }
	
	for (var i=0;i < frm.elements.length;i++)
	{
		var e = frm.elements[i];
		if (e.type == "checkbox"  && e.name==chkChildCheckBox)
		{
			e.checked = objMstr.checked;
		}
	}
}

/*
 INPUT : Tag Object
 OUTPUT: If Success Position of the tag in a array Otherwise return null( If Tag Object is invalid)
     Array(left Position ,Top Position); 
 example :-
 var clt=getRef("signin");
 var pos=getTagPostion(clt);
 if(pos!=null)
 {
     left=pos[0];
     top=pos[1];
 }
*/
function getTagPostion(ctl)
{
    /*if(isValidObject(ctl)==false) 
    {
        return null;
    }*/
    var leftpos=0;
    var toppos=0;
    aTag = ctl;
    do
    {
        aTag = aTag.offsetParent;
        leftpos += aTag.offsetLeft;
        toppos += aTag.offsetTop;
    }
    while(aTag.tagName!="BODY");
    leftpos += ctl.offsetLeft;
    toppos += ctl.offsetTop;
    return new Array(leftpos,toppos);
}

function isValidObject(obj) 
{
    if (obj==null)
    { 
        return false;
    }
    if (typeof(objToTest)== "undefined" )
    {
        return false;
    }
    return true;
}


function setAllRowColor(tag,colorCode)
{
	var childs = tag.childNodes;
	for(var i = 0; i < childs.length; i++)
	{
		var child=childs[i];
		child.style.backgroundColor=colorCode;
	} 
}








