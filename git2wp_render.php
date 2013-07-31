<?php


	function git2wp_render_resource_history( $resource ) { ?>
		<div height='100px'>
		
		<?php
		$commit_history = $resource['git_data']['commit_history'];
		$commit_history = array_reverse($commit_history);
		
		foreach($commit_history as $key => $commit) { ?>
			<label for="downgrade_resource_<?php echo $key; ?>">
				<div>
					<a href="<?php echo $commit['git_url']; ?>" target='_blank'><?php echo substr($commit['sha'], 0, 7); ?></a>
					<span style='padding-left: 5px;'><?php echo ucfirst($commit['message']); ?></span>
					<span style='padding-left: 5px;'><?php echo date("d-m-y", $commit['timestamp']); ?></span>
				</div>
			</label>
		<?php
		}
		?>
		</div>
	<?php	
	}
	
?>
