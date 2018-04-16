/**
*   Subscribe and unsubscribe from category notifications.
*
*   @param  integer id      Category ID
*   @param  boolean do_sub  True to subscribe, False to unsubscribe
*/
var ADVTcatSub = function(id, do_sub)
{
    var dataS = {
        "action": do_sub == 1 ? "catsub" : "catunsub",
        "id": id,
    };
    data = $.param(dataS);
    $.ajax({
        type: "POST",
        dataType: "json",
        url: glfusionSiteUrl + "/classifieds/ajax.php",
        data: data,
        success: function(jsonObj) {
            try {
                if (jsonObj.newstate == 1) {
                    document.getElementById("sub_img").style.display = "none";
                    document.getElementById("unsub_img").style.display = "";
                } else {
                    document.getElementById("sub_img").style.display = "";
                    document.getElementById("unsub_img").style.display = "none";
                }
                $.UIkit.notify("<i class='uk-icon-check'></i>&nbsp;" + jsonObj.statusMessage, {timeout: 1000,pos:'top-center'});
            }
            catch(err) {
                alert(jsonObj.statusMessage);
            }
        }
    });
    return false;
};
