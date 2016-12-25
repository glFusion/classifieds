/*  Subscribe and unsubscribe from category notifications.
*/
var xmlHttp;
function ADVTcatSub(id, do_sub)
{
  xmlHttp=ADVTgetXmlHttpObject();
  if (xmlHttp==null) {
    alert ("Browser does not support HTTP Request")
    return
  }
  var action = do_sub == 1 ? "catsub" : "catunsub";
  var url=glfusionSiteUrl + "/classifieds/ajax.php?action="+action+"&id="+id;
  url=url+"&sid="+Math.random();
  xmlHttp.onreadystatechange=ADVTsubStateChanged;
  xmlHttp.open("GET",url,true);
  xmlHttp.send(null);
}

function ADVTsubStateChanged()
{
  var newstate;

  if (xmlHttp.readyState==4 || xmlHttp.readyState=="complete") {
    jsonObj = JSON.parse(xmlHttp.responseText)

    // Get the status and category id
    if (jsonObj.newstate == 1) {
        document.getElementById("sub_img").style.display = "none";
        document.getElementById("unsub_img").style.display = "";
    } else {
        document.getElementById("sub_img").style.display = "";
        document.getElementById("unsub_img").style.display = "none";
    }
  }
}
