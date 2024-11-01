<?php 
/*
 * WP Evernote Synchronizer
 *
 * @package     WP Evernote Synchronizer
 * @author      Nora
 * @copyright   2016 Nora https://wp-works.com
 * @license     GPL-2.0+
 * 
 * @wordpress-plugin
 * Plugin Name: WP Evernote Synchronizer
 * Plugin URI: https://wp-works.com
 * Description: Enables to Import Notes from Evernote as pages of selected post type.
 * Version: 1.0.7
 * Author: Nora
 * Author URI: https://wp-works.com
 * Text Domain: wp-evernote-synchronizer
 * Domain Path: /languages/
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// Import the classes that we're going to be using
//use EDAM\NoteStore\NoteFilter, EDAM\NoteStore\NotesMetadataResultSpec;
use EDAM\Types\Data, EDAM\Types\Note, EDAM\Types\Resource, EDAM\Types\ResourceAttributes;
use EDAM\Error\EDAMUserException, EDAM\Error\EDAMErrorCode, EDAM\Error\EDAMNotFoundException;
use Evernote\Client;


require_once( 'evernote-sdk-php-master/lib/autoload.php' );

require_once 'evernote-sdk-php-master/lib/Evernote/Client.php';
require_once 'evernote-sdk-php-master/lib/packages/Errors/Errors_types.php';
require_once 'evernote-sdk-php-master/lib/packages/Types/Types_types.php';
require_once 'evernote-sdk-php-master/lib/packages/Limits/Limits_constants.php';
require_once 'evernote-sdk-php-master/lib/protocol/TProtocol.php';


if( ! defined( 'WPES_PATH' ) ) define( 'WPES_PATH', plugin_dir_url( __FILE__ ) );

if( ! class_exists( 'WP_Evernote_Synchronizer' ) ) {

	class WP_Evernote_Synchronizer {

		public static $registered_object_data = array();
		public static $post_types;

		public static $evernote_ids;

		public static $developerToken;
		public static $notebook_name;
		public static $evernote_tags;
		public static $object_taxonomies;
		public static $object_terms = array();

		function __construct() {

			$this->wpes_add_actions();

		}

		#
		# アクションフックに追加
		#
			function wpes_add_actions() {

				if( is_admin() ) {

					// 初期化
						add_action( 'init', array( 'WP_Evernote_Synchronizer', 'wpes_add_post_type_enpages' ) );
						add_action( 'init', array( 'WP_Evernote_Synchronizer', 'wpes_init' ), 100 );

					// 設定ページの追加
						add_action( 'admin_menu', array( $this, 'wpes_options_menu' ) );

					// ユーザー設定に項目を追加

						// プロフィールの更新
							add_action( 'personal_options_update', array( $this, 'wpes_update_extra_profile_fields' ), 11 );
							//add_action( 'edit_user_profile_update', array( $this, 'wpes_update_extra_profile_fields' ), 11 );
						
						// プロフィールの設定を追加
							add_action( 'show_user_profile', array( $this, 'wpes_custom_user_profile_fields' ), 11 );
							//add_action( 'edit_user_profile', array( $this, 'wpes_custom_user_profile_fields' ), 11 );
					
					// スクリプト
						add_action( 'admin_enqueue_scripts', array( $this, 'wpes_admin_enqueue_scripts' ) );

				}

			}

		#
		# アクションフック「init」
		#
			// カスタム投稿タイプ「wpes-evernote」を追加
				public static function wpes_add_post_type_enpages() {
					
					$labels = array(
						'name'               => __( 'Evernotes', 'wp-evernote-synchronizer' ),
						'singular_name'      => __( 'Evernote', 'wp-evernote-synchronizer' ),
						'menu_name'          => __( 'Evernotes', 'wp-evernote-synchronizer' ),
						'name_admin_bar'     => __( 'Evernote', 'wp-evernote-synchronizer' ),
						'add_new'            => __( 'Add new', 'wp-evernote-synchronizer' ),
						'add_new_item'       => __( 'Add New Evernotes', 'wp-evernote-synchronizer' ),
						'new_item'           => __( 'New Evernote', 'wp-evernote-synchronizer' ),
						'edit_item'          => __( 'Edit Evernote', 'wp-evernote-synchronizer' ),
						'view_item'          => __( 'View Evernote', 'wp-evernote-synchronizer' ),
						'all_items'          => __( 'All Evernotes', 'wp-evernote-synchronizer' ),
						'search_items'       => __( 'Search Evernotes', 'wp-evernote-synchronizer' ),
						'parent_item_colon'  => __( 'Parent Evernotes:', 'wp-evernote-synchronizer' ),
						'not_found'          => __( 'No Evernotes found.', 'wp-evernote-synchronizer' ),
						'not_found_in_trash' => __( 'No Evernotes found in Trash.', 'wp-evernote-synchronizer' )
					);

					$args = array(
						'labels'              => $labels,
						'public'              => false,
						'exclude_from_search' => true,
						'publicly_queryable'  => false,
						'show_ui'             => true,
						'show_in_menu'        => true,
						'query_var'           => false,
						'rewrite'             => array( 'slug' => 'wpes-evernote' ),
						'capability_type'     => 'post',
						'has_archive'         => false,
						'hierarchical'        => false,
						'menu_position'       => null,
						'supports'            => array( 'title', 'editor', 'author' )

					);

					register_post_type( 'wpes-evernote', $args );
					
					$labels = array(
						'name'              => __( 'Notebooks', 'wp-evernote-synchronizer' ),
						'singular_name'     => __( 'Notebook', 'wp-evernote-synchronizer' ),
						'search_items'      => __( 'Search Notebooks', 'wp-evernote-synchronizer' ),
						'all_items'         => __( 'All Notebooks', 'wp-evernote-synchronizer' ),
						'parent_item'       => __( 'Parent Notebook', 'wp-evernote-synchronizer' ),
						'parent_item_colon' => __( 'Parent Notebook:', 'wp-evernote-synchronizer' ),
						'edit_item'         => __( 'Edit Notebook', 'wp-evernote-synchronizer' ),
						'update_item'       => __( 'Update Notebook', 'wp-evernote-synchronizer' ),
						'add_new_item'      => __( 'Add New Notebook', 'wp-evernote-synchronizer' ),
						'new_item_name'     => __( 'New Notebook Name', 'wp-evernote-synchronizer' ),
						'menu_name'         => __( 'Notebook', 'wp-evernote-synchronizer' ),
					);

					$args = array(
						'hierarchical'      => true,
						'labels'            => $labels,
						'show_ui'           => true,
						'show_admin_column' => true,
						'query_var'         => false,
						'rewrite'           => array( 'slug' => 'wpes-evernote-notebook' ),
					);

					register_taxonomy( 'wpes-evernote-notebook', array( 'wpes-evernote' ), $args );
					
					$labels = array(
						'name'              => __( 'Tags', 'wp-evernote-synchronizer' ),
						'singular_name'     => __( 'Tag', 'wp-evernote-synchronizer' ),
						'search_items'      => __( 'Search Tags', 'wp-evernote-synchronizer' ),
						'all_items'         => __( 'All Tags', 'wp-evernote-synchronizer' ),
						'parent_item'       => __( 'Parent Tag', 'wp-evernote-synchronizer' ),
						'parent_item_colon' => __( 'Parent Tag:', 'wp-evernote-synchronizer' ),
						'edit_item'         => __( 'Edit Tag', 'wp-evernote-synchronizer' ),
						'update_item'       => __( 'Update Tag', 'wp-evernote-synchronizer' ),
						'add_new_item'      => __( 'Add New Tag', 'wp-evernote-synchronizer' ),
						'new_item_name'     => __( 'New Tag Name', 'wp-evernote-synchronizer' ),
						'menu_name'         => __( 'Tag', 'wp-evernote-synchronizer' ),
					);

					$args = array(
						'hierarchical'      => true,
						'labels'            => $labels,
						'show_ui'           => true,
						'show_admin_column' => true,
						'query_var'         => false,
						'rewrite'           => array( 'slug' => 'wpes-evernote-tag' ),
					);

					register_taxonomy( 'wpes-evernote-tag', array( 'wpes-evernote' ), $args );

				}

			// 初期化
				public static function wpes_init() {

					self::$post_types = get_post_types( 
						array( 
							//'show_ui' => true, 
							'show_in_menu' => true 
						), 
						'objects' 
					);

					self::wpes_get_registered_data_for_json();

					//$this->$evernote_ids = get_option( '', '' );

				}

		#
		# 設定ページ
		#
			#
			# 設定ページの追加
			#
				function wpes_options_menu() {

					if( current_user_can( 'manage_options' ) ) {

						add_submenu_page( 
							'edit.php?post_type=wpes-evernote',
							__( 'Settings', 'wp-evernote-synchronizer' ), 
							__( 'Settings', 'wp-evernote-synchronizer' ), 
							'edit_posts', 
							'wpes_options', 
							array( $this, 'wpes_settings_page' ) 
						);

						// 設定項目
							register_setting( 'wpes_options', 'wpes_evernote_common_token', 'sanitize_text_field' );
							register_setting( 'wpes_options', 'wpes_evernote_udpated_status', 'sanitize_text_field' );
							register_setting( 'wpes_options', 'wpes_evernote_update_suspend', 'sanitize_text_field' );
							register_setting( 'wpes_options', 'wpes_evernote_update_action_interval', 'wpes_sanitize_interval' );
						// 投稿タイプ毎の設定
							register_setting( 'wpes_options', 'wpes_evernote_notebook_on', 'wpes_sanitize_array_of_text' );
							register_setting( 'wpes_options', 'wpes_evernote_notebook_key', 'wpes_sanitize_array_of_text' );
						// 除外タグ
							register_setting( 'wpes_options', 'wpes_evernote_exclude_tags', 'sanitize_text_field' );

							add_action( 'update_option_wpes_evernote_update_suspend', array( $this, 'update_the_cron' ), 10, 3 );

					}

				}

				function wpes_settings_page() {

					echo '<h2>' . __( 'Evernote Settings', 'wp-evernote-synchronizer' ) . '</h2>';

					echo '<form id="wpes-settings-form" method="post" action="options.php">';

						settings_fields( 'wpes_options' );
						do_settings_sections( 'wpes_options' );

						// 共通設定
							$post_type_evernote_token = get_option( 'wpes_evernote_common_token', '' );
							$wpes_evernote_udpated_status = get_option( 'wpes_evernote_udpated_status', 'draft' );
							$wpes_evernote_update_suspend = get_option( 'wpes_evernote_update_suspend', 'wpes_evernote_update_suspend' );
							$wpes_evernote_update_action_interval = absint( get_option( 'wpes_evernote_update_action_interval', 30 ) );
						// 投稿タイプのノートブックの名前
							$post_type_notebook_on = get_option( 'wpes_evernote_notebook_on', array() );
							if ( ! is_array( $post_type_notebook_on ) ) {
								$post_type_notebook_on = array();
							}
							$post_type_notebook_name = get_option( 'wpes_evernote_notebook_key', array() );
						// 除外タグ
							$wpes_evernote_exclude_tags = get_option( 'wpes_evernote_exclude_tags', '' );


						echo '<div class="metabox-holder">';
							echo '<div id="general-settings-wrapper" class="settings-wrapper postbox">';
								echo '<h3 id="general-settings-h2" class="form-table-title hndle">' . __( 'Common Settings', 'wp-evernote-synchronizer' ) . '</h3>';
								echo '<div class="inside"><div class="main">';

									echo __( 'Last Update : ', 'wp-evernote-synchronizer' ) . get_option( 'wpes_update_time' ) . '<br>';

									echo __( 'Current Time : ', 'wp-evernote-synchronizer' ) . date( "Y-m-d H:i:s T" ) . '<br>';

									echo __( 'Next Update : ', 'wp-evernote-synchronizer' ) . ( $wpes_evernote_update_suspend != '' ? __( 'Suspended', 'wp-evernote-synchronizer' ) : date( "Y-m-d H:i:s T", wp_next_scheduled( 'wpes_evernote_update' ) ) ) . '<br>';

									//echo date( "Y-m-d H:i:s T", wp_next_scheduled( 'wpes_evernote_update' ) ); // チェック用

									/*
										echo '<pre>';
										$data = _get_cron_array();
										print_r( $data );
										echo '</pre>';
									*/

									// 共通設定
										echo '<table class="form-table">';
											echo '<tbody>';

											// トークン
												echo '<tr>';
													echo '<th>';
														echo '<label for="wpes_evernote_common_token">' . __( 'Evernote Developer Token', 'wp-evernote-synchronizer' ) . '</label>';
													echo '</th>';

													echo '<td>';
													echo '<p><small>' . __( 'Enter <a target="_blank" href="https://www.evernote.com/api/DeveloperToken.action">Developer Token</a> to import notes from the account. This will importe notes as pages written by User ID "1".<br>In order to import notes as pages written by each user, please set the Token in page "Your Profile".', 'wp-evernote-synchronizer' ) . '</small></p>';
														echo '<input type="text" name="wpes_evernote_common_token" value="' . $post_type_evernote_token . '" style="width: 100%;">';
													echo '</td>';
												echo '</tr>';

											// ステータス
												echo '<tr>';
													echo '<th>';
														echo '<label>' . __( 'Imported Note Status', 'wp-evernote-synchronizer' ) . '</label>';
													echo '</th>';

													echo '<td>';
													echo '<p><small>' . __( 'Select the status of Pages when imported.', 'wp-evernote-synchronizer' ) . '</small></p>';
													echo '<input type="radio" name="wpes_evernote_udpated_status" value="draft" ' . checked( $wpes_evernote_udpated_status, 'draft', false ) . '><label>' . __( 'Draft', 'wp-evernote-synchronizer' ) . '</label><br>';
													echo '<input type="radio" name="wpes_evernote_udpated_status" value="publish" ' . checked( $wpes_evernote_udpated_status, 'publish', false ) . '><label>' . __( 'Publish', 'wp-evernote-synchronizer' ) . '</label><br>';
													echo '</td>';
												echo '</tr>';

											// 停止モード
												echo '<tr>';
													echo '<th>';
														echo '<label for="wpes_evernote_update_suspend">' . __( 'Suspend Mode', 'wp-evernote-synchronizer' ) . '</label>';
													echo '</th>';

													echo '<td>';
													echo '<p><input type="checkbox" name="wpes_evernote_update_suspend" value="wpes_evernote_update_suspend" ' . checked( $wpes_evernote_update_suspend, 'wpes_evernote_update_suspend', false ) . '>&nbsp;<small>' . __( 'Suspend the Auto Synchronization by Cron. ( This suspends calling update function called in Cron )', 'wp-evernote-synchronizer' ) . '</small></p>';
													echo '</td>';
												echo '</tr>';

											// インターバル
												echo '<tr>';
													echo '<th>';
														echo '<label for="wpes_evernote_update_action_interval">' . __( 'Interval', 'wp-evernote-synchronizer' ) . '</label>';
													echo '</th>';

													echo '<td>';
													echo '<p><small>' . __( 'Interval( Minutes ) of import action.', 'wp-evernote-synchronizer' ) . '</small></p>';
														echo '<input type="number" name="wpes_evernote_update_action_interval" value="' . $wpes_evernote_update_action_interval . '" min="10" max="1440" step="10" style="width: 80px;">';
													echo '</td>';
												echo '</tr>';

											echo '</tbody>';
										echo '</table>';

									// 投稿タイプ設定の詳細
										echo '<h3>' . __( 'Post Type Detection', 'wp-evernote-synchronizer' ) . '</h3>';
										echo '<p><small>';
											_e( 'If you want to update pages for each post type,<br>check the box and create Notebooks on the Evernote account.<br>Pages will be imported as a page of the post type.<br> If you don\'t want to import( or update ), remove the check.<br><br><strong>* You must set the unique notebook name even if the check is removed.<br>( It may cause problems to USE Same Notebook for other Post Type )</strong><br>', 'wp-evernote-synchronizer' );
										echo '</small></p>';

										// 各投稿タイプ
											echo '<div class="popup-object-taxonomy-options-background"></div>';
											echo '<table class="form-table">';
												echo '<tbody>';
													echo '<tr>';
														echo '<th>';
															echo '<label>' . __( 'Post Type', 'wp-evernote-synchronizer' ) . '</label>';
														echo '</th>';
														echo '<td>';
															echo '<p>' . __( 'Notebook', 'wp-evernote-synchronizer' ) . '</p>';
														echo '</td>';
													echo '</tr>';

													global $_wp_post_type_features;
													if( is_array( self::$post_types ) ) { foreach( self::$post_types as $post_type ) {

														// セットアップ
															// 表示名
																$post_type_displayed_name = $post_type->labels->name;
//var_dump( $post_type );
															// 投稿タイプ名
																$post_type_name = $post_type->name;
																if( $post_type_name == 'product' ) continue;

															// コンテンツエディターがサポートされていない場合はスルー
																if( ! isset( $_wp_post_type_features[ $post_type_name ][ 'editor' ] ) 
																	|| ! $_wp_post_type_features[ $post_type_name ][ 'editor' ] 
																) continue;

																if( ! isset( $post_type_notebook_on[ $post_type_name ] ) ) {
																	$post_type_notebook_on[ $post_type_name ] = '';
																}

																if( ! isset( $post_type_notebook_name[ $post_type_name ] ) ) {
																	$post_type_notebook_name[ $post_type_name ] = $post_type_displayed_name;
																}

															// タクソノミー
																$object_taxonomies = get_object_taxonomies( $post_type_name, 'objects' );

															// 権限
																//$post_type_caps = ( array ) $post_type->cap;

														echo '<tr>';
															echo '<th style="padding: 0;">';
																echo '<label for="wpes_evernote_notebook_key[' . $post_type_name . ']">' . $post_type_displayed_name . '</label>';
															echo '</th>';
															echo '<td style="padding: 0;">';
																echo '<p>';
																	// Notebook
																		echo '<input type="checkbox" name="wpes_evernote_notebook_on[' . $post_type_name . ']" value="' . $post_type_name . '" ' . checked( $post_type_notebook_on[ $post_type_name ], $post_type_name, false ) . '">&nbsp';
																		echo '<input type="text" name="wpes_evernote_notebook_key[' . $post_type_name . ']" value="' . $post_type_notebook_name[ $post_type_name ] . '">';
																echo '</p>';
															echo '</td>';
														echo '</tr>';

													} }

												echo '</tbody>';
											echo '</table>';

									// 除外タグの詳細
										echo '<h3>' . __( 'Exclude Tags', 'wp-evernote-synchronizer' ) . '</h3>';
										echo '<p><small>';
											_e( 'Notes which have at least one of tags below will not be imported.', 'wp-evernote-synchronizer' );
										echo '</small></p>';

										echo '<table class="form-table">';
											echo '<tbody>';

											echo '<tr>';
												echo '<th>';
													echo '<label for="wpes_evernote_exclude_tags">' . __( 'Exclude Tags', 'wp-evernote-synchronizer' ) . '</label>';
												echo '</th>';
												echo '<td>';
													echo '<p><small>';
														_e( 'Commas Separate', 'wp-evernote-synchronizer' );
													echo '</small></p>';
													echo '<input type="text" name="wpes_evernote_exclude_tags" value="' . $wpes_evernote_exclude_tags . '">&nbsp';
												echo '</td>';
											echo '</tr>';

											echo '</tbody>';
										echo '</table>';

									echo '<input type="submit" id="wpes-save-button" class="button-primary" value="' . __( 'Save', 'wp-evernote-synchronizer' ) . '">';

									echo '<p><small>&nbsp;' . __( 'Before click the button "Update Now", Please Save the Settings Above Once because this will use the saved data ( Not Current Settings Data on UI Above even if you changed ).', 'wp-evernote-synchronizer' ) . '</small></p>';

									echo '<a id="wpes-update-button" class="button" href="#wpes-ajax-message">' . __( 'Update Now', 'wp-evernote-synchronizer' ) . '</a>';

								echo '</div></div>';
							echo '</div>';
						echo '</div>';


					echo '</form>';

					echo '<div class="metabox-holder">';
						echo '<div id="general-settings-wrapper" class="settings-wrapper postbox">';
							echo '<h3 id="general-settings-h2" class="form-table-title hndle">' . __( 'Messages for "Update Now"', 'wp-evernote-synchronizer' ) . '</h3>';
							echo '<div class="inside"><div class="main">';

								echo '<pre id="wpes-ajax-message">';

									// ボタン「Update Now」で処理内容が出力されます。
									//wpes_users_evernote_update();

								echo '</pre>';

							echo '</div></div>';
						echo '</div>';
					echo '</div>';

				}

				function update_the_cron( $old_value, $value, $option = 'wpes_evernote_update_suspend' )
				{

					// Reset Schedules
						// Deactivate
						$timestamp = wp_next_scheduled( 'wpes_evernote_update' );
						wp_unschedule_event( $timestamp, 'wpes_evernote_update' );
						wp_clear_scheduled_hook( 'wpes_evernote_update' );

						// Set
						if ( 'wpes_evernote_update_suspend' !== $value ) {
							wp_schedule_event( time(), 'en_update_interval', 'wpes_evernote_update' );
						}

				}

			#
			# 各ユーザー毎の設定項目を追加
			#
				// 追加したユーザーの設定を保存する
					function wpes_update_extra_profile_fields( $user_id ) {

						$wpes_evernote_udpated_status = get_option( 'wpes_evernote_udpated_status', 'draft' );

						if( ( $wpes_evernote_udpated_status === 'publish' && ! current_user_can( 'publish_posts' ) )
							|| ( $wpes_evernote_udpated_status === 'draft' && ! current_user_can( 'edit_posts' ) )
						) {
							return;
						}

						check_admin_referer( 'wpes_evernote_settings', 'wpes_evernote_settings_nonce' );

						remove_action( 'profile_update', array( $this, 'wpes_update_extra_profile_fields' ) );

						if( isset( $_POST[ 'wpes_user_evernote_token' ] ) ) {
							
							$wpes_user_evernote_token = sanitize_text_field( $_POST[ 'wpes_user_evernote_token' ] );
							
							update_user_meta( $user_id, 'wpes_user_evernote_token', $wpes_user_evernote_token );

						}
						
						add_action( 'profile_update', array( $this, 'wpes_update_extra_profile_fields' ) );

					}

				// ユーザーに設定項目を追加する
					function wpes_custom_user_profile_fields( $user ) {

						$wpes_evernote_udpated_status = get_option( 'wpes_evernote_udpated_status', 'draft' );

						if( ( $wpes_evernote_udpated_status === 'publish' && ! current_user_can( 'publish_posts' ) )
							|| ( $wpes_evernote_udpated_status === 'draft' && ! current_user_can( 'edit_posts' ) )
						) {
							return;
						}

						wp_nonce_field( 'wpes_evernote_settings', 'wpes_evernote_settings_nonce' );

						$user_data = ( array ) $user->data;

						$wpes_user_evernote_token = get_user_meta( $user_data[ 'ID' ], 'wpes_user_evernote_token', true );

						// 設定
							$post_type_notebook_on = get_option( 'wpes_evernote_notebook_on', array() );
							$post_type_notebook_name = get_option( 'wpes_evernote_notebook_key', array() );
						// 除外タグ
							$wpes_evernote_exclude_tags = preg_split( '/,\s*/', get_option( 'wpes_evernote_exclude_tags', '' ) );

						echo '<div class="metabox-holder">';
							echo '<div id="general-settings-wrapper" class="settings-wrapper postbox">';
								echo '<h3 id="general-settings-h2" class="form-table-title hndle">' . __( 'Settings by WP Evernote Synchronier', 'wp-evernote-synchronizer' ) . '</h3>';
								echo '<div class="inside"><div class="main">';

									//echo '<h3>' . __( 'Settings by WP Evernote Synchronier', 'wp-evernote-synchronizer' ) . '</h3>';

									?>
									<table class="form-table">

										<tbody>

											<tr>
												<th scope="row">
													<label for="wpes_user_evernote_token">
														<?php _e( "Evernote Token", 'shapeshifter' ); ?>
													</label>
												</th>
												<td>
													<?php echo '<p><small>' . __( 'Enter <a target="_blank" href="https://www.evernote.com/api/DeveloperToken.action">Developer Token</a> to import notes from your account.', 'wp-evernote-synchronizer' ) . '</small></p>'; ?>

													<input 
														type="text" 
														id="wpes_user_evernote_token" 
														class="regular-hidden-field" 
														name="wpes_user_evernote_token" 
														value="<?php echo esc_attr( $wpes_user_evernote_token ); ?>"
														style="width: 100%;"
													/>
													<p><small><?php _e( 'You can import notes in Notebook named following as Pages of Post Type.<br>Please check available Post Types and Notebook Names.', 'wp-evernote-synchronizer' ) ?></small></p>
													<table><tbody>
														<tr>
															<th style="padding-left: 0;"><?php _e( 'Post Type:', 'wp-evernote-synchronizer' ) ?></th>
															<td style="padding-left: 0;"><?php _e( 'Notebook', 'wp-evernote-synchronizer' ) ?></td>
														</tr>
													<?php
														// 投稿タイプとノートブック
														global $_wp_post_type_features;
														if( is_array( self::$post_types ) ) { foreach( self::$post_types as $post_type ) {

															// セットアップ
																// 表示名
																$post_type_displayed_name = $post_type->labels->name;
																// 投稿タイプ名
																$post_type_name = $post_type->name;

																// コンテンツエディターがサポートされていない場合はスルー
																if( ! isset( $_wp_post_type_features[ $post_type_name ][ 'editor' ] ) 
																	|| ! $_wp_post_type_features[ $post_type_name ][ 'editor' ] 
																) continue;

																if( ! isset( $post_type_notebook_on[ $post_type_name ] ) ) {
																	continue;
																}

																if( ! isset( $post_type_notebook_name[ $post_type_name ] ) ) {
																	$post_type_notebook_name[ $post_type_name ] = $post_type_displayed_name;
																}

															// 出力
																echo '<tr style="">
																	<th style="padding: 0;">' . sprintf( '%s', $post_type_displayed_name ) . ':</th>
																	<td style="padding: 0;">' . sprintf( '"%s"', $post_type_notebook_name[ $post_type_name ] ) . '</td>
																</tr>';

														} }
														// 除外タグ
														echo '<tr>';
															echo '<th>';
																echo __( 'Exclued Tags:', 'wp-evernote-synchronizer' );
															echo '</th>';
															echo '<td>';
																echo '<p><small>' . __( 'Notes which has at least one of tags following will not be imported.', 'wp-evernote-synchronizer' ) . '</small></p>';
																if( is_array( $wpes_evernote_exclude_tags ) ) { foreach( $wpes_evernote_exclude_tags as $index => $tag ) {
																	echo sprintf( __( '"%s"', 'wp-evernote-synchronizer' ), $tag ) . '&nbsp;';
																} }
															echo '</td>';

														echo '</tr>';

														?>
													</tbody></table>
												</td>
											</tr>

											<tr>
												<th style="padding-top: 0; padding-bottom: 0;">
													<?php echo __( 'Last Auto Update : ', 'wp-evernote-synchronizer' ); ?>
												</th>
												<td style="padding-top: 0; padding-bottom: 0;">
													<?php echo get_option( 'wpes_update_time' ); ?>
												</td>
											</tr>

											<tr>
												<th style="padding-top: 0; padding-bottom: 0;">
													<?php echo __( 'Current Time : ', 'wp-evernote-synchronizer' ); ?>
												</th>
												<td style="padding-top: 0; padding-bottom: 0;">
													<?php echo date( "Y-m-d H:i:s T" ); ?>
												</td>
											</tr>

											<tr>
												<th style="padding-top: 0; padding-bottom: 0;">
													<?php echo __( 'Next Auto Update : ', 'wp-evernote-synchronizer' ); ?>
												</th>
												<td style="padding-top: 0; padding-bottom: 0;">
													<?php echo ( get_option( 'wpes_evernote_update_suspend', 'wpes_evernote_update_suspend' ) != '' ? __( 'Suspended', 'wp-evernote-synchronizer' ) : date( "Y-m-d H:i:s T", wp_next_scheduled( 'wpes_evernote_update' ) ) ); ?>
												</td>
											</tr>

											<tr>
												<th>
													<?php echo '<a id="wpes-update-user-button" class="button" href="#wpes-ajax-message">' . __( 'Update Now', 'wp-evernote-synchronizer' ) . '</a>'; ?>
												</th>

												<td>
													<?php 
														//echo '<p><small>&nbsp;' . __( 'Before click the button "Update Now", Please Save the Settings Above Once because this will use the saved data ( Not Current Settings Data on UI Above even if you changed ).', 'wp-evernote-synchronizer' ) . '</small></p>'; 
														echo '<p style="color: green;">Status: <span id="wpes-ajax-user-message"></span></p>';
													?>
												</td>

											</tr>

										</tbody>

									</table>

									<?php 

								echo '</div></div>';
							echo '</div>';
						echo '</div>';

					}

			#
			# スクリプト
			#
				function wpes_admin_enqueue_scripts() {

					// Register
						wp_register_style( 'wpes-admin-css', WPES_PATH . 'css/wpes-admin-options-page.css' );
						wp_register_script( 'wpes-admin-js', WPES_PATH . 'js/wpes-admin-options-page.js' );

					// Localize Data
						$data = self::wpes_get_registered_data_for_json();

						wp_localize_script( 'wpes-admin-js', 'wpesRegisteredData', $data );

					// Enqueue
						wp_enqueue_script( 'wpes-admin-js' );

				}

		#
		# アップデート
		#
			public static function wpes_update_evernote_notes( $developerToken, $author_id = 1, $post_status = 'draft', $post_type_notebook_on = array(), $post_type_notebook_name = array(), $wpes_evernote_exclude_tags = array() ) {

				set_time_limit( 0 );

				#
				# セットアップ
				#
					// トークン
						//$developerToken = get_option( 'wpes_evernote_common_token', '' );
						if( $developerToken == '' ) {
							echo '<p class="wpes-message-notice">' . __( 'No token.', 'wp-evernote-synchronizer' ) . '</p>';
							return;
						}

					// テスト環境
						$sandbox = false;

					// クライアントを取得
						$client = new \Evernote\Client( array(
							'token' => $developerToken,
							'sandbox' => $sandbox
						) );

						//return;

						//print_r( $client );

					// NoteStore
						try {
							$noteStore = $client->getNoteStore();							
						} catch( Exception $e ) {
							//print_r( $e );
							printf( '<p class="wpes-message-error">' . __( 'Error User ID: %d', 'wp-evernote-synchronizer' ) . '</p>', $author_id );
							echo '<p class="wpes-message-error">' . __( 'Please, check the entered Token or the Expiration', 'wp-evernote-synchronizer' ) . '</p>';
							return;
						}

						//print_r( $noteStore );

					#
					# 設定を取得
					#
						// 投稿ステータス
							//$post_status = get_option( 'wpes_evernote_udpated_status', 'draft' );

						// 投稿タイプのノートブックの名前
							//$post_type_notebook_on = get_option( 'wpes_evernote_notebook_on', array() );
							//$post_type_notebook_name = get_option( 'wpes_evernote_notebook_key', array() );
							//print_r( $post_type_notebook_on );
							//print_r( $post_type_notebook_name );
						// 除外タグ
							//$wpes_evernote_exclude_tags = preg_split( '/,\s?/', get_option( 'wpes_evernote_exclude_tags', '' ) );
							//print_r( $wpes_evernote_exclude_tags );

				#
				# 各ノートブック
				#
					$notebooks = $noteStore->listNotebooks();
					$notes = array();
					if( is_array( $notebooks ) ) { foreach( $notebooks as $index => $notebook ) {

						// ノートブックの名前
							self::$notebook_name = $notebook_name = $notebook->name;
							//echo '<br><br>' . "Notebook: " . $notebook_name . '<br><br>'; // チェック用

						#
						# 投稿タイプのチェック
						#
							// 投稿タイプを取得
								$post_type = self::wpes_is_evernote_notebook_on_for_post_type( $notebook_name, $post_type_notebook_on, $post_type_notebook_name );

							// 該当しない場合
								if( ! $post_type ) {
									//printf( __( 'Notebook "%s" No', 'wp-evernote-synchronizer' ) . '<br><br>', $notebook_name );// チェック用
									continue;
								}

								echo '<p class="wpes-message-info pwpes-message-ost-type">' . sprintf( __( 'Notebook "%s"', 'wp-evernote-synchronizer' ), $notebook_name ) . '&nbsp;=>&nbsp;' . sprintf( __( 'Post Type "%s"', 'wp-evernote-synchronizer' ), $post_type ) . '</p>';

							// 該当する場合
								$posts = get_posts( array( 
									'post_type' => $post_type,
									'post_status' => array( 'draft', 'publish' ),
									'posts_per_page' => -1
								) ); //print_r( $posts ); // チェック用

							// タクソノミー
								self::$object_terms = array();
								self::$object_taxonomies = $object_taxonomies = get_object_taxonomies( $post_type/*, 'object'*/ );
								foreach( $object_taxonomies as $index => $taxonomy_name ) {

									if( in_array(
										$taxonomy_name,
										array( 'post_format', 'wpes-evernote-notebook' )
									) ) continue;

									self::$object_terms[ $taxonomy_name ] = array();
									self::$object_terms[ $taxonomy_name ][ 'ids-names' ] = get_terms( array(
										'taxonomy' => $taxonomy_name,
										'fields' => 'id=>name',
										'hide_empty' => false
									) );
									self::$object_terms[ $taxonomy_name ][ 'objects' ] = get_terms( array(
										'taxonomy' => $taxonomy_name,
										'hide_empty' => false
									) );
										
								}

						#
						# for "findNotesMetadata"
						#
							// Params
								// ノートブックGuid
									$notebook_guid = $notebook->guid;
								// フィルター
									$noteFilter = new \EDAM\NoteStore\NoteFilter( array(
										'ascending' => true,
										'notebookGuid' => $notebook_guid
									) );
								// オフセット
									$offset = 0;
								// 最大取得数
									$findNoteCounts = $noteStore->findNoteCounts(
										$developerToken,
										$noteFilter,
										false
									);
									$maxNotes = $findNoteCounts->notebookCounts[ $notebook_guid ];
								// 指定不要だけど型のみ必要なので取得
									$NotesMetadataResultSpec = new EDAM\NoteStore\NotesMetadataResultSpec();

							// GetNotesMetadata
								$notes[ $notebook_guid ] = $noteStore->findNotesMetadata(
									$developerToken,
									$noteFilter,
									$offset,
									$maxNotes,
									$NotesMetadataResultSpec
								);

						#
						# for Each NoteMetadata
						#
						if( is_array( $notes[ $notebook_guid ]->notes ) ) { foreach( $notes[ $notebook_guid ]->notes as $index => $noteMetadata ) {

							#
							# ノートの取得
							#
								$note_guid = $noteMetadata->guid;
								$withContent = true;
								$withResourcesData = true;
								$withResourcesRecognition = true;
								$withResourcesAlternateData = true;

								$note = $noteStore->getNote(
									$developerToken,
									$note_guid,
									$withContent,
									$withResourcesData,
									$withResourcesRecognition,
									$withResourcesAlternateData
								);

							#
							# 除外タグのチェック
							#	
								self::$evernote_tags = $evernote_tags = self::wpes_get_evernote_tags( $developerToken, $noteStore, $note );
								$is_exclude = false;
								if( is_array( self::$evernote_tags ) ) { foreach( self::$evernote_tags as $index => $data ) {

									if( in_array( $data, $wpes_evernote_exclude_tags ) )
										$is_exclude = true;

								} }
								if( $is_exclude ) {
									printf( '<p class="wpes-message-notice">' . "\t" . __( 'Note "%s" has the Exclude Tag.', 'wp-evernote-synchronizer' ) . '</p>', $note->title );// チェック用
									continue;
								}

							#
							# 更新すべきかチェック
							#
								$will_update_page = self::wpes_will_update_page( $note, $posts );
								if( $will_update_page === false ) {
									printf( '<p class="wpes-message-notice">' . "\t" . __( 'Skip the note "%s" because it has already been imported and not updated in Evernote.', 'wp-evernote-synchronizer' ) . '</p>', $note->title );
									continue;
								}

							#
							# データの更新
							#
								$inserted_object_id = self::wpes_exec_evernote_update( $developerToken, $author_id, $noteStore, $note, $post_type, $post_status, $will_update_page );

							#
							# タームの更新
							#
								if( ! empty( $inserted_object_id ) ) {

									// 投稿タイプがエバーノートの場合にノートブックの登録（ タクソノミーリストから除外しているため ）
										if( $post_type === 'wpes-evernote' ) {
											$completed_notebook = wp_set_object_terms( $inserted_object_id, self::$notebook_name, 'wpes-evernote-notebook', false );
										}
									// その他のタクソノミーのターム
										self::wpes_exec_evernote_tags_update( $inserted_object_id );

								}

						} }

					} }

			}

				// ノートブックのチェック
					public static function wpes_is_evernote_notebook_on_for_post_type( $notebook_name, $post_type_notebook_on, $post_type_notebook_name ) {

						if( ! in_array( $notebook_name, $post_type_notebook_name ) ) {

							return false;
						
						} else {

							$post_type = array_search( $notebook_name, $post_type_notebook_name );
							if( ! in_array( $post_type, $post_type_notebook_on ) ) {

								return false;

							}

						}

						return $post_type;

					}

				// タグのチェック
					public static function wpes_get_evernote_tags( $developerToken, $noteStore, $note ) {

						$tagGuids = $note->tagGuids;
						$tags = array();

						if( is_array( $tagGuids ) ) { 

							foreach( $tagGuids as $index => $tagGuid ) {

								$tags[ $index ] = $noteStore->getTag( $developerToken, $tagGuid )->name;

							} 

							return $tags;

						}

						return false;

					}

				// 更新すべきかチェック
					public static function wpes_will_update_page( $note, $posts ) {

						foreach( $posts as $index => $post ) {

							$evernote_guid = get_post_meta( $post->ID, '_wpes_evernote_guid', true );

							//echo '<p class="wpes-message-info">' . $evernote_guid . ' : Saved Evernote Guid<br>' . $note->guid . ' : Evernote Guid</p>';

							// "_wpes_evernote_guid"がセットされていない場合はスキップ
								if( empty( $evernote_guid ) )
									continue;

							// 一度取得済みかどうか
								if( $evernote_guid === $note->guid ) {

									//echo '<p class="wpes-message-info">' . intval( get_post_meta( $post->ID, '_wpes_evernote_last_updated', true ) ) . ' : Saved Evernote Updated<br>' . $note->updated . ' : Evernote Updated</p>';

									//echo '<p>' . get_post_meta( $post->ID, '_wpes_evernote_last_updated', true );
									//echo ' : ';
									//echo $note->updated / 1000 . '<br></p>';


									// 更新日のチェック
										if( get_post_meta( $post->ID, '_wpes_evernote_last_updated', true ) < ( $note->updated / 1000 ) ) {

											return $post;

										} else {

											return false;

										}

								}

						} 

						return true;

					}

				// 各ノートの処理
					public static function wpes_exec_evernote_update( $developerToken, $author_id, $noteStore, $note, $post_type, $post_status, $will_update_page ) {

						if( $will_update_page === true ) {
							printf( '<p class="wpes-message-notice">' . "\t" . __( 'Inserting Note "%1$s" in the status "%2$s"', 'wp-evernote-synchronizer' ) . '</p>', $note->title, ( $post_status === 'draft' ? __( 'Draft', 'wp-evernote-synchronizer' ) : __( 'Publish', 'wp-evernote-synchronizer' ) ) );
						} else {
							printf( '<p class="wpes-message-notice">' . "\t" . __( 'Updating Note "%1$s" in the saved status.', 'wp-evernote-synchronizer' ) . '</p>', $note->title );
						}

						#
						# アップロード用ディレクトリ
						#
							$current = time();
							$month = date( "m", $current );
							$year = date( "Y", $current );
							$upload_directory = wp_upload_dir();
							$baseurl = $upload_directory[ 'baseurl' ] . '/' . $year . '/' . $month . '/';
							$basedir = $upload_directory[ 'basedir' ] . '/' . $year . '/' . $month . '/';

						#
						# リソース
						#
							$recieved_data = self::wpes_exec_images_from_evernote( $author_id, $note->resources, $baseurl, $basedir, $will_update_page );
							$upload_image_args = $recieved_data[ 'upload_image_args' ];
							$note_resource_images = $recieved_data[ 'note_resource_images' ];
							unset( $recieved_data, $current, $month, $year, $upload_directory, $baseurl, $basedir );

							//print_r( $upload_image_args ); // チェック用
							//print_r( $note_resource_images ); //チェック用

						#
						# ページの処理
						#
							if( $will_update_page === true ) {

								// コンテンツの編集
									$note_content = self::wpes_get_modified_page_contents( $note, $note_resource_images );
								// ページの挿入
									$inserted_object_id = self::wpes_exec_insert_pages( $author_id, $note, $note_content, $upload_image_args, $post_type, $post_status );

							} else {

								// コンテンツの編集
									$note_content = self::wpes_get_modified_page_contents( $note, $note_resource_images );
								// ページの挿入
									$inserted_object_id = self::wpes_exec_update_pages( $author_id, $note, $note_content, $upload_image_args, $post_type, $will_update_page->post_status, $will_update_page );

							}

						// ID
							return $inserted_object_id;

					}

						// イメージの処理
							public static function wpes_exec_images_from_evernote( $author_id, $resources, $baseurl, $basedir, $will_update_page ) {

								$upload_image_args = array();
								$note_resource_images = array();

								$_wpes_evernote_uploaded_images = false;
								if( is_object( $will_update_page ) ) {
									$_wpes_evernote_uploaded_images = get_post_meta( $will_update_page->ID, '_wpes_evernote_uploaded_images', true );
								} //print_r( $_wpes_evernote_uploaded_images );

								if( ! $_wpes_evernote_uploaded_images 
									|| ! isset( $_wpes_evernote_uploaded_images[ 0 ] )
								) {

									//echo 'no-img';

									if( is_array( $resources ) ) { foreach( $resources as $index => $resource ) {

										//echo 'upload for new';

										$upload_image_args[ $index ] = self::wpes_upload_images_from_evernote( $author_id, $resource, $baseurl, $basedir, $will_update_page );
										if( $upload_image_args !== false ) {
											$hash = $upload_image_args[ $index ][ 'hash' ];
											$note_resource_images[ $hash ] = $upload_image_args[ $index ][ 'url' ];
										} else {
											continue;
										}

									} }

								} else {

									$upload_image_args = $_wpes_evernote_uploaded_images;

									// 既得リソース
										if( $upload_image_args === false ) {
											$note_resource_images = false;
										} elseif( is_array( $upload_image_args )  ) { foreach( $upload_image_args as $index => $data ) {
											$hash = $upload_image_args[ $index ][ 'hash' ];
											$note_resource_images[ $hash ] = $upload_image_args[ $index ][ 'url' ];
										} }

									// 未取得のリソース
										if( is_array( $resources ) ) { foreach( $resources as $index => $resource ) {

											$image_guid_arr = array();
											// 既得イメージのGuid配列を取得
											if( is_array( $upload_image_args ) ) { foreach( $upload_image_args as $index => $image_args ) {

												$image_guid_arr[] = $image_args[ 'guid' ];

												// イメージのGuid配列に該当しない場合はアップロードして取得
												if( in_array( ! $resource->guid, $image_guid_arr ) ) {

													// 新しくイメージをアップロード
														$new_upload_image_args = self::wpes_upload_images_from_evernote( $author_id, $resource, $baseurl, $basedir, $will_update_page );
													// コンテンツ編集用を追加
														$hash = $new_upload_image_args[ 'hash' ];
														$note_resource_images[ $hash ] = $new_upload_image_args[ 'url' ];
													// イメージの配列を追加
														$upload_image_args[] = $new_upload_image_args;

												}

											} }

										} }

								}

								return array(
									'upload_image_args' => $upload_image_args,
									'note_resource_images' => $note_resource_images
								);

							}

								// イメージの取得とアップロード
									public static function wpes_upload_images_from_evernote( $author_id, $resource, $baseurl, $basedir, $will_update_page ) {

										// 後で修正
										mkds( $basedir );

										#
										# リソースのデータ
										#
											$resource_guid = $resource->guid;
											$resource_data = $resource->data; // Obj
											$resource_mime = $resource->mime;
											$resource_width = $resource->width;
											$resource_height = $resource->height;
											$resource_recognition = $resource->recognition; // Obj
											$resource_attributes = $resource->attributes; // Obj

											$resource_data_body = $resource_data->body;

											$fromBodyHash = unpack( "H*" , $resource->data->bodyHash );
											$hash = strtolower( $fromBodyHash[ 1 ] );

											$file_name = $hash . '-' . $resource_attributes->fileName;
											$file_path = $basedir . $file_name;
											$file_url = $baseurl . $file_name;

											//print_r( $resource ); //チェック用

										// イメージかどうかチェック
										if( strpos( $resource_mime, 'image' ) === false )
											return false;

										// ファイルが存在しない場合
										if( ! file_exists( $file_path ) ) {

											$wp_upload_dir = wp_upload_dir();

											$result = file_put_contents( $file_path, $resource_data_body, LOCK_EX );

											$wp_filetype = wp_check_filetype( $file_path, null );
											$attachment = array(
												'guid' => $file_url,
												'post_mime_type' => $wp_filetype[ 'type' ],
												'post_title' => preg_replace( '/\.[^.]*$/', '', $file_name ),
												'post_content' => '',
												'post_status' => 'inherit',
												'post_author' => $author_id
											);

											$attachment_id = wp_insert_attachment( $attachment, $file_path, 0 );
											$attachment_metadata = wp_generate_attachment_metadata( $attachment_id, $file_path );
											wp_update_attachment_metadata( $attachment_id, $attachment_metadata );

											return array(
												'guid' => $resource_guid,
												'attachment_id' => $attachment_id,
												'hash' => $hash,
												'url' => $baseurl . $file_name,
												'size' => $resource_recognition->size,
											);

										} 

										$attachments = get_posts( array( 
											'post_type' => 'attachment',
										) );
										if( is_array( $attachments ) ) { foreach( $attachments as $index => $attachment ) {

											if( $attachment->guid !== $file_url ) {
												continue;
											}
											return array(
												'guid' => $resource_guid,
												'attachment_id' => $attachment->ID,
												'hash' => $hash,
												'url' => $baseurl . $file_name,
												'size' => $resource_recognition->size,
											);

										} } 

										return false;

									}

						// ページの編集
							public static function wpes_get_modified_page_contents( $note, $note_resource_images ) {

								// タイトルとコンテンツの取得
									$note_content = $note->content;

									//echo htmlentities( $note_content ) . '<br><br>'; //チェック用

								// ラッパー削除
									$note_content = preg_replace( '/<\?xml[^>]*?>/ims', '', $note_content );
									$note_content = preg_replace( '/<!DOCTYPE[^>]*?>/ims', '', $note_content );
									$note_content = preg_replace( '/<\/?en-note[^>]*>/ims', '', $note_content );

								// タグ整理
									$note_content = preg_replace( '/<del[^>]*?>[\s\S]*?<\/del>/ims', '', $note_content );
									$note_content = preg_replace( '/<center[^>]*?display[^>]*?>[\s\S]*?<\/center>/ims', '', $note_content );
									//$note_content = preg_replace( '/<\/?div[^>]*>/ims', '', $note_content ); //チェック用
									//$note_content = preg_replace( '/ style\s*=\s*[\'"][^\'"]*?[\'"]/ims', '', $note_content ); //チェック用

									//echo $note_title . '<br>'; //チェック用
									//echo htmlentities( $note_content ) . '<br><br>'; //チェック用
								// イメージの代入
									if( is_array( $note_resource_images ) ) { foreach( $note_resource_images as $hash => $url ) {

										$note_content = preg_replace(  
											'/<en\-media[^>]+(hash\s*=\s*[\'"]' . $hash . '[\'"])[^>]*><\/en\-media>/ims', 
											'<img class="en-media" src="' . $url .  '">',
											$note_content
										);

									} }

									$note_content = preg_replace( '/<en\-media[^>]+><\/en\-media>/ims', '', $note_content );

								//echo $note_title . '<br>'; //チェック用
								//echo htmlentities( $note_content ) . '<br><br>'; //チェック用

								return $note_content;

							}

						// ページの追加
							public static function wpes_exec_insert_pages( $author_id, $note, $note_content, $upload_image_args, $post_type = 'wpes-evernote', $post_status = 'draft' ) {

								//print_r( $note ); //チェック用

								$note_guid = $note->guid;
								$note_created = $note->created / 1000;
								$note_updated = $note->updated / 1000;

								$post_arr = array(
									'post_type' => $post_type,
									'post_title' => $note->title,
									'post_content' => $note_content,
									'post_status' => $post_status,
									'post_author' => $author_id,
									'meta_input' => array(
										'_wpes_evernote_guid' => $note_guid,
										'_wpes_evernote_created' => $note_created,
										'_wpes_evernote_last_updated' => $note_updated,
										'_wpes_evernote_uploaded_images' => $upload_image_args
									)
								);
								//print_r( $post_arr ); //チェック用

								// ページを追加
									$post_id = wp_insert_post( $post_arr );

								// メタデータを更新
									/*if( $post_id != '' ) {
										update_post_meta( $post_id, '_wpes_evernote_guid', $note_guid );
										update_post_meta( $post_id, '_wpes_evernote_created', $note->created );
										update_post_meta( $post_id, '_wpes_evernote_last_updated', $note->updated );
										update_post_meta( $post_id, '_wpes_evernote_uploaded_images', $upload_image_args );
									}*/

								// サムネイルの更新
									$attachment_id = null;
									if( isset( $upload_image_args[ 0 ][ 'attachment_id' ] ) )
										$attachment_id = $upload_image_args[ 0 ][ 'attachment_id' ];
									set_post_thumbnail( $post_id, $attachment_id );

								return $post_id;

							}

						// ページの更新
							public static function wpes_exec_update_pages( $author_id, $note, $note_content, $upload_image_args, $post_type = 'wpes-evernote', $post_status = 'draft', $post ) {

								//print_r( $note ); //チェック用

								$note_guid = $note->guid;
								$note_created = $note->created / 1000;
								$note_updated = $note->updated / 1000;
								echo is_int( $note_udpated ) ? 'true' : 'false';
								settype( $note_updated, "integer" );
								echo is_int( $note_udpated ) ? 'true' : 'false';

								$post_arr = array(
									'ID' => $post->ID,
									'post_type' => $post_type,
									'post_title' => $note->title,
									'post_content' => $note_content,
									//'post_status' => $post_status,
									'post_author' => $author_id,
									'meta_input' => array(
										'_wpes_evernote_guid' => $note_guid,
										'_wpes_evernote_created' => $note_created,
										'_wpes_evernote_last_updated' => $note_updated,
										'_wpes_evernote_uploaded_images' => $upload_image_args
									)
								);

								//print_r( $post_arr ); //チェック用

								// ページの更新
									$post_id = wp_update_post( $post_arr );

								// ページのメタデータの更新
									/*if( $post_id != '' ) {
										update_post_meta( $post_id, '_wpes_evernote_guid', $note_guid );
										update_post_meta( $post_id, '_wpes_evernote_created', $note->created );
										update_post_meta( $post_id, '_wpes_evernote_last_updated', $note->updated );
										update_post_meta( $post_id, '_wpes_evernote_uploaded_images', $upload_image_args );
									}*/

								// サムネイルの更新
									$attachment_id = null;
									//if( isset( $upload_image_args[ 0 ][ 'url' ] ) )

										//$attachment_id = $upload_image_args[ 0 ][ 'url' ];

									if( $attachment_id !== null 
										&& post_type_supports( $post_type, 'thumbnail' )
									) {
										set_post_thumbnail( $post->ID, $attachment_id );
									}

								return $post_id;

							}

				// ターム
					public static function wpes_exec_evernote_tags_update( $object_id ) {

						#
						# タームの更新
						#
							//self::$notebook_name;
							//print_r( self::$evernote_tags );
							//print_r( self::$object_taxonomies );
							//print_r( self::$object_terms );
							$registered_terms = array();
							$registered_terms_for_display = array();
							// タクソノミー
								foreach( self::$object_terms as $taxonomy_name => $taxonomy_terms ) {

									// オブジェクトに登録されているタームの取得
										$registered_terms[ $taxonomy_name ] = array();
										$registered_terms_for_display[ $taxonomy_name ] = array();
										$temp_terms = get_the_terms( $object_id, $taxonomy_name );
										foreach( $temp_terms as $index => $term_object ) {

											array_push( $registered_terms[ $taxonomy_name ], $term_object->term_id );
											array_push( $registered_terms_for_display[ $taxonomy_name ], $term_object->name );

										} unset( $temp_terms );

									// ノートのタグがある場合
										if( is_array( self::$evernote_tags ) && ! empty( self::$evernote_tags ) ) {
											foreach( self::$evernote_tags as $index => $evernote_tag ) {

												// 既存のターム
													$existing_term_ids = term_exists( $evernote_tag, $taxonomy_name );
													if( $existing_term_ids ) {

														// ページに登録されているターム
															$term_exists = false;
															foreach( $registered_terms_for_display[ $taxonomy_name ] as $key => $name ) {
																if( $name === $evernote_tag ) {
																	$term_exists = true;
																}
															}
															if( $term_exists ) {
																unset( self::$evernote_tags[ $index ] );
																continue;
															}

														// 新規追加など、登録されているタグを追加
															array_push( $registered_terms[ $taxonomy_name ], $evernote_tag );
															array_push( $registered_terms_for_display[ $taxonomy_name ], $evernote_tag );
															unset( self::$evernote_tags[ $index ] );

													}

											}
										} 

									// タームをセット
										$completed_terms = wp_set_object_terms( $object_id, $registered_terms[ $taxonomy_name ], $taxonomy_name, false );

									// メッセージ
										if( ! empty( $registered_terms_for_display[ $taxonomy_name ] ) ) {

											$taxonomy_object = get_taxonomy( $taxonomy_name );
											$taxonomy_displayed_name = $taxonomy_object->label;

											echo '<p>';
												echo "\t\t";
												printf( __( 'Saved the tags below as terms of %s', 'wp-evernote-synchronizer' ), $taxonomy_displayed_name );
											echo '</p>';

											echo '<p>';
												echo "\t\t\t";
												echo implode( ', ', $registered_terms_for_display[ $taxonomy_name ] ) . '<br>';
											echo '</p>';

										}

								}

							// 余ったタグを最後のタクソノミーにセット
								if( ! empty( self::$evernote_tags ) && ! empty( $taxonomy_name ) ) {

									$completed_terms = wp_set_object_terms( $object_id, self::$evernote_tags, $taxonomy_name, false );
									echo '<p>';
										echo "\t\t";
										printf( __( 'Saved the tags below as terms of %s because they are non-registered terms', 'wp-evernote-synchronizer' ), $taxonomy_name );
									echo '</p>';

									echo '<p>';
										echo "\t\t\t";
										echo implode( ', ', self::$evernote_tags ) . '<br>';
									echo '</p>';

								}

					}

		#
		# Data
		#
			public static function wpes_get_registered_data_for_json() {

				// グローバル
					global $_wp_post_type_features;

					self::$registered_object_data;

				// 各投稿タイプ
					$post_types = get_post_types( array(
						array( 
							//'show_ui' => true, 
							'show_in_menu' => true 
						), 
						'objects' 
					) );
					foreach( self::$post_types as $index => $post_type ) {

						// セットアップ
							// 投稿タイプ名
								$post_type_name = $post_type->name;
								if( $post_type_name == 'product' ) continue;

							// コンテンツエディターがサポートされていない場合はスルー
								if( ! isset( $_wp_post_type_features[ $post_type_name ][ 'editor' ] ) 
									|| ! $_wp_post_type_features[ $post_type_name ][ 'editor' ] 
								) continue;

						// Get the Post Type Data
							self::$registered_object_data[ $post_type_name ] = self::wpes_get_the_post_type_objects( $post_type_name );

					}

				// リターン
					return self::$registered_object_data;

			}

				public static function wpes_get_the_post_type_objects( $post_type ) {

					if( ! empty( $_REQUEST[ 'registerd_post_type' ] ) ) $post_type = $_REQUEST[ 'registerd_post_type' ];

					// Holder
						$post_type_data = array();

					// Pages
						$post_type_data[ 'pages' ] = get_posts( array(
							'post_type' => $post_type,
							'post_status' => array( 'draft', 'publish' ),
							'posts_per_page' => -1
						) );

					// Taxonomies
						// Holder
							$post_type_data[ 'taxonomies' ] = array();
						// Registered Data
							$taxonomies = get_object_taxonomies( $post_type );
							if( ! empty( $taxonomies ) ) {
								foreach( $taxonomies as $index => $taxonomy_name ) {

									// Holder
										$post_type_data[ 'taxonomies' ][ $taxonomy_name ] = array();

									// Array ( ID => Name )
										$post_type_data[ 'taxonomies' ][ $taxonomy_name ][ 'ids-names' ] = get_terms( array(
											'taxonomy' => $taxonomy_name,
											'fields' => 'id=>name',
											'hide_empty' => false
										) );
									// Objects
										$post_type_data[ 'taxonomies' ][ $taxonomy_name ][ 'objects' ] = get_terms( array(
											'taxonomy' => $taxonomy_name,
											'hide_empty' => false
										) );

								}
							}

					// Return
						return $post_type_data;

				}

	}

	if( ! function_exists( 'wpes_mkds' ) ) {
		function wpes_mkds( $directory ) {

			if( ! is_dir( $directory ) ) {

				if( ! wpes_mkds( dirname( $directory ) ) )
					return false;

				if( ! mkdir( $directory, 0755 ) )
					return false;

			}
			
			return true;

		}
	}

	// Hooks
		add_filter( 'cron_schedules', 'wpes_add_cron_interval_for_en_synchronizer' );
		add_action( 'wpes_evernote_update', 'wpes_evernote_update_func' );
		if( is_admin() ) {
			add_action( 'wp_ajax_wpes_evernote_update_func', 'wpes_evernote_update_func' );
			add_action( 'wp_ajax_wpes_user_evernote_update', 'wpes_user_evernote_update' );
		}
		register_activation_hook( __FILE__, 'wpes_evernote_update_schedule' );
		register_deactivation_hook( __FILE__, 'wpes_evernote_schedule_deactivation' );

	// テスト

	// 同期インターバル
		function wpes_add_cron_interval_for_en_synchronizer( $schedules ) {

			$en_update_interval = absint( get_option( 'wpes_evernote_update_action_interval', 30 ) );

			$en_update_interval = $en_update_interval * 60;

			$schedules[ 'en_update_interval' ] = array(
				'interval' => $en_update_interval,
				'display'  => __( 'Interval of Evernote Update Action in Seconds', 'wp-evernote-synchronizer' )
			);

			return $schedules;

		}

	// スケジュール
		function wpes_evernote_update_schedule() {

			$wpes_evernote_update_suspend = get_option( 'wpes_evernote_update_suspend', 'wpes_evernote_update_suspend' );

			if( 'wpes_evernote_update_suspend' !== $wpes_evernote_update_suspend ) {

				wp_schedule_event( time(), 'en_update_interval', 'wpes_evernote_update' );

			}

		}

	// アップデート
		function wpes_evernote_update_func() {

			if( ! isset( $_REQUEST[ 'wpes_evernote_update_func' ] ) ) {
				$wpes_evernote_update_suspend = get_option( 'wpes_evernote_update_suspend', 'wpes_evernote_update_suspend' );
				if( ! empty( $wpes_evernote_update_suspend ) ) 
					return;
			}

			update_option( 'wpes_update_time', date( "Y-m-d H:i:s T" ) );

			// 共通の同期処理
				wpes_common_evernote_update();

			// ユーザー毎の処理
				wpes_users_evernote_update();

		}

			function wpes_common_evernote_update() {

				// 共通の同期処理
					$developerToken = get_option( 'wpes_evernote_common_token', '' );

					if( $developerToken == '' ) {
						echo '<p class="wpes-message-notice">' . __( 'Evernote Token is not set for the Common Account.', 'wp-evernote-synchronizer' ) . '</p>';
						return;
					}

					echo '<h4>' . __( 'Importing from Common Account.', 'wp-evernote-synchronizer' ) . '</h4>';

					$author_id = 1;

				// 投稿ステータス
					$post_status = get_option( 'wpes_evernote_udpated_status', 'draft' );
				// 投稿タイプのノートブックの名前
					$post_type_notebook_on = get_option( 'wpes_evernote_notebook_on', array() );
					$post_type_notebook_name = get_option( 'wpes_evernote_notebook_key', array() );
				// 除外タグ
					$wpes_evernote_exclude_tags = preg_split( '/,\s*/', get_option( 'wpes_evernote_exclude_tags', '' ) );

				WP_Evernote_Synchronizer::wpes_update_evernote_notes( 
					$developerToken,
					$author_id,
					$post_status,
					$post_type_notebook_on, 
					$post_type_notebook_name, 
					$wpes_evernote_exclude_tags 
				);

			}

			function wpes_users_evernote_update() {

				$users = get_users();
				foreach( $users as $index => $user ) {

					// インポート時の投稿ステータスによるユーザーの権限チェック
						if( get_option( 'wpes_evernote_udpated_status', 'draft' ) === 'publish'
							&& ! ( isset( $user->allcaps[ 'publish_posts' ] ) && $user->allcaps[ 'publish_posts' ] ) 
						) {
							continue;
						}

					// ユーザーID
						$user_id = $user->ID;
					
					// トークンの有無をチェック
						$wpes_user_evernote_token = get_user_meta( $user_id, 'wpes_user_evernote_token', true );
						if( $wpes_user_evernote_token == '' ) {
							printf( '<p class="wpes-message-notice">' . __( 'Evernote Token is not set for User ID "%s"', 'wp-evernote-synchronizer' ) . '</p>', $user_id );
							continue;
						}

					// トークンの重複チェック
						$tokens = get_option( 'wpes_common_and_user_tokens', get_option( 'wpes_evernote_common_token', '' ) );
						$tokens_arr = preg_split( '/,\s*/', $tokens );
						if( ! is_array( $tokens_arr ) ) { $tokens_arr = array( $tokens ); }
						if( in_array( $wpes_user_evernote_token, $tokens_arr ) ) {
							printf( '<p class="wpes-message-notice">' . __( 'Evernote Token for User ID "%s" is a Duplicate of others.', 'wp-evernote-synchronizer' ) . '</p>', $user_id );
							continue;
						}
						$tokens .= ',' . $wpes_user_evernote_token;
						update_option( 'wpes_common_and_user_tokens', $tokens );

					// 各ユーザーの処理
						wpes_user_evernote_update( $user_id );

				}

			}

				function wpes_user_evernote_update( $user_id = 0 ) {

					if( isset( $_REQUEST[ 'wpes_user_id' ] ) ) { $user_id = $_REQUEST[ 'wpes_user_id' ]; }

					if( $user_id === 0 ) return;

					if( isset( $_REQUEST[ 'wpes_user_token' ] ) ) { 
						$wpes_user_evernote_token = $_REQUEST[ 'wpes_user_token' ]; 
					} else {
						$wpes_user_evernote_token = get_user_meta( $user_id, 'wpes_user_evernote_token', true );
					}


					if( $wpes_user_evernote_token == '' ) {
						sprintf( '<p class="wpes-message-notice">' . __( 'Evernote Token is not set for User ID "%s"', 'wp-evernote-synchronizer' ) . '</p>', $user_id );
						return;
					} 

					printf( '<h4>' . __( 'Importing from User Account. ID : "%d"' ) . '</h4>', $user_id );

					// 投稿ステータス
						$post_status = get_option( 'wpes_evernote_udpated_status', 'draft' );
					// 投稿タイプのノートブックの名前
						$post_type_notebook_on = get_option( 'wpes_evernote_notebook_on', array() );
						$post_type_notebook_name = get_option( 'wpes_evernote_notebook_key', array() );
					// 除外タグ
						$wpes_evernote_exclude_tags = preg_split( '/,\s*/', get_option( 'wpes_evernote_exclude_tags', '' ) );

					WP_Evernote_Synchronizer::wpes_update_evernote_notes( 
						$wpes_user_evernote_token,
						$user_id,
						$post_status,
						$post_type_notebook_on, 
						$post_type_notebook_name, 
						$wpes_evernote_exclude_tags 
					);

				}

	// 解除用
		function wpes_evernote_schedule_deactivation() {

			update_option( 'wpes_evernote_update_suspend', 'wpes_evernote_update_suspend' );
			$timestamp = wp_next_scheduled( 'wpes_evernote_update' );
			wp_unschedule_event( $timestamp, 'wpes_evernote_update' );
			wp_clear_scheduled_hook( 'wpes_evernote_update' );

		}


	#
	# サニタイズ
	#
		// 文字列のみの配列
			function wpes_sanitize_array_of_text( $array ) {

				foreach( $array as $index => $value ) {
					$array[ $index ] = sanitize_text_field( $value );
				}

				return $array;

			}

			function wpes_sanitize_interval( $interval ) {
				
				$interval = absint( $interval );

				if( $interval < 10 ) 
					$interval = 10;

				return $interval;

			}

	// テキストドメイン
		load_plugin_textdomain(
			'wp-evernote-synchronizer',
			false,
			dirname( plugin_basename( __FILE__ ) ) . '/languages'
		);

	// 初期化
		$WP_Evernote_Synchronizer = new WP_Evernote_Synchronizer();

}





?>