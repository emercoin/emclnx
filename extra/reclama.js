
    var xmlDoc = null ;

  function load(url,id,server,userid) {
	  var xmlDoc;
	 
    if (typeof window.ActiveXObject != 'undefined' ) {
      xmlDoc = new ActiveXObject("Microsoft.XMLHTTP");
      xmlDoc.onreadystatechange = process ;
	  	xmlDoc.zzz=id;
	  	xmlDoc.url=url;
		xmlDoc.userid=userid;
	  	xmlDoc.server=server;

    }
    else {
      xmlDoc = new XMLHttpRequest();
	  	xmlDoc.zzz=id;
	  	xmlDoc.url=url;
		xmlDoc.userid=userid;
	  	xmlDoc.server=server;	  
      		xmlDoc.onload = process ;
    }
//    xmlDoc.open( "GET", "background.html", true );
    xmlDoc.open( "GET", url, true );
    xmlDoc.send( null );
  }

function getHead(sep,str){
	pos=str.indexOf(sep);
	return str.substr(0,pos);
}
function getTail(sep,str){
	pos=str.indexOf(sep);
	return str.substr(pos+sep.length,str.length);
}
function getBetween(startWord,str,endWord){
	tail=getTail(startWord,str);
	head=getHead(endWord,tail)
	return head;
	
	}


  function process() {
	    // alert (this.server+":::>"+this.zzz);
    if ( this.readyState != 4 ) return ;
        //  document.getElementById("output").value = xmlDoc.responseText ;
		rez=""+this.responseText;		
		link=getBetween("'",rez,"'");
		caption=getBetween(">",rez,"<");
		user=getTail("://",this.server);
		user=user.replace("/","");
		user=user.replace(".","_");
		//alert (caption);
		document.getElementById(this.zzz).innerHTML = caption;
		//alert (this.server+"/"+link+":"+this.userid);
		document.getElementById(this.zzz).url = this.server+"/"+link+":"+this.userid;

		document.getElementById(this.zzz).caption = caption ;
		document.getElementById(this.zzz).onclick = function (e) {
	          // alert(this.url);
			setTimeout( 'location="'+this.url+'";', 2 );
			       //alert(this.url);
				  // var e = e || window.event;
				  // var target = e.target || e.srcElement;
				  // if (this == target) alert("Вместо меня должно стоять модальное окно");
				  

}

		
		
  }

  function empty() {
    //document.getElementById("output").value = '<empty>' ;
	document.getElementById(xmlDoc.zzz).innerHTML = '<empty>' ;
  }


function callAjax(url) {
        var xmlhr;
		

        if (window.XMLHttpRequest) {
            xmlhr = new XMLHttpRequest();			
        } else {
            xmlhr = new ActiveXObject("Microsoft.XMLHTTP");
        }

        xmlhr.onreadystatechange = function () {
            if (xmlhr.readyState == 4 && xmlhr.status == 200) {
                alert(xmlhr.responseText);
            }
        }
        xmlhr.open("GET", url, true);
        xmlhr.send();
    }

    function myFunction(id1, id2) {
        callAjax("http://example.com/ajax2.php?id2=" + id1);
        callAjax("http://example.com/ajax2.php?id2=" + id2);
    }