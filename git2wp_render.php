<?php


	function git2wp_render_resource_history( $resource , $resource_id ) {
		ob_start();
		?>
		
		<tr valign="top">
			<th scope="row">
				<label><strong><?php echo $resource['repo_name']; ?></strong></label>
			</th>
			<td>
				<span class="history-slider clicker button-primary" alt="<?php echo "history_expand_$resource_id"; ?>" ><center>Expand</center></span>		
				<div class="slider home-border-center quarter" id="<?php echo "history_expand_$resource_id"; ?>">
					<?php
					$commit_history = $resource['git_data']['commit_history'];
					$commit_history = array_reverse($commit_history);
					
					foreach($commit_history as $key => $commit) {
						$str_date = '';
						$date = date_parse($commit['timestamp']);
						$date['day'] = str_pad($date['day'], 2, '0' , STR_PAD_LEFT);
						$date['month'] = str_pad($date['month'], 2, '0', STR_PAD_LEFT);
						$date['hour'] = str_pad($date['hour'], 2, '0', STR_PAD_LEFT);
						$date['minute'] = str_pad($date['minute'], 2, '0', STR_PAD_LEFT);
						
						$str_date .= $date['day'].".".$date['month'].".".$date['year']."   ".$date['hour'].":".$date['minute'];
						?>
						<div>
							<label for="downgrade_resource_<?php echo $resource_id."_".$key; ?>">
								<a href="<?php echo $commit['git_url']; ?>" target='_blank'><?php echo substr($commit['sha'], 0, 7); ?></a>
								<span style='padding-left: 5px;'><?php echo ucfirst($commit['message']); ?></span>
								<span style='padding-left: 5px;'><?php echo $str_date; ?></span>
							</label>
							<input type="checkbox" name="downgrade_resource_<?php echo $resource_id."_".$key; ?>" >
						</div>
						
					<?php
					}
					?>
				</div>
			</td>
		</tr>
	<?php
		$data = ob_get_contents();
		ob_end_clean();
		
		return $data;
	}
	
?>
