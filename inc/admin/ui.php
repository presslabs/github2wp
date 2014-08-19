<?php



function github2wp_options_page() {
	if ( ! current_user_can('manage_options') )
		wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
	
	$nav_bar_tabs = array(
		'resources',
		'settings',
		'history',
		'faq'
	);

	isset( $_GET['tab'] ) ? $tab = $_GET['tab'] : $tab = 'resources';

	if ( ! in_array( $tab, $nav_bar_tabs ) )
		$tab = 'resources';

	echo '<div class="wrap">';
	github2wp_render_plugin_icon();
	github2wp_render_tab_menu( $tab );

	if ( 'resources' == $tab ) {
		github2wp_head_commit_cron();

		wp_clean_plugins_cache(true);
		wp_clean_themes_cache(true);

		$options = get_option( 'github2wp_options' );
		github2wp_render_resource_form();
	}

	if ( 'settings' == $tab ) {
		github2wp_token_cron();

		$options = get_option( 'github2wp_options' );
		$default = &$options['default'];

		github2wp_render_settings_app_reset_message( $default );
		github2wp_render_settings_form( $default );
	}

	if ( 'history' == $tab )
		github2wp_render_history_page();

	if ( 'faq' == $tab )
		github2wp_render_faq_page();

	echo '</div><!-- .wrap -->';
}



function github2wp_setting_resources_list() {
	$options = get_option( 'github2wp_options' );
	$resource_list = $options['resource_list'];

	if( !is_array( $resource_list ) || empty( $resource_list ) )
		return;

	$new_transient = array();
	$transient = get_transient( 'github2wp_branches' );
	$default = $options['default'];

	foreach ( $resource_list as $index => $resource ) {
		$k++;

		$my_data = '';
		$action = github2wp_return_resource_dismiss( $resource, $k-1 );

		$branches = github2wp_get_res_branches( $resource, $transient, $options );
		$new_transient[] = array(
			'repo_name' => $resource['repo_name'],
			'branches'  => $branches
		);


		$alternate = '';
		if ( 0 == ($k % 2) )
			$alternate = 'class="inactive"';

		$repo_type = github2wp_get_repo_type( $resource['resource_link'] );
		$resource_path = github2wp_get_content_dir_by_type($repo_type);
		$resource_path .= basename($resource['resource_link']);

		if ( ! is_dir( $resource_path ) ) {
			$my_data .= '<p><strong>' . __( 'The resource does not exist on your site!', GITHUB2WP ) . '</strong></p>';
			$action .= github2wp_return_resource_install( $resource, $k-1 );
		}

		$resource_file = $resource['repo_name'] . (('plugin' == $repo_type) ? '/'.$resource['repo_name'].'.php' : '');
		$resource_current_version = github2wp_get_header($resource_file, 'Version');

		if ( $resource_current_version )
			$my_data .= github2wp_get_resource_render_details( $k, $resource_file, $resource['is_on_wp_svn'] );


		$new_version = substr( $resource['head_commit'], 0, 7 );
	
		if ( $new_version && $new_version != $resource_current_version ) {
			$my_data .= '<strong>' . __( 'New Version: ', GITHUB2WP ) . "</strong>$new_version<br /></div>";
			$action .= github2wp_return_resource_update( $resource, $k-1 );
		}
		
		$body .= "<tr $alternate>"
			. "<td>$k</td>"
			. "<td>$my_data<br />"
			. github2wp_return_resource_git_link( $resource )
			. "<br />"
			. github2wp_return_wordpress_resource( $repo_type, $resource['repo_name'] )
			. "<br />"
			. github2wp_return_branch_dropdown( $index, $branches )
			. "</td>"
			. "<td>$action</td>"
			. "</tr>";				
	}

	if ( false === $transient)
		set_transient( 'github2wp_branches', $new_transient, 5*60 );


	$table_head  = '<thead><tr>';
	$table_head .= '<th></th>';
	$table_head .= '<th>'. __( 'Resource', GITHUB2WP ) .'</th>';
	$table_head .= '<th>'. __( 'Options', GITHUB2WP ) .'</th>';
	$table_head .= '</tr></thead>';

	$table_body = "<tbody>$body</tbody>";

	$table = "<br />
		<table id='the-list' class='wp-list-table widefat plugins' cellpadding='5' border='1' cellspacing='0'>
		$table_head
		$table_body	
		</table>";

	echo $table;
}



function github2wp_render_resource_history( $resource , $resource_id, $commit_history ) {
	if ( !count( $commit_history ) )
		echo '<div class="half centered">'. __( 'Nope no history yet :D', GITHUB2WP ) . '</div>';
	?>
	
	<table class='wp-list-table widefat plugins' >
	<thead>
		<tr>
			<th scope='col' width='10%;'><b>SHA</b></th>
			<th scope='col' width='70%;'><b><?php _e( 'Message', GITHUB2WP ); ?></b></th>
			<th scope='col' width='10%;'><b><?php _e( 'Date', GITHUB2WP ); ?></b></th>
			<th scope='col' width='10%;'><b><?php _e( 'Select', GITHUB2WP ); ?></b></th>
		</tr>
	</thead>
	<tbody>


	<?php
	foreach ( $commit_history as $commit ) {
		$k++;
		$date_time = new DateTime( $commit['timestamp'] );
		$date_time =  $date_time->format( 'd.m.y H:i:s' );

		?>

		<tr class='<?php echo ( $k % 2 ) ? 's-inactive' : ''; ?>'>
			<td width='10%;'><a href='<?php echo $commit['git_url']; ?>' target='_blank'><?php echo substr( $commit['sha'], 0, 7 ); ?></a></td>
			<td width='70%;'><?php echo ucfirst( nl2br( $commit['message'] ) ); ?></td>
			<td width='10%;'><?php echo $date_time; ?></td>
			<td width='10%;'><input type='submit' value='<?php _e( 'Revert', GITHUB2WP ); ?>'
				class='downgrade button-secondary' id='downgrade-resource-<?php echo $resource_id . '-' . $commit['sha']; ?>' /></td>
		</tr>
	<?php } ?>

	</tbody>
	</table>						
<?php
}



function github2wp_return_branch_dropdown( $index, $branches ) {
	$options = get_option('github2wp_options');

	$branch_dropdown = '<strong>' . __( 'Branch:', GITHUB2WP )
		. "</strong><select style='width: 125px;' class='resource_set_branch' resource_id='$index'>";

	if( is_array( $branches ) && count( $branches ) > 0 ) {
		foreach ( $branches as $branch ) {
			if ( $options['resource_list'][ $index ]['repo_branch'] == $branch )
				$branch_dropdown .= "<option value='$branch' selected='selected' >$branch</option>";
			else
				$branch_dropdown .= "<option value='$branch' >$branch</option>";
		}
	}
	$branch_dropdown .= '</select>';

	return $branch_dropdown;
}



function github2wp_return_resource_url( $resource_username, $resource_repo ) {
	return "https://github.com/$resource_username/$resource_repo.git";
}



function github2wp_return_resource_git_link( $resource ) {
	$github_resource_url = github2wp_return_resource_url( $resource['username'], $resource['repo_name'] );	

	return '<strong>' . __( 'Github:', GITHUB2WP )
		. "</strong><a target='_blank' href='$github_resource_url' >$github_resource_url</a>";
}



function github2wp_return_wordpress_resource( $repo_type, $repo_name ) {
	return "<strong>WP:</strong> /wp-content/{$repo_type}s/$repo_name";
}



function github2wp_return_resource_dismiss( $resource, $index ) {
	$github_resource_url = github2wp_return_resource_url( $resource['username'], $resource['repo_name'] );

	return "<p><input name='submit_delete_resource_$index' type='submit'
		class='button button-red btn-medium' value='" . __( 'Dismiss', GITHUB2WP )
			. "' onclick='return confirm("
			. __( 'Do you really want to disconect from Github:', GITHUB2WP ) . "$github_resource_url?" . ");'/></p>";
}



function github2wp_return_resource_install( $resource, $index ) {
	return "<p><input name='submit_install_resource_$index' type='submit'
		class='button button-primary btn-medium' value='"
		. __( 'Install' ) . "' /></p>";
}



function github2wp_return_resource_update( $resource, $index ) {
	return  "<p><input name='submit_update_resource_$index' type='submit'
		class='button btn-medium' value='"
		. __( 'Update' ) . "'/></p>";
}



function github2wp_render_plugin_icon() {
	echo '<div id="icon-plugins" class="icon32">&nbsp;</div>';
}



function github2wp_render_tab_menu( $tab ) {
?>
	<h2 class='nav-tab-wrapper'>
		<a class='nav-tab<?php ( 'resources' == $tab ) ? ' nav-tab-active' : ''; ?>'
			href='<?php echo github2wp_return_settings_link( '&tab=resources' ); ?>'><?php _e( 'Resources', GITHUB2WP ); ?></a>
		<a class='nav-tab<?php ( 'history' == $tab ) ? ' nav-tab-active' : ''; ?>'
			href='<?php echo github2wp_return_settings_link( '&tab=history' ); ?>'><?php _e( 'History', GITHUB2WP ); ?></a>
		<a class='nav-tab<?php ( 'settings' == $tab ) ? ' nav-tab-active' : ''; ?>'
			href='<?php echo github2wp_return_settings_link( '&tab=settings' ); ?>'><?php _e( 'Settings', GITHUB2WP ); ?></a>
		<a class='nav-tab<?php ( 'FAQ' == $tab ) ? ' nav-tab-active' : ''; ?>'
			href='<?php echo github2wp_return_settings_link( '&tab=faq' ); ?>'><?php _e( 'FAQ', GITHUB2WP ); ?></a>
	</h2>
<?php
}



function github2wp_render_resource_form() {
?>
	<form action='options.php' method='post'>
		<?php 
			$disable_resource_fields = '';
			if ( github2wp_needs_configuration() )
				$disable_resource_fields = 'disabled="disabled"';

			settings_fields( 'github2wp_options' );
			do_settings_sections( 'github2wp' );			
		?>
		<table class='form-table'>
			<tbody>
				<tr valign='top'>
					<th scope='row'>
						<label><?php _e( 'Resource Type:', GITHUB2WP ); ?></label>
					</th>
					<td>
						<label for='resource_type_dropdown'>
							<select name='resource_type_dropdown' <?php echo $disable_resource_fields; ?> id='resource_type_dropdown'>
								<option value='plugins'><?php _e( 'Plugin', GITHUB2WP ); ?></option>
								<option value='themes'><?php _e( 'Theme', GITHUB2WP ); ?></option>
							</select>
						</label>
						<p class='description'><?php _e( 'Is it a <strong>plugin</strong> or a <strong>theme</strong>?</p>', GITHUB2WP ); ?>
					</td>
				</tr>
				<tr valign='top'>
					<th scope='row'>
						<label><?php _e( 'GitHub clone url:', GITHUB2WP ); ?></label>
					</th>
					<td>
						<label for='resource_link'>
							<input name='resource_link' id='resource_link' value='' <?php echo $disable_resource_fields; ?> type='text' size='30'>
						</label>
						<p class='description'><?php _e( 'Github repo link.', GITHUB2WP ); ?></p>
					</tr>
				<tr valign='top'>
					<th scope='row'>
						<label><?php _e( 'Synching Branch:', GITHUB2WP ); ?></label>
					</th>
					<td>
						<label for='master_branch'>
							<input name='master_branch' id='master_branch' value='' <?php echo $disable_resource_fields; ?> type='text' size='30'>
						</label>
						<p class='description'><?php _e( 'This will override your account preference only for this resource.', GITHUB2WP ); ?></p>
						<p class='description'><?php _e( 'Optional: This will set the branch that will dictate whether or not to synch.', GITHUB2WP ); ?></p>
					</td>
				</tr>
				<tr valign='top'>
					<td>
					</td>
				</tr>
			</tbody>
		</table>
		<input name='submit_resource' <?php echo $disable_resource_fields; ?> type='submit' class='button button-primary'
			value='<?php _e( 'Add Resource', GITHUB2WP ); ?>' />
		<br /><br /><br />
		<?php
			do_settings_sections( 'github2wp_list' );
			github2wp_setting_resources_list();
		?>
	</form>
<?php
}



function github2wp_render_settings_helper() {
?>
	<a class='button-primary clicker' alt='#' ><?php _e( 'Need help?', GITHUB2WP ); ?></a>		
	<div class='slider home-border-center' id='#'>
		<table class="form-table">
			<tbody>
				<tr valign='top'>
					<th scope='row'>
						<label><?php _e( 'Follow this link and <br />fill as shown here:', GITHUB2WP ); ?></label>
					</th>
					<td>
						<label>
							<a href='https://github.com/settings/applications/new' target='_blank'><?php _e( 'Create a new git application', GITHUB2WP ); ?></a>
						</label>
						<p class='description'><strong><?php _e( 'Application Name', GITHUB2WP ); ?></strong> -> github2wp</p>
						<p class='description'><strong><?php _e( 'Main URL', GITHUB2WP ); ?> </strong>-> <?php echo home_url();?></p>
						<p class='description'><strong><?php _e( 'Callback URL', GITHUB2WP ); ?></strong> -> <?php echo home_url() . '/?github2wp_auth=true'; ?></p>
					</td>
				</tr>
				<tr valign='top'>
					<th scope='row'>
						<label><?php _e( 'Go here and select the <br />newly created application', GITHUB2WP ); ?></label>
					</th>
					<td>
						<label><a href='https://github.com/settings/applications' target='_blank'><?php _e( 'Application list', GITHUB2WP ); ?></a></label>
						<p class='description'><strong><?php _e( 'Here you have all the information that you need to fill in the form.', GITHUB2WP ); ?></strong></p>
					</td>
				</tr>
			</tbody>
		</table>
		<br /><br /><br />
	</div>
<?php
}



function github2wp_render_settings_form( & $default ) {
?>

	<form action='options.php' method='post'>
		<?php 
			settings_fields( 'github2wp_options' );
			do_settings_sections( 'github2wp_settings' );
			github2wp_render_settings_helper();
		?>
		<table class='form-table'>
			<tbody>
				<tr valign='top'>
					<th scope='row'>
						<label><?php _e( 'Github master branch override:', GITHUB2WP ); ?></label>
					</th>
					<td>
						<label for='master_branch'>
							<input name='master_branch' id='master_branch'  type='text' size='40'
								value='<?php echo $default['master_branch'] ? $default['master_branch'] : 'master'; ?>'>
						</label>
						<p class='description'><?php _e( "In case you don't  want to synch your master branch, change this setting here.", GITHUB2WP ); ?></p>
					</td>
				</tr>
				<tr valign='top'>
					<th scope='row'>
						<label><?php _e( 'Github client id:', GITHUB2WP ); ?></label>
					</th>
					<td>
						<label for='client_id'>
							<input name='client_id' id='client_id'  type='text' size='40' value='<?php echo $default['client_id'] ? $default['client_id'] : ''; ?>'>
						</label>
						<p class='description'><?php _e( 'The git application client id, created for this plugin.', GITHUB2WP ); ?></p>
					</td>
				</tr>
				<tr valign='top'>
					<th scope='row'>
						<label><?php _e( 'Github client secret:', GITHUB2WP ); ?></label>
					</th>
					<td>
						<label for='client_secret'>
							<input name='client_secret' id='client_secret'  type='text' size='40'
								value='<?php echo $default['client_secret'] ? $default['client_secret'] : ''; ?>'>
						</label>
						<p class='description'><?php _e( 'The git application client secret, created for this plugin.', GITHUB2WP ); ?></p>
						<p class='description'><?php _e( 'Notice: These two should be valid because they are used to authentificate us on behalf of yourself.', GITHUB2WP ); ?> </p>
					</td>
				</tr>
				<?php if ( $default['changed'] ): ?>
					<tr valign='top' class='plugin-update-tr'>
						<th scope='row'>
							<label><?php _e( 'Generate Token:', GITHUB2WP );?></label>
						</th>
						<td>
							<a onclick='setTimeout(function(){location.reload(true);}, 60*1000)' target='_blank' style='text-decoration: none; color: red; font-weight: bold;'
								href='https://github.com/login/oauth/authorize?client_id=<?php echo $default['client_id']. '&client_secret=' . $default['client_secret'] . '&scope=repo'; ?>'>
								<?php _e( 'Generate!', GITHUB2WP ); ?>
							</a>
						</td>
					</tr>
				<?php elseif ( $default['access_token'] ): ?>
					<tr valign='top'>
						<th scope='row'>
							<label><?php _e( 'GitHub Link Status:', GITHUB2WP ); ?> </label>
						</th>
						<td>
							<span style='color: green'><strong><?php _e( 'OK', GITHUB2WP ); ?></strong></span>
						</td>
					</tr>
				<?php endif ?>
			</tbody>
		</table>
		<input name='submit_settings' type='submit' class='button button-primary' value='<?php _e( 'Save changes', GITHUB2WP ); ?>' />
	</form>
<?php
}



function github2wp_render_faq_page() {
	do_settings_sections( 'github2wp_faq' );

	$faq_data = array(
		'Test question 0' => "And the answer is here 0",
		'Test question 1' => "And the answer is here 1\n",
		'Test question 2' => "And the answer is here 2",
		'Test question 3' => "And the answer is here 3",
		'Test question 4' => "And the answer is here 4",
		'Test question 5' => "And the answer is here 5"
	);	
	$k = 0;

	echo '<dl>';
	foreach ( $faq_data as $q => $a ) {
		echo "<dt class='faq_question clicker' alt='$k'>$q</dt><dd class='faq_answer slider' id='$k'>" . nl2br( $a ) . "</dd>";
		$k++;
	}
	echo '</dl>';
}



function github2wp_render_history_page() {
?>
	<div id='github2wp_history_messages'></div>

	<form action='options.php' method='post'>
		<?php
			settings_fields( 'github2wp_options' );
			do_settings_sections( 'github2wp_history' );
			$options = get_option( 'github2wp_options' );
			$resource_list = $options['resource_list'];
			$plugin_render = '';
			$theme_render = '';

			if ( is_array( $resource_list ) && ! empty( $resource_list ) ) {
				foreach ( $resource_list as $key => $resource ) {
					$type = github2wp_get_repo_type( $resource['resource_link'] );

					if ( file_exists(	WP_CONTENT_DIR . "/{$type}s/{$resource['repo_name']}" ) ) {
						if ( 'plugin' == $type ) 
							$plugin_render .= "<tr valign='top'>
														<th scope='row'>
															<label><strong>{$resource['repo_name']}</strong></label>
														</th>
														<td>
															<span class='history-slider clicker button-primary' alt='history-expand-$key'><center>" . __( 'Expand', GITHUB2WP ) . "</center></span>
																<div class='slider home-border-center half' id='history-expand-$key' style='padding-top: 5px;'>
																</div>
															</span>
														</td>
													</tr>";
						elseif( 'theme' == $type ) 
							$theme_render .= "<tr valign='top'>
														<th scope='row'>
															<label><strong>{$resource['repo_name']}</strong></label>
														</th>
														<td>
															<span class='history-slider clicker button-primary' alt='history-expand-$key'><center>" . __( 'Expand', GITHUB2WP ) . "</center></span>
																<div class='slider home-border-center half' id='history-expand-$key' style='padding-top: 5px;'>
																</div>
															</span>
														</td>
													</tr>";
					}
				}
			}
		?>
		<table class='form-table' >
			<tbody>
				<tr><th colspan='2'><h2><?php _e( 'Plugins', GITHUB2WP ); ?></h2><br /></th></tr> 
				<?php echo $plugin_render; ?>
			</tbody>
		</table>
		<br /><br /><br />
		<table class='form-table'>
			<tbody>
				<tr><th colspan='2'><h2><?php _e( 'Themes', GITHUB2WP ); ?></h2><br /></th></tr>
				<?php echo $theme_render; ?>
			</tbody>
		</table>
	</form>
<?php
}



function github2wp_get_resource_render_details( $index, $resource_file, $on_svn ) {
	$output = '';

	$output .= '<strong>' . github2wp_get_header( $resource_file, 'Name' ) . '</strong>&nbsp;(';

	if ( $on_svn )
		$output .= '<div class="notification-warning" title="'
					. __( 'Wordpress has a resource with the same name.\nWe will override its update notifications!', GITHUB2WP) . '"></div>';
	$author = github2wp_get_header( $resource_file, 'Author' );
	$author_uri = github2wp_get_header( $resource_file, 'AuthorURI' );
	$description = github2wp_get_header( $resource_file, 'Description' );
	$version = github2wp_get_header($resource_file, 'Version');
	
	if ( $author_uri )
		$author = "<a href='$author_uri' target='_blank'>$author</a>";

	$output .= __( 'Version ', GITHUB2WP ) . $version . '&nbsp;|&nbsp;';
	$output .= __( 'By ', GITHUB2WP ) . $author . ')&nbsp;';
	$output .= "<a id='need_help_$index' class='clicker' alt='res_details_$index'><strong>"
		. __( 'Details', GITHUB2WP ) . '</strong></a><br />';
	$output .= "<div id='res_details_$index' class='slider home-border-center'>";

	if ( $plugin_description )
		$output .= $description . '<br />';

	return $output;
}



function github2wp_second_section_description() {
	echo '<p>' . __( 'Enter here the default settings for the Github connexion.', GITHUB2WP ) . '</p>';
}

function github2wp_main_history_section_description() {
	echo '<p>' . __( 'You can revert to an older version of a resource at any time.', GITHUB2WP ) . '</p>';
}

function github2wp_resource_display_section_description() {
	echo '<p>' . __( 'Here you can manage your Github resources.', GITHUB2WP ) . '</p>';
}

function github2wp_main_section_description() {
	echo '<p>' . __( 'Enter here the required data to set up a new GitHub endpoint.', GITHUB2WP ) . '</p>';
}

function github2wp_main_faq_section_description() {
	echo '<p>' . __( 'If you can\'t find an answer to your problem contact us.', GITHUB2WP ) . '</p>';
}
