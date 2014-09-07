<?php
/**
 * Plugin Name: Za Kogo Autocomplete
 * Description: Adds jQuery Autocomplete functionality with addresses to Za Kogo search box (base on SearchAutocomplete plugin )
 * Version: 0.5.0
 * Author: Vitaliy Pylypiv
 * License: GPLv2 or later
 */
class ZaKogoAutocomplete {
	protected static $options_field = "sa_settings";
	protected static $options_field_ver = "sa_settings_ver";
	protected static $options_field_current_ver = "0.5.0";
	protected static $options_default = array(
		'autocomplete_search_id'          => '#street_address',
		'autocomplete_minimum'            => 3,
		'autocomplete_numrows'            => 10,
		'autocomplete_posttypes'          => array(),
        'autocomplete_streetfield'        => 'адресса',
        'autocomplete_streetkey'          => 'додай_адресу',
		'autocomplete_exclusions'		=> '',
		'autocomplete_alert'	     	=> "Почніть вводити вулицю, і оберіть адресу зі списку, що з'явиться",
		'autocomplete_theme'              => '/redmond/jquery-ui-1.9.2.custom.min.css',
		'autocomplete_custom_theme'       => '',
	);
	protected static $options_init = array(
		'autocomplete_search_id'          => '#street_address',
		'autocomplete_minimum'            => 3,
		'autocomplete_numrows'            => 10,
		'autocomplete_posttypes'          => array(),
        'autocomplete_streetfield'        => 'адресса',
        'autocomplete_streetkey'          => 'додай_адресу',
		'autocomplete_exclusions'					=> '',
		'autocomplete_alert'	     	=> "Почніть вводити вулицю, і оберіть адресу зі списку, що з'явиться",
		'autocomplete_theme'              => '/redmond/jquery-ui-1.9.2.custom.min.css',
		'autocomplete_custom_theme'       => '',
	);

	var $pluginUrl,
			$defaults,
			$options;

	public function __construct() {
		$this->initVariables();
		add_action( 'wp_enqueue_scripts', array( $this, 'initScripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'initAdminScripts' ) );
		$this->initAjax();
		// init admin settings page
		add_action( 'admin_menu', array( $this, 'adminSettingsMenu' ) );
		add_action( 'admin_init', array( $this, 'adminSettingsInit' ) ); // Add admin init functions
	}

	public function initVariables() {
		$this->pluginUrl = plugin_dir_url( __FILE__ );
		$options         = get_option( self::$options_field );

		$this->options = ( $options !== false ) ? wp_parse_args( $options, self::$options_default ) : self::$options_default;
	}

	public function initScripts() {
		$localVars = array(
			'ajaxurl'   => admin_url( 'admin-ajax.php' ),
			'fieldName' => $this->options['autocomplete_search_id'],
			'minLength' => $this->options['autocomplete_minimum'],
			'autocomplete_alert' => $this->options['autocomplete_alert'],
			'cacheurl' => $this->_getCacheURL()
		);
		if ( $this->options['autocomplete_theme'] !== '--None--' ) {
			wp_enqueue_style( 'ZaKogoAutocomplete-theme', plugins_url( 'css' . $this->options['autocomplete_theme'], __FILE__ ), array(), '1.9.2' );
		}
		if ( wp_script_is( 'jquery-ui-autocomplete', 'registered' ) ) {
			wp_enqueue_script( 'ZaKogoAutocomplete', plugins_url( 'js/search-autocomplete.js', __FILE__ ), array( 'jquery-ui-autocomplete' ), '1.0.0', true );
		}
		else {
			wp_register_script( 'jquery-ui-autocomplete', plugins_url( 'js/jquery-ui-1.9.2.custom.min.js', __FILE__ ), array( 'jquery-ui' ), '1.9.2', true );
			wp_enqueue_script( 'ZaKogoAutocomplete', plugins_url( 'js/search-autocomplete.js', __FILE__ ), array( 'jquery-ui-autocomplete' ), '0.5.0', true );
		}
		wp_localize_script( 'ZaKogoAutocomplete', 'ZaKogoAutocomplete', $localVars );
	}

	public function initAdminScripts() {
		$localAdminVars = array(
			'defaults' => self::$options_default
		);
		wp_enqueue_script( 'ZaKogoAutocompleteAdmin', plugins_url( 'js/admin-scripts.js', __FILE__ ), array( 'jquery-ui-sortable' ), '1.0.0', true );
		wp_localize_script( 'ZaKogoAutocompleteAdmin', 'ZaKogoAutocompleteAdmin', $localAdminVars );
	}

	public function initAjax() {
		add_action( 'wp_ajax_autocompleteCallback', array( $this, 'acCallback' ) );
		add_action( 'wp_ajax_nopriv_autocompleteCallback', array( $this, 'acCallback' ) );
	}

	public function acCallback() {
		global $wpdb;
	
		$stop_words = array(
			'бульв.','бульв','буль','бул',
			'вул.','вул',
			'пров.','пров','про',
			'просп.','просп', 'прос','про' );
		
		$res = '';
		$results = array();
		
		$term  = sanitize_text_field( $_GET['term'] ); 
		if( strpos($term, '0_') === 0 ) {
			$term  = substr( $term, 2  ); // remove prefix
			$term  = base64_decode($term);
		} else {
//		    error_log($term);
		}

		if ( trim($term) && !in_array( trim($term), $stop_words ) && count( $this->options['autocomplete_posttypes'] ) > 0 ) {
			$cache_file_name = $this->_getCacheFileName( $term );
			if( file_exists($cache_file_name) ) {
				$max_age = 24 * 3600;
				if (time()-filemtime($cache_file_name) < $max_age ) {
				 	$res = file_get_contents($cache_file_name);
				} 
			}
			
			if( !$res ) {
				$sql = "SELECT M.*, P.post_title FROM $wpdb->postmeta as M  JOIN  $wpdb->posts as P ON M.post_id=P.ID 
						 WHERE M.meta_key LIKE %s 
						 AND P.post_type=%s
						 AND P.post_status='publish'";

				
				$tokens = explode( ',', $term );
				$sql .= sprintf( " AND M.meta_value LIKE '%s'", "%%".addslashes(trim($tokens[0]))."%%" );
				array_shift($tokens);
				
				$building_tokens = array();
				foreach( $tokens as $token ) {
					$ts = explode( ' ', trim($token) );
					foreach( $ts as $t ) {
						if( trim($t) && !in_array(trim($t), $stop_words) ) {
							if( is_numeric($t) ) {
								$building_tokens[] = trim($t);
							}
							$sql .= sprintf( " AND M.meta_value LIKE '%s'", "%%".addslashes(trim($t))."%%" );
						}
					}
				}

				$rows = $wpdb->get_results(
					 $wpdb->prepare( 
						$sql,
						'%_додай_адресу',
					 	$this->options['autocomplete_posttypes'][0]
				     ),
					 ARRAY_A 
				);
	
				foreach( $rows as $row) {			    
						$linkURL = get_permalink( $row['post_id'] );

						$region = '';
						$field = get_post_meta( $row['post_id'], 'округ' );
						if($field && count($field) ) {
							$district_id = $field[0];
							$linkURL = get_permalink( $district_id );
							$region = get_field( 'район', $district_id );
						}
						
						$linkURL = add_query_arg( array( 'pollstation' => $row['post_id'] ), $linkURL );
						/*
						$field = get_post_meta( $row['post_id'], 'номер' );
						if( $field && count($field) ) {
							$linkURL .= ( strpos($linkURL,'?') > 0 ? '&' : '?' ).'polling_station='.$field[0]; 						
						}
						*/
	
						if( strpos($row['meta_value'], ':' ) > 0 ) {
							// split by buildings
							list( $street, $buildings) = explode(':', $row['meta_value'] );
							$buf = explode(',', $buildings );
							foreach( $buf as $building ) {
								$found = count($building_tokens) == 0;
								foreach($building_tokens as $t ) {
									if( strpos( $building, $t) !== false ) {
										$found = true;
									}
								}
								if( $found ) {
									$results[] = array (
						        		'title' => sprintf( "%s, %s", $street, $building ),
						        		'url'   => $linkURL,
										'region'=> $region								
						        	);	
								}
							}
						} else {									
							$results[] = array (
				        		'title' => sprintf( "%s", $row['meta_value'] ) ,
				        		'url'   => $linkURL,
				        		'region'=> $region
				        	);
						}
				}

				usort($results, 'zakogo_addresses_sort');
				
				$results = apply_filters( 'search_autocomplete_modify_results', $results );
				if( $this->options['autocomplete_numrows'] ) {
					$res = json_encode( array( 'results' => array_slice( $results, 0, $this->options['autocomplete_numrows'] ) ) );
				} else {
					$res = json_encode( array( 'results' => $results ) );
				}
				
				if( count($results) > 0 ) {
					@file_put_contents($cache_file_name, $res);
				}				
			}			
		} else {
		    $res = json_encode( array( 'results' => array() ) );
		}
		
		echo $res;
		die();
	}	


	/*
	 * Admin Settings
	 *
	 */
	public function adminSettingsMenu() {
		$page = add_options_page( 'Za Kogo Autocomplete', 'Za Kogo Autocomplete', 'manage_options', 'search-autocomplete', array( $this, 'settingsPage' ) );
	}

	public function settingsPage() {
		$this->_cleanCache();
		?>
		<div class="wrap searchautocomplete-settings">
			<?php screen_icon(); ?>
			<h2><?php _e( "Za Kogo Autocomplete", "search-autocomplete" ); ?></h2>

			<form action="options.php" method="post">
				<?php wp_nonce_field(); ?>
				<?php
				settings_fields( "sa_settings" );
				do_settings_sections( "search-autocomplete" );
				?>
				<input class="button-primary" name="Submit" type="submit" value="<?php _e( "Save settings", "search-autocomplete" ); ?>">
				<input class="button revert" name="revert" type="button" value="<?php _e( "Revert to Defaults", "search-autocomplete" ); ?>">
			</form>
		</div>
	<?php
	}

	/**
	 *
	 */
	public function adminSettingsInit() {
		register_setting(
			self::$options_field,
			self::$options_field,
			array( $this, "sa_settings_validate" )
		);
		add_settings_section(
			'sa_settings_main',
			__( 'Settings', 'search-autocomplete' ),
			array( $this, 'sa_settings_main_text' ),
			'search-autocomplete'
		);
		add_settings_field(
			'autocomplete_search_id',
			__( 'Search Field Selector', 'search-autocomplete' ),
			array( $this, 'sa_settings_field_selector' ),
			'search-autocomplete',
			'sa_settings_main'
		);
		add_settings_field(
			'autocomplete_minimum',
			__( 'Autocomplete Trigger', 'search-autocomplete' ),
			array( $this, 'sa_settings_field_minimum' ),
			'search-autocomplete',
			'sa_settings_main'
		);
		add_settings_field(
			'autocomplete_numrows',
			__( 'Number of Results', 'search-autocomplete' ),
			array( $this, 'sa_settings_field_numrows' ),
			'search-autocomplete',
			'sa_settings_main'
		);
		add_settings_field(
			'autocomplete_posttypes',
			__( 'Post Types', 'search-autocomplete' ),
			array( $this, 'sa_settings_field_posttypes' ),
			'search-autocomplete',
			'sa_settings_main'
		);
		add_settings_field(
			'autocomplete_streetfield',
			__( 'ACF Street Field Name', 'search-autocomplete' ),
			array( $this, 'sa_settings_field_streetfield' ),
			'search-autocomplete',
			'sa_settings_main'
		);
		add_settings_field(
			'autocomplete_streetkey',
			__( 'ACF Street Field Key', 'search-autocomplete' ),
//			array( $this, 'sa_settings_field_streetkey' ),
			'search-autocomplete',
			'sa_settings_main'
		);

		add_settings_field(
			'autocomplete_alert',
			__( 'Alert ', 'search-autocomplete' ),
			array( $this, 'sa_settings_field_alert' ),
			'search-autocomplete',
			'sa_settings_main'
		);
		add_settings_field(
			'autocomplete_theme',
			__( 'Theme Stylesheet', 'search-autocomplete' ),
			array( $this, 'sa_settings_field_themes' ),
			'search-autocomplete',
			'sa_settings_main'
		);
	}

	public function sa_settings_main_text() {
	}

	public function sa_settings_field_selector() {
		?>
		<input id="autocomplete_search_id" class="regular-text" name="<?php echo self::$options_field; ?>[autocomplete_search_id]" value="<?php echo htmlspecialchars( $this->options['autocomplete_search_id'] ); ?>">
		<p class="description">
			<?php _e( "Any valid CSS selector will work.", "search-autocomplete" ); ?><br>
			<?php _e( "The default search box for TwentyTwelve, TwentyEleven, and TwentyTen is '#s'.", "search-autocomplete" ); ?><br>
			<?php _e( "The default search box for TwentyThirteen is '[name=\"s\"]'.", "search-autocomplete" ); ?>
		</p>
	<?php
	}

	public function sa_settings_field_minimum() {
		?>
		<input id="autocomplete_minimum" class="regular-text" name="<?php echo self::$options_field; ?>[autocomplete_minimum]" value="<?php echo $this->options['autocomplete_minimum']; ?>">
		<p class="description"><?php _e( "The minimum number of characters before the autocomplete triggers.", "search-autocomplete" ); ?>
		<br>
	<?php
	}

	public function sa_settings_field_numrows() {
		?>
		<input id="autocomplete_numrows" class="regular-text" name="<?php echo self::$options_field; ?>[autocomplete_numrows]" value="<?php echo $this->options['autocomplete_numrows']; ?>">
		<p class="description"><?php _e( "The total number of results returned.", "search-autocomplete" ); ?><br>
	<?php
	}


	public function sa_settings_field_posttypes() {
		$selectedTypes = $this->options['autocomplete_posttypes'];
		$args          = array(
			'public' => true,
		);
		$output        = 'objects';
		$postTypes     = get_post_types( $args, $output );
		?><p><?php
		foreach ( $postTypes as $postType ) {
			?>
			<label>
				<input name="<?php echo self::$options_field; ?>[autocomplete_posttypes][]" class="autocomplete_posttypes" id="autocomplete_posttypes-<?php echo $postType->name ?>" type="checkbox" value="<?php echo $postType->name ?>" <?php checked( in_array( $postType->name, $selectedTypes ), true ); ?>>
				<?php echo $postType->labels->name ?></label><br>
		<?php
		}
		?></p>
		<p class="description"><?php _e( 'Select post type to select streets.', 'search-autocomplete' ); ?></p>
	<?php
	}

	public function sa_settings_field_streetfield() {
		?>
		<input id="autocomplete_streetfield" class="regular-text" name="<?php echo self::$options_field; ?>[autocomplete_streetfield]" value="<?php echo $this->options['autocomplete_streetfield']; ?>">
		<input id="autocomplete_streetkey" class="regular-text" name="<?php echo self::$options_field; ?>[autocomplete_streetkey]" value="<?php echo $this->options['autocomplete_streetkey']; ?>">
		<p class="description"><?php _e( "ACF name of the selected above type, where streets are stored.", "search-autocomplete" ); ?>
		<br>
	<?php
	}
	
	public function sa_settings_field_alert() {
		?>
		<input id="autocomplete_alert" class="regular-text" name="<?php echo self::$options_field; ?>[autocomplete_alert]" value="<?php echo $this->options['autocomplete_alert']; ?>">
		<p class="description"><?php _e( "Alert message, shown when user tries to submit search form without choosing an item from the list", "search-autocomplete" ); ?>
		<br>
	<?php
	}

	public function sa_settings_field_themes() {
		$globFilter = dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . '*.css';

		if ( $themeOptions = glob( $globFilter, GLOB_ERR ) ) {
			array_unshift( $themeOptions, __( '--None--', 'search-autocomplete') );
		} else {

		}
		?>
		<select name="<?php echo self::$options_field; ?>[autocomplete_theme]" id="autocomplete_theme">
			<?php
			foreach ( $themeOptions as $stylesheet ) {
				$newSheet = str_replace( dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'css', '', $stylesheet );
//				$newSheet = str_replace( '\\', '/', $newSheet );
				printf( '<option value="%s"%s>%s</option>', $newSheet, ( $newSheet == $this->options['autocomplete_theme'] ) ? ' selected="selected"' : '', $newSheet );
			}
			?>
		</select>
		<p class="description"><?php _e( 'These themes use the jQuery UI standard theme set up.  You can create and download additional themes here: <a href="http://jqueryui.com/themeroller/" target="_blank">http://jqueryui.com/themeroller/</a>', 'search-autocomplete' ); ?>.</p>
		<p class="description"><?php _e( 'To add a new theme to this plugin you must upload the "/css/" directory in the generated theme to the this plugin\'s "/css/" directory.  For example, "/wp-content/plugin/search-autocomplete/css/" would be a default install location', 'search-autocomplete' ); ?>.</p>
		<p class="description"><?php _e( 'The minified (compressed) version of the CSS for ThemeRoller themes typically contain ".min" within their file name.', 'search-autocomplete' ); ?></p>
		<p class="description"><?php _e( 'If you would like to use your own styles outside of the plugin, select "--None--" and no stylesheet will be loaded by the plugin.', 'search-autocomplete' ); ?></p>
	<?php
	}

	public function sa_settings_field_sortorder() {
		?>
		<select name="<?php echo self::$options_field; ?>[autocomplete_sortorder]" id="autocomplete_sortorder">
			<option value="posts" <?php selected( $this->options['autocomplete_sortorder'], 'posts' ); ?>><?php _e( 'Posts First', 'search-autocomplete' ); ?></option>
			<option value="terms" <?php selected( $this->options['autocomplete_sortorder'], 'terms' ); ?>><?php _e( 'Taxonomies First', 'search-autocomplete' ); ?></option>
		</select>
		<p class="description"><?php _e( 'When using multiple types (posts or taxonomies) this controls what order they are sorted in within the autocomplete drop down.', 'search-autocomplete' ); ?></p>
	<?php
	}

	public function sa_settings_validate( $input ) {
		$valid = wp_parse_args( $input, self::$options_default );
		return $valid;
	}

	public function activate( $network_wide ) {
		if ( get_option( 'sa_settings' ) === false ) {
			update_option( 'sa_settings', self::$options_init );
		} else {
			$options = get_option( 'sa_settings' );
		}
	}
	
	private function _getCacheURL() {
		$upload_dir = wp_upload_dir();
		$cache_url = $upload_dir['baseurl'] . '/zakogo_cache';

		return $cache_url;
	}
	
	private function _getCacheDirName() {
		$upload_dir = wp_upload_dir();
		$cache_path = $upload_dir['basedir'] . '/zakogo_cache';

		if(!file_exists($cache_path)){
			error_log( 'create zakogo streets cache in '.$cache_path);
			@mkdir($cache_path);
		}

		return $cache_path;
	}
	
	private function _getCacheFileName($term) {
		$cache_path = $this->_getCacheDirName();

		return $cache_path .'/'.intval($this->options['autocomplete_numrows']).'_'.base64_encode( mb_strtolower($term) ) ;
	}
	
	private function _cleanCache() {	
		$cache_dir = $this->_getCacheDirName();	
		if ($handle = opendir( $cache_dir)) {
	        while (false !== ($entry = readdir($handle))) {
	            if ($entry != "." && $entry != "..") {
	                //unlink( realpath( $cache_dir .'/' . $entry ) );
	                error_log( realpath( $cache_dir .'/' . $entry ) );
	            }
	        }
	        closedir($handle);
		}
	}
}
register_activation_hook( __FILE__, array( 'ZaKogoAutocomplete', 'activate' ) );

$ZaKogoAutocomplete = new ZaKogoAutocomplete();


function zakogo_addresses_sort( $a, $b ) {
	$ret = 0;
	
	$street1 = $a['title'];
	$street2 = $b['title'];
		
	// building numbers
	$b_num1 = 0;
	$b_num2 = 0;
	
	// building suffixes
	$b_suf1 = '';
	$b_suf2 = '';
	
	if( strpos($street1, ",") > 0) {
		list($street1, $b_num1) = explode(',', $street1);
				
		preg_match( '/^(\d+)(.*)$/', trim($b_num1), $matches  );
		
		if( count($matches) > 1 && intval($matches[1]) ) {
			$b_num1 = intval($matches[1]);
		}
		
		if( count($matches) > 2 ) {
			$b_suf1 = $matches[2];
		}
	}
	
	if( strpos($street2, ",") > 0) {
		list($street2, $b_num2) = explode(',', $street2);
		
		preg_match( '/^(\d+)(.*)$/', trim($b_num2), $matches  );
		
		if( count($matches) > 1 && intval($matches[1]) ) {
			$b_num2 = intval($matches[1]);
		}
		
		if( count($matches) > 2 ) {
			$b_suf2 = $matches[2];
		}
	}
		
	if( $street1 > $street2 ) {
		$ret = 1;
	} elseif( $street1 < $street2 ) {
		$ret = -1;
	} else {		
		if( $b_num1 > $b_num2 ) {
			$ret = 1;
		} elseif( $b_num1 < $b_num2 ) {
			$ret = -1;
		} else {
			if( $b_suf1 > $b_suf2 ) {
				$ret = 1;
			} elseif($b_suf1 < $b_suf2) {
				$ret = -1;
			}
		}
	}	
	
	return $ret;
}
