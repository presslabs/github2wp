<?php


	function git2wp_render_resource_history( $resource , $resource_id ) {
		ob_start();
		?>
		
		<tr valign="top">
			<th scope="row">
				<label><strong><?php echo $resource['repo_name']; ?></strong></label>
			</th>
			<td>
				<?php
				$commit_history = $resource['git_data']['commit_history'];

				if(count($commit_history) != 0):
					$commit_history = array_reverse($commit_history, true);
				?>
			
				<span class="history-slider clicker button-primary" alt="<?php echo "history_expand_$resource_id"; ?>" ><center>Expand</center></span>		
					<div class="slider home-border-center half" id="<?php echo "history_expand_$resource_id"; ?>" style='padding-top: 5px;'>
						<table class='wp-list-table widefat plugins' >
							<thead>
								<tr>
									<th scope='col' width="10%;"><b>SHA</b></th>
									<th scope='col' width="70%;"><b>Message</b></th>
									<th scope='col' width="10%;"><b>Date</b></th>
									<th scope='col' width="10%;"><b>Select</b></th>
								</tr>
							</thead>
							<tbody>
					<?php
					
					foreach($commit_history as $key => $commit) {
						$k++;
						$str_date = '';
						$date = date_parse($commit['timestamp']);
						$date['day'] = str_pad($date['day'], 2, '0' , STR_PAD_LEFT);
						$date['month'] = str_pad($date['month'], 2, '0', STR_PAD_LEFT);
						$date['hour'] = str_pad($date['hour'], 2, '0', STR_PAD_LEFT);
						$date['minute'] = str_pad($date['minute'], 2, '0', STR_PAD_LEFT);
						
						$str_date .= $date['day'].".".$date['month'].".".$date['year']."   ".$date['hour'].":".$date['minute'];
						?>
						
								<tr class="<?php echo ($k % 2) ? 's-inactive' : '';?>">
									<td width="10%;"><a href="<?php echo $commit['git_url']; ?>" target='_blank'><?php echo substr($commit['sha'], 0, 7); ?></a></td>
									<td width="70%;"><?php echo ucfirst($commit['message']); ?></td>
									<td width="10%;"><?php echo $str_date; ?></td>
									<td width="10%;"><input type='submit' value='Revert' class="downgrade button-secondary" id="downgrade-resource-<?php echo $resource_id."-".$key; ?>" /></td>
								</tr>

						<?php } ?>
							</tbody>
						</table>						
				<?php else: ?>
					<div class='half centered'>Nope no history yet :D</div>
				<?php endif; ?>
					</div>
			</td>
		</tr>
	<?php
		$data = ob_get_contents();
		ob_end_clean();
		
		return $data;
	}
	
?>
