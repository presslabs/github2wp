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
	
	function git2wp_render_plugin_icon() {
		echo '<div id="icon-plugins" class="icon32">&nbsp;</div>';
	}
	
	function git2wp_render_tab_menu( $tab ) {
		?>
		<h2 class="nav-tab-wrapper">
			<a class="nav-tab<?php if($tab=='resources')
			echo' nav-tab-active';?>" href="<?php echo git2wp_return_settings_link('&tab=resources'); ?>">Resources</a>
			<a class="nav-tab<?php if($tab=='history')
			echo' nav-tab-active';?>" href="<?php echo git2wp_return_settings_link('&tab=history'); ?>">History</a>
			<a class="nav-tab<?php if($tab=='settings')
			echo' nav-tab-active';?>" href="<?php echo git2wp_return_settings_link('&tab=settings'); ?>">Settings</a>
		</h2>
		<?php
	}
	
	function git2wp_render_resource_form() {
		?>
		<form action="options.php" method="post">
		<?php 
				$disable_resource_fields = '';
				if ( git2wp_needs_configuration() )
					$disable_resource_fields = 'disabled="disabled" ';

				settings_fields('git2wp_options');
				do_settings_sections('git2wp');				
		?>
		<table class="form-table">
			<tbody>
				<tr valign="top">
					<th scope="row">
						<label>Resource Type:</label>
					</th>
					<td>
						<label for="resource_type_dropdown">
							<select name='resource_type_dropdown' <?php echo $disable_resource_fields; ?>id='resource_type_dropdown'>
								<option value='plugins'>Plugin</option>
								<option value='themes'>Theme</option>
							</select>
						</label>
						<p class="description">Is it a <strong>plugin</strong> or a <strong>theme</strong>?</p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<label>GitHub clone url:</label>
					</th>
					<td>
						<label for="resource_link">
							<input name="resource_link" id="resource_link" value="" <?php echo $disable_resource_fields; ?>type="text" size='30'>
						</label>
						<p class="description">Github repo link.</p>
					</tr>
				<tr valign="top">
					<th scope="row">
						<label>Synching Branch:</label>
					</th>
					<td>
						<label for="master_branch">
							<input name="master_branch" id="master_branch" value="" <?php echo $disable_resource_fields; ?>type="text" size='30'>
						</label>
						<p class="description">This will override your account preference only for this resource.</p>
						<p class="description">Optional: This will set the branch that will dictate whether or not to synch.</p>
					</td>
				</tr>
				
				<tr valign="top">
					<td>
					</td>
				</tr>
			</tbody>
		</table>
		<input name="submit_resource" <?php echo $disable_resource_fields; ?>type="submit" class="button button-primary" value="<?php esc_attr_e('Add Resource'); ?>" />
		
		<br /><br /><br />
		<?php 
				do_settings_sections('git2wp_list');
				git2wp_setting_resources_list();
		?>
		</form>		
		<?php
	}
	
	function git2wp_render_settings_helper() {
		?>
		<a class="button-primary clicker" alt="#" >Need help?</a>		
		<div class="slider home-border-center" id="#">
				<table class="form-table">
					<tbody>
						<tr valign="top">
							<th scope="row">
								<label>Follow this link and <br />
											 fill as shown here:</label>
							</th>
							<td>
								<label><a href="https://github.com/settings/applications/new" target="_blank">Create a new git application</a></label>
								<p class="description"><strong>Application Name</strong> -> git2wp</p>
								<p class="description"><strong>Main URL </strong>-> <?php echo home_url();?></p>
								<p class="description"><strong>Callback URL</strong> -> <?php echo home_url() . '/?git2wp_auth=true';?></p>
							</td>
						</tr>
						<tr valign="top">
							<th scope="row">
								<label>Go here and select the <br />
											 newly created application</label>
							</th>
							<td>
								<label><a href="https://github.com/settings/applications" target="_blank">Application list</a></label>
								<p class="description"><strong>Here you have all the information that you need to fill in the form.</strong></p>
							</td>
						</tr>
					</tbody>
				</table>
				<br /><br /><br />
		</div>
		<?php
	}
	
	function git2wp_render_settings_app_reset_message( & $default) {
		if($default['app_reset'])
			if(git2wp_needs_configuration())
				echo "<div class='updated'><p>You've reset/deleted you're GitHub application settings reconfigure them here.</p></div>";	
	}
	
	function git2wp_render_settings_form( & $default ) {
		?>
		
		<form action="options.php" method="post">
		
		<?php 
		settings_fields('git2wp_options');
		do_settings_sections('git2wp_settings');
		git2wp_render_settings_helper();
		?>
			
			<table class="form-table">
				<tbody>
					<tr valign="top">
						<th scope="row">
							<label>Github master branch override:</label>
						</th>
						<td>
							<label for="master_branch">
								<input name='master_branch' id='master_branch'  type="text" size='40' value='<?php echo $default["master_branch"]  ? $default["master_branch"] : "master";?>'>
							</label>
							<p class="description">In case you don't  want to synch your master branch, change this setting here.</p>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">
							<label>Github client id:</label>
						</th>
						<td>
							<label for="client_id">
								<input name='client_id' id='client_id'  type="text" size='40' value='<?php echo $default["client_id"]  ? $default["client_id"] : "";?>'>
							</label>
							<p class="description">The git application client id, created for this plugin.</p>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">
							<label>Github client secret:</label>
						</th>
						<td>
							<label for="client_secret">
								<input name='client_secret' id='client_secret'  type="text" size='40' value='<?php echo $default["client_secret"]  ? $default["client_secret"] : "";?>'>
							</label>
							<p class="description">The git application client secret, created for this plugin.</p>
							<p class="description">Notice: These two should be valid because they are used to authentificate us on behalf of yourself. </p>
						</td>
					</tr>
					<?php if($default['changed']): ?>
						<tr valign='top' class='plugin-update-tr'>
							<th scope='row'>
								<label>Generate Token:</label>
							</th>
							<td>
								<a onclick='setTimeout(function(){location.reload(true);}, 60*1000)' target='_blank' style='text-decoration: none; color: red; font-weight: bold;'
									href='https://github.com/login/oauth/authorize?client_id=<?php echo $default['client_id']. "&client_secret=" . $default['client_secret'] . "&scope=repo"; ?>'>
									Generate!
								</a> 
							</td>
						</tr>
					<?php else:
							if($default['access_token']): ?>
						<tr valign='top'>
							<th scope='row'>
								<label>GitHub Link Status: </lablel>
							</th>
							<td>
								<span style='color: green'><strong>OK</strong></span>
							</td>
						</tr>
					<?php
						endif;
					endif; ?>
				</tbody>
			</table>
			
			<input name="submit_settings" type="submit" class="button button-primary" value="<?php esc_attr_e('Save changes'); ?>" />
	</form>
		<?php
	}
	
	function git2wp_render_history_page() {
		?>
		<div id="git2wp_history_messages"></div>
	
		<form action="options.php" method="post">
			<?php
				settings_fields('git2wp_options');
				do_settings_sections('git2wp_history');
				$options = get_option('git2wp_options');
				$resource_list = $options['resource_list'];
			
				$plugin_render = '';
				$theme_render = '';
				if(is_array($resource_list) && !empty($resource_list))
					foreach($resource_list as $key => $resource) {
						$type = git2wp_get_repo_type($resource['resource_link']);
					
						if($type == 'plugin') 
							$plugin_render .= "<tr valign='top'>
														<th scope='row'>
															<label><strong>{$resource['repo_name']}</strong></label>
														</th>
														<td>
															<span class='history-slider clicker button-primary' alt='history-expand-$key'><center>Expand</center></span>
																<div class='slider home-border-center half' id='history-expand-$key' style='padding-top: 5px;'>
																</div>
															</span>
														</td>
													</tr>";
						
						if($type == 'theme') 
							$theme_render .= "<tr valign='top'>
														<th scope='row'>
															<label><strong>{$resource['repo_name']}</strong></label>
														</th>
														<td>
															<span class='history-slider clicker button-primary' alt='history-expand-$key'><center>Expand</center></span>
																<div class='slider home-border-center half' id='history-expand-$key' style='padding-top: 5px;'>
																</div>
															</span>
														</td>
													</tr>";
				}
		?>
		
			<table class="form-table" >
				<tbody>
					<tr><th colspan='2'><h2>Plugins</h2><br /></th></tr> 
					<?php echo $plugin_render; ?>
				</tbody>
			</table>
			<br /><br /><br />
			<table class="form-table">
				<tbody>
					<tr><th colspan='2'><h2>Themes</h2><br /></th></tr> 
					<?php echo $theme_render; ?>
				</tbody>
			</table>
		</form>
		<?php
	}
	
	
	
?>
