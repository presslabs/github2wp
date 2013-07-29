<?php

	function git2wp_render_resource_history( $key ) {
		$html = '';
		$html .= "<div height='100px'>";
		
		$options = get_option('git2wp_options');
		$commit_history = $options['resource_list'][$key]['git_data']['commit_history'];
		$commit_history = array_reverse($commit_history);
		
		foreach($commit_history as $key => $commit) {
			
			$html .= "<label for='downgrade_resource_".$key."'>";
			$html .= "<div>";
			
			$html .= "<a href='".$commit['git_url']."' target='_blank'>".substr($commit['sha'],0,7)."</a>";
			$html .= "<span style='padding-left: 5px;'>".ucfirst($commit['message'])."</span>";
			$html .= "<span style='padding-left: 5px;'>".date("d-m-y", $commit['timestamp'])."</span>";
			
			$html .= "</div>";
			$html .= "</label>";
		}
		
		$html .= "</div>";
	}
?>
