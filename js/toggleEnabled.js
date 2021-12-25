/*  Updates submission form fields based on changes in the category
 *  dropdown.
 */
var ADVTtoggleEnabled = function(cbox, id, type) {
    oldval = cbox.checked ? 0 : 1;
    var dataS = {
        "action" : "toggleEnabled",
        "id": id,
        "type": type,
        "oldval": oldval,
    };
    data = $.param(dataS);
    $.ajax({
        type: "POST",
        dataType: "json",
        url: site_admin_url + "/plugins/classifieds/ajax.php",
        data: data,
        success: function(result) {
            cbox.checked = result.newval == 1 ? true : false;
            try {
                $.UIkit.notify("<i class='uk-icon-check'></i>&nbsp;" + result.statusMessage, {timeout: 1000,pos:'top-center'});
            }
            catch(err) {
                alert(result.statusMessage);
            }
        }
    });
    return false;
};

var ADVT_catsub = function(e, cat_id)
{
    var elemid = '#catsub_ico_' + cat_id;
    var dataS = {
        "action" : "catsub",
        "cat_id": cat_id,
        "is_subscribed": $(elemid).data('value'),
    };
    data = $.param(dataS);
    $.ajax({
        type: "POST",
        dataType: "json",
        url: glfusionSiteUrl + "/classifieds/ajax.php",
        data: data,
        success: function(result) {
            try {
                if (result.subscribed) {
                    $(elemid).attr('class', 'uk-icon uk-icon-bookmark uk-text-success');
                    $(elemid).data('value', '1');
                } else {
                    $(elemid).attr('class', 'uk-icon uk-icon-bookmark-o');
                    $(elemid).data('value', '0');
                }
                $(elemid).prop('title', result.title);
                $.UIkit.notify("<i class='uk-icon uk-icon-check'></i>&nbsp;" + result.statusMessage, {timeout: 1000,pos:'top-center'});
            }
            catch(err) {
                console.log(err);
                alert(result.statusMessage);
            }
        },
        error: function (xhr, ajaxOptions, thrownError) {
            console.log(xhr);
            console.log(ajaxOptions);
            console.log(thrownError);
        }
    });
    return false;

};

