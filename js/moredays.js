/**
*   Add more days to an ad's run.
*
*   @param  string  id      Ad ID
*   @param  integer days    Number of days to add.
*/
function ADVTmoreDays(id, days)
{
    var dataS = {
        "action": "moredays",
        "id": id,
        "days": days,
    };
    data = $.param(dataS);
    $.ajax({
        type: "POST",
        dataType: "json",
        url: glfusionSiteUrl + "/classifieds/ajax.php",
        data: data,
        success: function(jsonObj) {
            try {
                document.getElementById("f_max_add_days").value = '';
                document.getElementById("max_add_days").innerHTML = jsonObj.maxdays;
                document.getElementById("exp_date").innerHTML = jsonObj.expdate;
                $.UIkit.notify("<i class='uk-icon-check'></i>&nbsp;" + jsonObj.statusMessage, {timeout: 1000,pos:'top-center'});
            }
            catch(err) {
                alert(jsonObj.statusMessage);
            }
        }
    });
    return false;
};
