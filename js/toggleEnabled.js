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
	var span_elemid = '#catsub_span_' + cat_id;
	var icon_elemid = '#catsub_icon_' + cat_id;
	var dataS = {
		"action" : "catsub",
		"cat_id": cat_id,
		"is_subscribed": $(icon_elemid).data('value'),
	};
	data = $.param(dataS);
	$.ajax({
		type: "POST",
		dataType: "json",
		url: glfusionSiteUrl + "/classifieds/ajax.php",
		data: data,
		success: function(result) {
			try {
				$(span_elemid).html(result.innerHtml);
				$(icon_elemid).prop('title', result.title);
				if (typeof UIkit.notify === 'function') {
					// uikit v2 theme
					$.UIkit.notify("<i class='uk-icon uk-icon-check'></i>&nbsp;" + result.statusMessage, {timeout: 1000,pos:'top-center'});
				} else if (typeof UIkit.notification === 'function') {
					// uikit v3 theme
					UIkit.notification({
						message: result.statusMessage,
						timeout: 1000
					});
				} else {
					alert(result.statusMessage);
				}
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

