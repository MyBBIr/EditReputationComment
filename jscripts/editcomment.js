/*
 * By: AliReza_Tofighi
 * Website: http://my-bb.ir
 */
var editReputation = function(uid, rid)
{
	id = 'vote_comment_' + uid + '_' + rid;

	$('#vote_comment_' + uid + '_' + rid).editable("xmlhttp.php?action=edit_vote&do=update_vote&uid=" + uid + '&rid=' + rid + '&my_post_key=' + my_post_key,
	{
		loadurl: "xmlhttp.php?action=edit_vote&do=get_vote&pid=&uid=" + uid + '&rid=' + rid,
		width: '300',
		event: "edit" + uid + '_' + rid,
		dataType: "json",
		callback: function(values, settings)
		{
			id = $(this).attr('id');
			
			var json = $.parseJSON(values);
			if(typeof json == 'object')
			{
				if(json.hasOwnProperty("errors"))
				{
					$("div.jGrowl").jGrowl("close");

					$.each(json.errors, function(i, message)
					{
						$.jGrowl(message);
					});
					$(this).html($('#' + id + '_temp').html());
				}
				else
				{
					// Change html content
					$(this).html(json.message);
				}
			}
			else
			{
				// Change html content
				$(this).html(json.message);
			}
			$('#' + id + '_temp').remove();
		}
	});

	// Create a copy of the vote
	if($('#' + id + '_temp').length == 0)
	{
		$('#' + id).clone().attr('id',id + '_temp').css('display','none').appendTo("body");
	}

	$('#vote_comment_' + uid + '_' + rid).trigger("edit" + uid + '_' + rid);
};