<?php
/**
 * Setups
 *
 * @package Digital Coins Exchanging Store
 * @since 1.0
 */

add_action( 'admin_init', 'dce_admin_init' );
/*
 * WP-Admin initialization
*/
function dce_admin_init()
{
	// restrict access to clients
	if ( !defined( 'DOING_AJAX' ) && in_array( DCE_CLIENT_ROLE, wp_get_current_user()->roles ) )
		dce_redirect( home_url() );

	// action actions ( setups )
	if ( current_user_can( 'manage_options' ) && isset( $_GET['dce_setup'] ) )
	{
		switch ( $_GET['dce_setup'] )
		{
			// setup pages
			case 'pages':
				// page attrs
				$page_attrs = array (
						'post_status' => 'publish',
						'post_type' => 'page',
						'comment_status' => 'closed',
						'ping_status' => 'closed',
				);

				$pages = dce_get_pages();
				foreach ( $pages as $page_name => &$page_info )
				{
					// check page existence
					$page = dce_get_page_by_slug( $page_name );
					if ( $page )
					{
						// check for shortcode
						if( '' != $page_info['content'] && false === strpos( $page->post_content, $page_info['content'] ) )
						{
							// update page content with the shortcode
							wp_update_post( array( 'ID' => $page->ID, 'post_content' => $page->post_content .'<br/>'. $page_info['content'] ) );
						}
					}
					else
					{
						// fill the attrs
						$page_attrs['post_name'] = $page_name;
						$page_attrs['post_title'] = $page_info['title'];
						$page_attrs['post_content'] = $page_info['content'];

						// insert the page
						$page_id = wp_insert_post( $page_attrs );
						if ( $page_id )
						{
							$page_info['id'] = $page_id;
							$page_info['url'] = get_permalink( $page_id );
						}
					}
				}

				// save changes
				update_option( 'dce_pages', $pages );

				break;
		}
	}
}

add_action( 'admin_notices', 'dce_admin_notices' );
/**
 * WP-Admin Notification messages
*/
function dce_admin_notices()
{
	// check pages
	$login_page = dce_get_pages( 'login' );
	if ( !get_permalink( $login_page['id'] ) )
		echo '<div class="error"><p>', sprintf( __( 'In order to <strong>Digital Coins Exchanging Store</strong> plugin to work it needs to setup the needed pages, <a class="button button-primary" href="%s">Click here to run setup</a>', 'dce' ), add_query_arg( 'dce_setup', 'pages' ) ) ,'</p></div>';
}

add_action( 'admin_enqueue_scripts', 'dce_admin_enqueue_scripts' );
/**
 * WP-Admin Scripts & Styles
*/
function dce_admin_enqueue_scripts( $current_page )
{
	/**
	 * Styles
	 */
	wp_enqueue_style( 'dce-shared-style' );
}












