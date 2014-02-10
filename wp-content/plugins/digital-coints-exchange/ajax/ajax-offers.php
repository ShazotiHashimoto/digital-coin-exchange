<?php
/**
 * Ajax: Offers
 * 
 * @package Digital Coins Exchanging Store
 * @since 1.0
 */

add_action( 'wp_ajax_dce_confirm_offer', 'dce_ajax_admin_offer_actions' );
add_action( 'wp_ajax_dce_deny_offer', 'dce_ajax_admin_offer_actions' );
/**
 * Confirm/Deny user's offer
 */
function dce_ajax_admin_offer_actions()
{
	if ( !current_user_can( 'manage_options' ) )
		dce_ajax_error( 'permission', __( 'You do not have permission to access here.', 'dce' ) );

	// offer ID
	$offer_id = (int) dce_get_value( 'offer' );
	if ( !$offer_id )
		dce_ajax_error( 'offer', __( 'Invalid offer ID', 'dce' ) );

	// target action/status
	$status = str_replace( array( 'dce_', '_offer' ), '', dce_get_value( 'action' ) );
	$status = 'confirm' == $status ? 'publish' : 'denied';

	// update post
	$offer_id = wp_update_post( array( 'ID' => $offer_id, 'post_status' => $status ), true );
	if ( is_wp_error( $offer_id ) )
		dce_ajax_error( $offer_id->get_error_code(), $offer_id->get_error_message() );

	// success
	dce_ajax_response( $status );
}

add_action( 'wp_ajax_cancel_offer', 'dce_ajax_cancel_offer' );
/**
 * Cancel offer
 */
function dce_ajax_cancel_offer()
{
	// offer ID
	$offer_id = (int) dce_get_value( 'offer' );
	if ( !$offer_id || !check_ajax_referer( 'dce_cancel_nonce_'. $offer_id, 'nonce', false ) )
		dce_ajax_error( 'offer', __( 'Unknown offer!!!', 'dce' ) );

	// check owner
	$post = get_post( $offer_id );
	if ( !$post || wp_get_current_user()->ID != $post->post_author )
		dce_ajax_error( 'permission', __( 'Unknown offer!!!', 'dce' ) );

	// cancel/delete offer
	wp_delete_post( $offer_id, true );

	// success
	dce_ajax_response( 'done' );
}

add_action( 'wp_ajax_create_offer', 'dce_ajax_create_offer' );
/**
 * Create new offer
 */
function dce_ajax_create_offer()
{
	check_ajax_referer( 'dce_save_offer', 'nonce' );

	// init data
	$coin_types = dce_get_coin_types();
	$form_fields = DCE_Offer::form_fields( $coin_types );

	// clear old errors
	DCE_Utiles::clear_form_errors();

	// validate form data
	foreach ( $form_fields as $field_name => &$field_args )
	{
		$field_args['value'] = dce_parse_input( $field_name, $field_args );
	}

	// error messages
	if ( DCE_Utiles::has_form_errors() )
	{
		$error_messages = '';
		$errors = DCE_Utiles::show_form_errors( false, true );

		foreach ( $errors as $error_message )
		{
			$error_messages .= dce_alert_message( $error_message, 'error' );
		}

		dce_ajax_error( 'form-errors', $error_messages );
	}

	// from value
	$from_amount = (int) dce_get_value( 'from_amount' );
	if ( !$from_amount )
		dce_ajax_error( 'from_amount', __( 'From Amount Invalid value', 'dce' ) );

	// to value
	$to_amount = (int) dce_get_value( 'to_amount' );
	if ( !$to_amount )
		dce_ajax_error( 'to_amount', __( 'To Amount Invalid value', 'dce' ) );

	// from coin type
	$from_coin = dce_get_value( 'from_coin' );
	if ( !isset( $coin_types[$from_coin] ) )
		dce_ajax_error( 'from_coin', __( 'From Coin Invalid value', 'dce' ) );

	// to coin type
	$to_coin = dce_get_value( 'to_coin' );
	if ( !isset( $coin_types[$from_coin] ) )
		dce_ajax_error( 'to_coin', __( 'To Coin Invalid value', 'dce' ) );

	// details
	$deal_details = dce_get_value( 'details' );

	// current user
	$user = DCE_User::get_current_user();

	// save offer
	$offer_id = $user->save_offer( $form_fields['from_amount']['value'], 
									$form_fields['from_coin']['value'], 
									$form_fields['to_amount']['value'], 
									$form_fields['to_coin']['value'], 
									array ( 
											'comm_method' => $form_fields['comm_method']['value'], 
											'details' => $form_fields['details']['value'] 
									) );
	if ( is_wp_error( $offer_id ) )
		dce_ajax_error( 'save', __( 'Error saving offer, please try again later', 'dce' ) );

	// success
	dce_ajax_response( $offer_id );
}





















