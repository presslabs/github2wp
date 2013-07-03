jQuery.noConflict();

jQuery(document).ready(function(){
    jQuery(".slider").hide();

    jQuery(".clicker").click(function(){
    	var target = jQuery(this).attr("href");
    	jQuery(".slider[id='" + target + "']").slideToggle('slow', function() {
    			//on completion
    		});
    });
});
