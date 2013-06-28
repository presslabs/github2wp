jQuery.noConflict();

jQuery(document).ready(function(){
    jQuery(".slider").hide();

    jQuery(".clicker").click(function(){
    	jQuery(".slider").slideToggle('slow', function() {
    			//on completion
    		});
    });
});
