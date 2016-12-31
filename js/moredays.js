/*  Subscribe and unsubscribe from category notifications.
*/
var xmlHttp;
function ADVTmoreDays(id, days)
{
  xmlHttp=ADVTgetXmlHttpObject();
  if (xmlHttp==null) {
    alert ("Browser does not support HTTP Request")
    return
  }
  var url=glfusionSiteUrl + "/classifieds/ajax.php?action=moredays&id="+id+"&days="+days;
  url=url+"&sid="+Math.random();
  xmlHttp.onreadystatechange=ADVTdaysStateChanged;
  xmlHttp.open("GET",url,true);
  xmlHttp.send(null);
}

function ADVTdaysStateChanged()
{
  if (xmlHttp.readyState==4 || xmlHttp.readyState=="complete") {
    jsonObj = JSON.parse(xmlHttp.responseText);
    // Get the new max days to add
    document.getElementById("f_max_add_days").value = '';
    document.getElementById("max_add_days").innerHTML = jsonObj.maxdays;
    document.getElementById("exp_date").innerHTML = jsonObj.expdate;
  }
}
