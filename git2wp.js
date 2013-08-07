var j = jQuery.noConflict();

j(document).ready(function($) {
    //toggler
    j(".slider").hide();

    j(".clicker").click(function(){
    	var alt = j(this).attr("alt");
    	j(".slider[id='" + alt + "']").slideToggle('slow', function() {
    			//on completion
    		});
    });    
		
		
		//branch updater
    j(".resource_set_branch").change(function() {
    	var id = j(this).attr("resource_id");
    	var branch = j(this).val();    	
  	
    	j(this).after('<div class="ajax-loader"></div>');
			
    	j.ajax(ajaxurl,{
    		type: 'post',
			async: true,
  			data: {action: 'git2wp_ajax', 'id': id, 'branch': branch, 'git2wp_action': 'set_branch'},
  			
  			success: function(response){
  							 	if(response['success']) {
									 	var div = j("select.resource_set_branch[resource_id='"
									 							+ id + "'] + div.ajax-loader"
									 	 						).removeClass('ajax-loader').addClass('ajax-success');
									  
									  setTimeout(function() { div.fadeOut(1000, function() { div.remove(); });
									  											}, 2500);
  							  
  							  } else {
  							  	var div = j("select.resource_set_branch[resource_id='"
									 							+ id + "'] + div.ajax-loader"
									 	 						).removeClass('ajax-loader').addClass('ajax-fail');
									 	setTimeout(function() { div.fadeOut(1000, function() { div.remove(); });
									  											}, 2500); 		
									}				
  							 },
  			
  			error: function(response) {
 												alert ( " Can't do because: " + response['error_messages'] );
											},
  			dataType: 'json'
			});
		})
		
		//downgrader
		
		j(".downgrade").click( function(e) {
			e.preventDefault();
			var array = j(this).attr('id').split('-');
			
			var res_id = array[2];
			var sha = array[3];
			var self = j(this);
			
			j(this).attr('disabled', 'disabled');
			
			j.ajax(ajaxurl,{
				type: 'post',
				async: true,
				data: {action: 'git2wp_ajax', 'res_id': res_id, 'sha': sha, 'git2wp_action': 'downgrade'},
				
				success: function(response){
									self.removeAttr("disabled");
									alert(response['success']);
								},
				
				error: function(response) {
													alert ( " Can't do because: " + response['error_messages'] );
												},
				dataType: 'json'
			});					
		});
		
		
});
