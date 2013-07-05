var j = jQuery.noConflict();

j(document).ready(function() {
    j(".slider").hide();

    j(".clicker").click(function(){
    	var target = j(this).attr("href");
    	j(".slider[id='" + target + "']").slideToggle('slow', function() {
    			//on completion
    		});
    });
    
 		j(".resource_fetch_branch").mousedown(function() {
    	var id = j(this).attr("resource_id");
    	

    	j.ajax({
    		type:'GET',
  			url: "/wp-content/plugins/git2wp/ajax-handler.php",
  			data: {'id': id, 'git2wp_action': 'fetch_branches'},
  			success: function(result){
  								var obj = j("select.resource_fetch_branch[resource_id='" + id + "']");
  								obj.html(result);
  							 },
  			error: function(request, error) {
 												alert ( " Can't do because: " + error );
											},
  			dataType: 'html'
			});
		})
});
