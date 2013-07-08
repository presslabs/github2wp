var j = jQuery.noConflict();

j(document).ready(function() {
    j(".slider").hide();

    j(".clicker").click(function(){
    	var target = j(this).attr("href");
    	j(".slider[id='" + target + "']").slideToggle('slow', function() {
    			//on completion
    		});
    });
    
    
    j(".resource_set_branch").change(function() {
    	var id = j(this).attr("resource_id");
    	var branch = j(this).val();
    	
    	j(this).after('<div class="ajax-loader"></div>');
			
    	j.ajax({
    		type:'post',
  			url: "/wp-content/plugins/git2wp/ajax-handler.php",
  			data: {'id': id, 'branch': branch, 'git2wp_action': 'set_branch'},
  			
  			success: function(result){
  							 	var div = j("select.resource_set_branch[resource_id='"
  							 							+ id + "'] + div.ajax-loader"
  							 	 						).removeClass('ajax-loader').addClass('ajax-success');
  							  
  							  setTimeout(function() { div.fadeOut(1000, function() { div.remove(); });
  							  											}, 3000);
  							 },
  			
  			error: function(request, error) {
 												alert ( " Can't do because: " + error );
											},
  			dataType: 'html'
			});
		})
		
});
