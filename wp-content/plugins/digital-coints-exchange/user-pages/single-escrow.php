<?php
/**
 * Single: Escrow
 *
 * @package Digital Coins Exchanging Store
 * @since 1.0
 */
/* @var $dce_user DCE_User */
global $dce_user;

// current escrow
$escrow = new DCE_Escrow( get_post() );
if ( !$escrow->exists() )
	return dce_alert_message( __( 'Unknown escrow', 'dce' ), 'error' );

// is the current logged in user is the owner/creator
$is_owner = strtolower( $dce_user->data->user_email ) != strtolower( $escrow->target_email );

// output holder
$output = '';

$coin_types = dce_get_coin_types();

// receive address
$receive_address = __( 'Your Receive Address', 'dce' ) .' : ';
$receive_address .= '<code>'. ( $is_owner ? $escrow->owner_address : $escrow->target_address ) .'</code>';

// display
$output .= dce_promotion_box( $receive_address );

// data table start
$output .= dce_table_start( 'single-escrow' );

// form fields for data display
$fields = DCE_Escrow::form_fields( $coin_types );

// convert from
$output .= '<tr><th>'. __( 'Other Party', 'dce' ) .'</th><td>'. ( $is_owner ? $escrow->target_email : $escrow->user->data->user_email ) .'</td></tr>';

// convert from
$output .= '<tr><th>'. __( 'Convert From', 'dce' ) .'</th><td>'. $escrow->convert_from_display( $coin_types ) .'</td></tr>';

// convert to
$output .= '<tr><th>'. __( 'Convert To', 'dce' ) .'</th><td>'. $escrow->convert_to_display( $coin_types ) .'</td></tr>';

// Commission payment
$output .= '<tr><th>'. $fields['comm_method']['label'] .'</th><td>'. $escrow->commission_method_display() .'</td></tr>';

// details
$output .= '<tr><th>'. $fields['details']['label'] .'</th><td>'. $escrow->details .'</td></tr>';

// table end
$output .= dce_table_end();

return $output;















