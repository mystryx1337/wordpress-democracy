jQuery(document).ready(function($) {
	/* -- democracy_reject_post -- */
	$('#democracy_reject_post_form_send').on('click', function() {
		//open textarea
		$('#democracy_reject_post_form').addClass('open');
		
		//send textarea to plugin via ajax
		if($('#democracy_reject_post_form_textarea').val() != ''){
			var data = {
				'action': 'democracy_reject_post',
				'reason': $('#democracy_reject_post_form_textarea').val(),
				'post_id': $('#democracy_reject_post_form_id').val()
			}
			$('#democracy_reject_post_form_send').addClass('button-primary-disabled');
			$.post(ajaxurl, data, function(response) {
				$('#democracy_reject_post_form').removeClass('open');
				$('#democracy_reject_post_form_textarea').val('');
				//$('#democracy_reject_post_form_send').removeClass('button-primary-disabled');
				$('#democracy_reject_post_form_response').text(response);
			});
		}
		
		//do not send anything via form
		return false;
	});


	/* -- democracy_invite_user -- */
	$('#democracy_invite_user_form').submit( ajax_send );
	
	
	/* -- democracy_reject_user -- */
		$('#democracy_reject_user_form_send').on('click', function() {
			//open textarea
			$('#democracy_invite_user_form').addClass('open');
			
			if($('#democracy_reject_user_form_textarea').val() == ''){
				//do not send anything via form
				return false;
			}
		});


	/* -- options admin -- */
	$('#democracy_options_admin_form').submit( ajax_send );
	$('#democracy_options_admin_form .select_ratio').change( toggle_percentage_box );
	$('#democracy_options_admin_form .select_ratio').each( toggle_percentage_box );

	function toggle_percentage_box(){
		if( $(this).val().substr(-1) == '_' ){
			$(this).next().removeClass('hidden');
		}
		else {
			$(this).next().addClass('hidden');
		}
	}
	
	
	/* -- options user -- */
	$('#democracy_options_user_form').submit( ajax_send );


	/* -- role application -- */
	$('.democracy_application_form').submit( ajax_send_application );
	if ( $( ".democracy_application_form" ).length ) {
		$.ajax({
			'url'    : ajaxurl,
			'type'   : 'POST',
			'data'   : {
				action: 'democracy_check_role_application',
				application_id: $('#democracy_application_id').val()
			},
			'success': function(response){ ajax_send_application_success(response); },
			'fail'   : function(err){ console.log(err); }
		});
	}
	function ajax_send_application(){
		var form_data = $(this).serialize();
		$(this).find(".button").each(function() {
			$(this).addClass('button-primary-disabled');
		});

		$.ajax({
			'url'    : ajaxurl,
			'type'   : 'POST',
			'data'   : form_data,
			'success': function(response){ ajax_send_application_success(response); },
			'fail'   : function(err){ console.log(err); }
		});
		return false;
	};
	
	function ajax_send_application_success(response){
		response = response.split(";--;");
		console.log(response);
		if(response[0] == 'yes'){
			$('.democracy_application_form .button').val( $("#democracy_text_cancel_app").val() );
			$('#democracy_applications_count').html(response[1]);
		}
		else{
			$('.democracy_application_form .button').val( $("#democracy_text_applicate").val() );
		}
		$("form .button").each(function() {
			$(this).removeClass('button-primary-disabled');
		});	
	}
	
	
	/* users_search */
	$('#democracy_exclude_choose_all').on('input', check_all);
	function check_all(){
		var val = $(this).prop('checked');
		$('#democracy_exclude_users_list input[type=checkbox]').each(function () {
			$(this).prop('checked', val);
		});
		var val = $(this).prop('checked');
		$('#democracy_users_list input[type=checkbox]').each(function () {
			$(this).prop('checked', val);
		});
	}
	
	if ( $('#democracy_users_search').length ) {
		$('#democracy_users_search').on('input', users_search);
		users_search();
	}
	
	function users_search(){
		var data = {
			'action': 'democracy_users_search',
			'search': $('#democracy_users_search').val()
		}
		
		$.post(ajaxurl, data, function(response) {
			$('#democracy_users_list').empty();
			var users = response.split(';;');
			var html_list = [];
			
			var text_reason = $('#democracy_exclude_users_text_reason').html();
			var text_exclude = $('#democracy_exclude_users_text_exclude').html();
			var text_include = $('#democracy_exclude_users_text_include').html();
			var text_without_role = $('#democracy_users_text_without_role').html();
			
			users.forEach(function(vals, key){
				if(vals != ''){
					var val = vals.split('::');
					var html_btn = "<input type='submit' name='exclude" + val[0] + "' value='&#128465;' title='" + text_exclude + "' />"
								+ "<input type='submit' name='include" + val[0] + "' value='X' title='" + text_include + "' />";
					
					var roles = val[4].split('??');
					roles.forEach(function(val_roles, key_roles){
						if(typeof html_list[val_roles] == 'undefined'){
							html_list[val_roles] = [];
						}
						var content = "<li>"
								+ "<input type='hidden' name='user" + val[0] + "' value='0' />"
								+ (val[2]=='1' ? '<span>&#10004</span>' : "<input type='checkbox' name='user" + val[0] + "' />")
								+ "<span>" + val[1] + "</span>"
								+ "<span>" + (val[3]=='' ? "<input type='text' name='reason" + val[0] + "' placeholder='" + text_reason + "' />" : val[3]) + "</span>"
								+ "<span>" + (val[2]=='1' ? html_btn : '') + "</span>"
							+ "</li>";
						html_list[val_roles].push(content);
					});
				}
			});
			
			/* Ausgabe */
			var keys = [];
			for (var k in html_list) {
				keys.unshift(k);
			}
			
			var div = document.createElement("div");
				$(div).addClass('clear');
			$('#democracy_users_list').append(div);
			for (var n = 0; n < keys.length; n++) {
				var key = keys[n];
				if(key == ''){h2 = text_without_role;}
				else{h2 = key}
				
				var ul1 = document.createElement("ul");
					$(ul1).addClass('table');
					$(ul1).addClass('left');
				$('#democracy_users_list').append(ul1);
				
				var ul2 = document.createElement("ul");
					$(ul2).addClass('table');
					$(ul2).addClass('right');
				$('#democracy_users_list').append(ul2);
				
				var div = document.createElement("div");
					$(div).addClass('clear');
				$('#democracy_users_list').append(div);
				
				$(ul1).append("<li><span></span><h2>" + h2 + "</h2></li>");
				for(var i = 0; i < Math.ceil(html_list[key].length/2); i++){
					$(ul1).append(html_list[key][i]);
				}
				
				$(ul2).append("<li class='collapse'><span></span><h2>&nbsp;</h2></li>");
				for(var i = Math.ceil(html_list[key].length/2); i < html_list[key].length; i++){
					$(ul2).append(html_list[key][i]);
				}
				
			}
		});
	}
	
	
	/* -- ajax send function -- */
	function ajax_send(){
		var form_data = $(this).serialize();
		$(this).find(".button").each(function() {
			$(this).addClass('button-primary-disabled');
		});

		$.ajax({
			'url'    : ajaxurl,
			'type'   : 'POST',
			'data'   : form_data,
			'success': function(response){
				console.log(response);
				
				response = response.split(";--;");
				$("#ajax_feedback").html(response[0]);
				
				for(i=1; i < response.length; i++){
					$("#ajax_feedback_" + i).html(response[i]);
				}
				
				if(response[0].substr(0,19) != '<span>&nbsp;</span>'){
					$("form .button").each(function() {
						if(!$(this).hasClass('keep_disabled')){
							$(this).removeClass('button-primary-disabled');
						}
					});
				}
			},
			'fail'   : function(err){
				console.log(err);
			}
		});
		return false;
	};
}); 