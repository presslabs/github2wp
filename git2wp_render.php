<?php
	function git2wp_render_resource_history( $resource , $resource_id, $commit_history) {
		if(count($commit_history) != 0):
		?>
		
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
					
					foreach($commit_history as $commit) {
						$k++;
						
						$date_time = new DateTime($commit['timestamp']);
						$date_time =  $date_time->format("d.m.y H:i:s");
						?>
						
								<tr class="<?php echo ($k % 2) ? 's-inactive' : '';?>">
									<td width="10%;"><a href="<?php echo $commit['git_url']; ?>" target='_blank'><?php echo substr($commit['sha'], 0, 7); ?></a></td>
									<td width="70%;"><?php echo ucfirst($commit['message']); ?></td>
									<td width="10%;"><?php echo $date_time; ?></td>
									<td width="10%;"><input type='submit' value='Revert' class="downgrade button-secondary" id="downgrade-resource-<?php echo $resource_id."-".$commit['sha']; ?>" /></td>
								</tr>

						<?php } ?>
							</tbody>
						</table>						
				<?php else: ?>
					<div class='half centered'>Nope no history yet :D</div>
				<?php endif; ?>
	<?php
	}
	
?>
