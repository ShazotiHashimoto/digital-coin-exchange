<?php
/**
 * User's offers
 *
 * @package Digital Coins Exchanging Store
 * @since 1.0
 */

/* @var $dce_user DCE_User */
global $dce_user;

// js & css
wp_enqueue_script( 'dce-offers', DCE_URL .'js/offers.js', array( 'dce-shared-script' ), false, true );

// shortcode output
$output = '';

// current view
$current_view = dce_get_value( 'view' );
if ( !in_array( $current_view, array( 'view_offers', 'create_offer' ) ) )
	$current_view = 'view_offers';

// views switch
switch ( $current_view )
{
	case 'view_offers':
		$output .= dce_section_title( __( 'Your Offers', 'dce' ) );

		// get user offers
		$offers = $dce_user->get_offers();

		// offers table start
		$output .= dce_table_start( 'user-offers' ) .'<thead><tr>';
		$output .= '<th>'. __( 'Original', 'dce' ) .'</th>';
		$output .= '<th>'. __( 'Target', 'dce' ) .'</th>';
		$output .= '<th>'. __( 'Date &amp; Time', 'dce' ) .'</th>';
		$output .= '<th>'. __( 'Status', 'dce' ) .'</th>';
		$output .= '<th>'. __( 'Actions', 'dce' ) .'</th>';
		$output .= '</tr></thead><tbody>';

		// content
		foreach ( $offers as $offer )
		{
			// data display
			$output .= '<tr><td>'. $offer['from_display'] .'</td>';
			$output .= '<td>'. $offer['to_display'] .'</td>';
			$output .= '<td>'. $offer['datetime'] .'</td>';
			$output .= '<td>'. $offer['status'] .'</td>';
			$output .= '<td><a href="#" class="button small red cancel-offer" ';
			$output .= 'data-action="cancel_offer" data-offer="'. $offer['ID'] .'" data-nonce="'. wp_create_nonce( 'dce_cancel_nonce_'. $offer['ID'] ) .'">';
			$output .= __( 'Cancel', 'offer' ) .'</a></td></tr>';

			// offer details
			if ( !empty( $offer['details'] ) )
				$output .= '<tr><td colspan="5"><strong>'. __( 'Offer Details', 'dce' ).':</strong> '. $offer['details'] .'</td></tr>';
		}

		// table end
		$output .= '</tbody>'. dce_table_end();

		// new offer link
		$output .= '<a href="'. add_query_arg( 'view', 'create_offer' ) .'" class="button small green">'. __( 'Create New Offer', 'dce' ) .'</a>';
		break;

	case 'create_offer':
		$coin_types = dce_get_coin_types();

		// title
		$output .= dce_section_title( __( 'Create new offer', 'dce' ) );

		// form start
		$output .= '<form action="" method="post" id="new-offer-form" class="ajax-form">';

		// exchange from amount
		$output .= dce_form_input( 'from_amount', array( 'label' => __( 'From Amount', 'dce' ), 'input' => 'text' ) );

		// exchange from coin type
		$output .= dce_form_input( 'from_coin', array( 'label' => __( 'From Coin', 'dce' ), 'input' => 'select', 'source' => $coin_types ) );

		// exchange to amount
		$output .= dce_form_input( 'to_amount', array( 'label' => __( 'To Amount', 'dce' ), 'input' => 'text' ) );

		// exchange to coin type
		$output .= dce_form_input( 'to_coin', array( 'label' => __( 'To Coin', 'dce' ), 'input' => 'select', 'source' => $coin_types ) );

		// exchange deal details
		$output .= dce_form_input( 'details', array( 'label' => __( 'Offer Details', 'dce' ), 'input' => 'textarea', 'cols' => 42, 'rows' => 8 ) );

		// hidden inputs
		$output .= '<input type="hidden" name="action" value="create_offer" />';
		$output .= wp_nonce_field( 'dce_save_offer', 'nonce', false, false );

		// submit
		$output .= '<p class="form-input"><input type="submit" value="'. __( 'Save', 'dce' ) .'" class="button small green" /></p>';

		// form end
		$output .= '</form>';
		break;
}

return $output;















