var xmlObj;
var xmlObjHD;

function colorImage( ele, imageName )
{

	if ( ele  != null )
	{
		ele.src='images/menubar/color/' + imageName;
	}
}

function grayImage( ele, imageName )
{

	if ( ele  != null )
	{
		ele.src='images/menubar/gray/' + imageName;
	}
}

function checkAll(field)
{
	if ( field != null )
	{
		for (i = 0; i < field.length; i++)
		{
			if (field[i] != null &&  field[i].checked != null && field[i].name != "no")
				field[i].checked = true ;
		}
	}
}

function uncheckAll(field)
{
	if ( field != null )
	{
		for (i = 0; i < field.length; i++)
		{
			if (field[i] != null &&  field[i].checked != null && field[i].name != "no" )
				field[i].checked = false ;
		}
	}
}

function getXmlHttpObject()
{
	var objXMLHttp = null;
	if ( window.XMLHttpRequest )
	{
		objXMLHttp = new XMLHttpRequest();
	}
	else if ( window.ActiveXObject )
	{
		objXMLHttp = new ActiveXObject("Microsoft.XMLHTTP" );
	}
	return objXMLHttp;
}

function getContentImage( node )
{
	xmlObj 		= getXmlHttpObject();
	var contentType = "application/x-www-form-urlencoded; charset=UTF-8";
	var url 	= "ajax/image.search.php";
	var query	= "crit=" + node;	
	
		
	
	xmlObj.onreadystatechange=getContentResponseImage;
	xmlObj.open("post",url,true);
	xmlObj.setRequestHeader("Content-Type", contentType);
	xmlObj.send(query);
	
}

function getContentResponseImage()
{
	if (xmlObj.readyState==2)
	{
		document.getElementById("imageSearchContent").innerHTML='<center><b>Performing Search...</b></center>';
	}
	if (xmlObj.readyState==4 || xmlObj.readyState=="complete")
	{ 
		document.getElementById("imageSearchContent").innerHTML=xmlObj.responseText;
	} 

}

function getContentGroup( node )
{
	xmlObj 		= getXmlHttpObject();
	var contentType = "application/x-www-form-urlencoded; charset=UTF-8";
	var url 	= "ajax/group.search.php";
	var query	= "crit=" + node;	
	
		
	
	xmlObj.onreadystatechange=getContentResponseGroup;
	xmlObj.open("post",url,true);
	xmlObj.setRequestHeader("Content-Type", contentType);
	xmlObj.send(query);
	
}

function getContentResponseGroup()
{
	if (xmlObj.readyState==2)
	{
		document.getElementById("groupSearchContent").innerHTML='<center><b>Performing Search...</b></center>';
	}
	if (xmlObj.readyState==4 || xmlObj.readyState=="complete")
	{ 
		document.getElementById("groupSearchContent").innerHTML=xmlObj.responseText;
	} 

}

function getContentHost( node )
{
	xmlObj 		= getXmlHttpObject();
	var contentType = "application/x-www-form-urlencoded; charset=UTF-8";
	var url 	= "ajax/host.search.php";
	var query	= "crit=" + node;	
	
		
	
	xmlObj.onreadystatechange=getContentResponseHost;
	xmlObj.open("post",url,true);
	xmlObj.setRequestHeader("Content-Type", contentType);
	xmlObj.send(query);
	
}

function getContentResponseHost()
{
	if (xmlObj.readyState==2)
	{
		document.getElementById("hostSearchContent").innerHTML='<center><b>Performing Search...</b></center>';
	}
	if (xmlObj.readyState==4 || xmlObj.readyState=="complete")
	{ 
		document.getElementById("hostSearchContent").innerHTML=xmlObj.responseText;
	} 

}

function getContentTask( node )
{
	xmlObj 		= getXmlHttpObject();
	var contentType = "application/x-www-form-urlencoded; charset=UTF-8";
	var url 	= "ajax/tasks.search.php";
	var query	= "crit=" + node;	
	
		
	
	xmlObj.onreadystatechange=getContentResponseTask;
	xmlObj.open("post",url,true);
	xmlObj.setRequestHeader("Content-Type", contentType);
	xmlObj.send(query);
	
}

function getContentResponseTask()
{
	if (xmlObj.readyState==2)
	{
		document.getElementById("taskSearchContent").innerHTML='<center><b>Performing Search...</b></center>';
	}
	if (xmlObj.readyState==4 || xmlObj.readyState=="complete")
	{ 
		document.getElementById("taskSearchContent").innerHTML=xmlObj.responseText;
	} 

}

function getContentBandwidth()
{
	if (xmlObj == null || xmlObj.readyState==4 || xmlObj.readyState=="complete" )
	{
		xmlObj 			= getXmlHttpObject();
		var contentType 	= "application/x-www-form-urlencoded; charset=UTF-8";
		var url 		= "ajax/bandwidth.update.php";
		var query		= "?no=no";	
		
		
		xmlObj.onreadystatechange=getContentResponseBandwidth;
		xmlObj.open("post",url,true);
		xmlObj.setRequestHeader("Content-Type", contentType);
		xmlObj.send(query);
	}
	
}

function getContentResponseBandwidth()
{
	if (xmlObj != null && (xmlObj.readyState==4 || xmlObj.readyState=="complete"))
	{ 
		document.getElementById("imgObj").innerHTML="<img src=phpimages/bandwidth.phpgraph.php?random=" + Math.random() + " />";
	} 

}

function getContentHD(url)
{

	if (xmlObjHD == null || xmlObjHD.readyState==4 || xmlObjHD.readyState=="complete" )
	{
	
		try 
		{
			xmlObjHD 		= getXmlHttpObject();
			var contentType 	= "application/x-www-form-urlencoded; charset=UTF-8";
			var query		= "?no=no";	
			xmlObjHD.onreadystatechange=getContentResponseHD;
			xmlObjHD.open("post",url,true);
			xmlObjHD.setRequestHeader("Content-Type", contentType);
			xmlObjHD.send(query);
		} 
		catch (e) 
		{
			document.getElementById("remainingfreespace").innerHTML=e + "<p>(Try using the server's IP address or hostname instead of localhost.)</p>";
		}	
		
	}
	
}

function getContentResponseHD()
{
	if (xmlObjHD != null && (xmlObjHD.readyState==4 || xmlObjHD.readyState=="complete"))
	{ 
		var strRes = xmlObjHD.responseText;
		if ( strRes != null )
		{
			var arRes = strRes.split("@");
			if ( arRes.length == 2 )
			{
				var totalspace = Math.round( (Number(arRes[0]) + Number(arRes[1]) ) * 100  ) / 100;
				var pct = Math.round( (arRes[1] / totalspace) * 100 );
				var pctText = Math.round( (arRes[1] / totalspace) * 10000 ) / 100;
				
				document.getElementById("remainingfreespace").innerHTML="<p class=noSpace>Total Space: " +  totalspace + " GB</p><p class=noSpace>Free Space: " + arRes[0] + " GB</p><p class=noSpace>Used Space: " + arRes[1] + " GB</p>";
				document.getElementById("remainingfreespace").innerHTML+="<p class=\"noSpace\"><div class=\"pb\"><img src=\"images/openslots.jpg\" height=25 width=\"" + pct  + "%\" /></div></p>";
				document.getElementById("remainingfreespace").innerHTML+="<p class=\"taskPCT\">" + pctText + "% used</p>";
			}				
		}
	} 

}

function getContentSnapin( node )
{
	xmlObj 		= getXmlHttpObject();
	var contentType = "application/x-www-form-urlencoded; charset=UTF-8";
	var url 	= "ajax/snapin.search.php";
	var query	= "crit=" + node;	
	
		
	
	xmlObj.onreadystatechange=getContentResponseSnapin;
	xmlObj.open("post",url,true);
	xmlObj.setRequestHeader("Content-Type", contentType);
	xmlObj.send(query);
	
}

function getContentResponseSnapin()
{
	if (xmlObj.readyState==2)
	{
		document.getElementById("snapinSearchContent").innerHTML='<center><b>Performing Search...</b></center>';
	}
	if (xmlObj.readyState==4 || xmlObj.readyState=="complete")
	{ 
		document.getElementById("snapinSearchContent").innerHTML=xmlObj.responseText;
	} 

}
