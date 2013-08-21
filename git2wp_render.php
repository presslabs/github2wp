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
	

	function git2wp_return_branch_dropdown( $index, $branches ) {
		$options = get_option('git2wp_options');
		
		if($index != 0) {
			$branch_dropdown = "<strong>Branch: </strong><select style='width: 125px;' class='resource_set_branch' resource_id='$index'>";
	
			if(is_array($branches) and count($branches) > 0) {
				foreach($branches as $branch)
					if($options['resource_list'][$index]['repo_branch'] == $branch)
						$branch_dropdown .= "<option value=".$branch." selected>".$branch."</option>";
					else
						$branch_dropdown .= "<option value=".$branch.">".$branch."</option>";
			}
			$branch_dropdown .= "</select>";
			
			return $branch_dropdown;
		}else
			return "<p><strong>Branch: </strong>master</p>";
	}
	
	
	function git2wp_return_resource_url($resource_username, $resource_repo) {
		return "https://github.com/$resource_username/$resource_repo.git";
	}
	
	
	function git2wp_return_resource_git_link($resource) {
		$github_resource_url = git2wp_return_resource_url($resource['username'], $resource['repo_name']);	
		
		return "<strong>Github: </strong><a target='_blank' href='".$github_resource_url."'>".$github_resource_url."</a>";
	}
	
	
	function git2wp_return_wordpress_resource($repo_type, $repo_name) {
		return "<strong>WP:</strong> /wp-content/" . $repo_type . "s/" . $repo_name;
	}
	
	
	function git2wp_return_resource_dismiss($resource, $index) {
		if($index != 0) {
			$github_resource_url = git2wp_return_resource_url($resource['username'], $resource['repo_name']);
		
			return '<p><input name="submit_delete_resource_'.$index
					.'" type="submit" class="button button-red btn-medium" value="'.esc_attr('Dismiss')
					.'" onclick="return confirm(\'Do you really want to disconect from Github: '
					.$github_resource_url . '?\');"/></p>';
		}else
			return '';
	}
	
	
	function git2wp_return_resource_install($resource, $index) {
		return '<p><input name="submit_install_resource_'.$index
						.'" type="submit" class="button button-primary btn-medium" value="'
						.esc_attr('Install') . '" /></p>';
	}
	
	function git2wp_return_resource_update($resource, $index) {
		return  '<p><input name="submit_update_resource_'.$index
							.'" type="submit" class="button btn-medium" value="'
							.esc_attr('Update') . '" /></p>';
	}
	
?>
