<?php
/**
 * Cron: Escrows
 *
 * @package Digital Coins Exchanging Store
 * @since 1.0
 */

//add_action( 'template_redirect', 'dce_cron_test' );
/**
 * Cron test
 */
function dce_cron_test()
{
	do_action( 'dce_cron_interval' );
}

add_action( 'dce_cron_interval', 'dce_cron_escrows_transactions_check' );
/**
 * Check open escrow transactions
 */
function dce_cron_escrows_transactions_check()
{
	global $wpdb;

	// open up execution time
	set_time_limit( 300 );

	// query open escrows
	$open_escrows = DCE_Escrow::query_escrows( array( 'post_status' => array( 'publish', 'in_progress' ) ) );

	$len = count( $open_escrows );
	if ( !$len )
		return;

	// set mail content
	add_filter( 'wp_mail_content_type', 'dce_set_mail_html_content_type' );

	// system settings
	$settings = dce_admin_get_settings();
	$settings['commission'] = floatval( $settings['commission'] );
	$settings['escrow_expire'] = intval( $settings['escrow_expire'] );

	$coin_types = dce_get_coin_types();

	// escrows loop
	for ( $i = 0; $i < $len; $i++ )
	{
		/* @var $escrow DCE_Escrow */
		$escrow =& $open_escrows[$i];

		// marked as failure ?
		if ( 'yes' == $escrow->is_failure )
			continue;

		// check from coin
		$from_rpc_client =& dce_coins_rpc_connections( $escrow->from_coin );
		$from_amount_received = $from_rpc_client->getreceivedbyaddress( $escrow->owner_address, $coin_types[$escrow->from_coin]['min_confirms'] );
		if ( is_wp_error( $from_amount_received ) )
			continue;

		// check to coin
		$to_rpc_client =& dce_coins_rpc_connections( $escrow->to_coin );
		$to_amount_received = $to_rpc_client->getreceivedbyaddress( $escrow->target_address, $coin_types[$escrow->to_coin]['min_confirms'] );
		if ( is_wp_error( $to_amount_received ) )
			continue;

		// cast values
		$from_amount_received = ( float ) $from_amount_received;
		$to_amount_received = ( float ) $to_amount_received;

		// insufficient amounts
		$insufficient_amounts = false;

		// save owner received amounts
		if ( $from_amount_received != 0 && $escrow->from_amount_received != $from_amount_received )
		{
			// save meta
			$escrow->set_meta( 'from_amount_received', $from_amount_received );

			// save transaction
			DCE_Transactions::save( array( 'amount' => DCE_Escrow::display_amount_formated( $from_amount_received, $escrow->from_coin, $coin_types ) ), 'received', $escrow->user->ID, $escrow->ID );

			// update status
			$escrow->change_status( 'in_progress' );

			// check required amount
			if ( $from_amount_received < $escrow->from_amount )
				$insufficient_amounts = true;
		}

		// save target received amounts
		if ( $to_amount_received != 0 && $escrow->to_amount_received != $to_amount_received )
		{
			// save meta
			$escrow->set_meta( 'to_amount_received', $to_amount_received );

			// save transaction
			DCE_Transactions::save( array( 'amount' => DCE_Escrow::display_amount_formated( $to_amount_received, $escrow->to_coin, $coin_types ) ), 'received', $escrow->target_user->ID, $escrow->ID );

			// update status
			$escrow->change_status( 'in_progress' );

			// check required amount
			if ( $to_amount_received < $escrow->to_amount )
				$insufficient_amounts = true;
		}

		// expires on
		$is_expired = current_time( 'timestamp' ) > strtotime( '+'. intval( $settings['escrow_expire'] ) .'days', strtotime( $escrow->datetime ) );

		// escrow expired or insufficient amounts
		if ( $is_expired || $insufficient_amounts )
		{
			// escrow failed 
			$escrow->set_meta( 'is_failure', 'yes' );

			// refund owner
			if ( $from_amount_received )
			{
				// try to refund
				$refund_txid = $from_rpc_client->sendtoaddress( $escrow->owner_refund_address, $from_amount_received );

				// append message
				$message = $settings['escrow_expire_amount_failure_mail'] .'<br/>';
				if ( is_wp_error( $refund_txid ) )
					$message .=  __( 'Contact site administrator to refund your coins.', 'dce' );
				else
					$message .=  __( 'Your coins have been refunded successfully.', 'dce' );

				// save transaction
				DCE_Transactions::save( array( 'amount' => DCE_Escrow::display_amount_formated( $from_amount_received, $escrow->from_coin, $coin_types ), 'txid' => $refund_txid ), 'refund', $escrow->user->ID, $escrow->ID );

				// notify failure
				wp_mail( $escrow->user->user_email,
						 __( 'Escrow Expiration Failure', 'dce' ), 
						sprintf( $message, esc_attr( $escrow->url() ), esc_attr( $escrow->feedback_url() ) ) 
				);
			}

			// refund target
			if ( $to_amount_received )
			{
				// try to refund
				$refund_txid = $from_rpc_client->sendtoaddress( $escrow->target_refund_address, $to_amount_received );

				// append message
				$message = $settings['escrow_expire_amount_failure_mail'] .'<br/>';
				if ( is_wp_error( $refund_txid ) )
					$message .=  __( 'Contact site administrator to refund your coins.', 'dce' );
				else
					$message .=  __( 'Your coins have been refunded successfully.', 'dce' );

				// save transaction
				DCE_Transactions::save( array( 'amount' => DCE_Escrow::display_amount_formated( $to_amount_received, $escrow->from_coin, $coin_types ), 'txid' => $refund_txid ), 'refund', $escrow->target_user->ID, $escrow->ID );

				// notify failure
				wp_mail( $escrow->target_user->user_email,
						 __( 'Escrow Expiration Failure', 'dce' ), 
						sprintf( $message, esc_attr( $escrow->url() ), esc_attr( $escrow->feedback_url() ) ) 
				);
			}

			// update status
			$escrow->change_status( 'failed' );

			// wp action
			do_action( 'dce_escrow_failed', $escrow );

			// skip to next escrow
			continue;
		}

		// which parties sent amounts right
		$all_received = 0;
		$to_notify = array();

		// owner sent right amount
		if ( $from_amount_received >= $escrow->from_amount )
		{
			if ( 'yes' != $escrow->target_notified )
			{
				// notify owner
				wp_mail( $escrow->user->data->user_email, 
						__( 'Escrow Notification', 'dce' ), 
						sprintf( $settings['escrow_user_sent_notify_mail'], $escrow->convert_from_display( $coin_types ), esc_attr( dce_get_pages( 'trans-history' )->url ) ) );

				// notify target
				wp_mail( $escrow->target_email, 
						__( 'Escrow Notification', 'dce' ), 
						sprintf( $settings['escrow_other_sent_notify_mail'], $escrow->user->display_name(), $escrow->url(), $escrow->convert_from_display( $coin_types ) ) );

				// check if coins more than required
				if ( $from_amount_received > $escrow->from_amount )
				{
					wp_mail( $escrow->user->data->user_email, 
							__( 'Escrow Notification', 'dce' ), 
							sprintf( $settings['escrow_coins_extra_notify_mail'], DCE_Escrow::display_amount_formated( $from_amount_received, $escrow->from_coin, $coin_types ), $escrow->convert_from_display( $coin_types ) ) );
				}

				// mark as notified
				$escrow->set_meta( 'target_notified', 'yes' );
			}

			$all_received++;
		}

		// target sent right amount
		if ( $to_amount_received >= $escrow->to_amount )
		{
			if ( 'yes' != $escrow->owner_notified )
			{
				// notify target
				wp_mail( $escrow->target_email,
						__( 'Escrow Notification', 'dce' ),
						sprintf( $settings['escrow_user_sent_notify_mail'], $escrow->convert_to_display( $coin_types ), esc_attr( dce_get_pages( 'trans-history' )->url ) ) );

				// notify owner
				wp_mail( $escrow->user->data->user_email, 
						__( 'Escrow Notification', 'dce' ), 
						sprintf( $settings['escrow_other_sent_notify_mail'], $escrow->target_user->display_name(), $escrow->url(), $escrow->convert_to_display( $coin_types ) ) );

				// check if coins more than required
				if ( $to_amount_received > $escrow->to_amount )
				{
					wp_mail( $escrow->target_email, 
							__( 'Escrow Notification', 'dce' ), 
							sprintf( $settings['escrow_coins_extra_notify_mail'], DCE_Escrow::display_amount_formated( $to_amount_received, $escrow->to_amount, $coin_types ), $escrow->convert_to_display( $coin_types ) ) );
				}

				// mark as notified
				$escrow->set_meta( 'owner_notified', 'yes' );
			}

			$all_received++;
		}

		// all amounts received
		if ( 2 == $all_received )
		{
			// check for receive addresses
			if ( empty( $escrow->owner_receive_address ) || empty( $escrow->target_receive_address ) )
				continue;

			// final amounts
			$amount_for_owner = $escrow->to_amount;
			$amount_for_target = $escrow->from_amount;

			// commission divider
			switch ( $escrow->comm_method )
			{
				// all on the owner
				case 'by_user':
					$amount_for_owner *= ( 100 - $settings['commission'] ) / 100;
					break;

				// all on the other paty
				case 'by_target':
					$amount_for_target *= ( 100 - $settings['commission'] ) / 100;
					break;

				// divided on both party equally
				case '50_50':
					// calculate 50% of the commission
					$half_commission = ( 100 - ( $settings['commission'] * 0.5 ) ) / 100;

					// cut from both amounts
					$amount_for_owner *= $half_commission;
					$amount_for_target *= $half_commission;
					break;
				default:
					continue;
			}

			// send amounts to parties
			$owner_txid = $to_rpc_client->sendtoaddress( $escrow->owner_receive_address, $amount_for_owner );
			$target_txid = $from_rpc_client->sendtoaddress( $escrow->target_receive_address, $amount_for_target );

			// save results
			$escrow->set_meta( 'owner_txid', $owner_txid );
			$escrow->set_meta( 'target_txid', $target_txid );

			// transactions status
			$owner_txid_failed = is_wp_error( $owner_txid );
			$target_txid_failed = is_wp_error( $target_txid );
			$amount_for_owner_display = DCE_Escrow::display_amount_formated( $amount_for_owner, $escrow->to_coin, $coin_types );
			$amount_for_target_display = DCE_Escrow::display_amount_formated( $amount_for_target, $escrow->from_coin, $coin_types );

			// save transactions
			DCE_Transactions::save( array( 'amount' => $amount_for_owner_display, 'error' => $owner_txid_failed ? $owner_txid : false ), 'sent', $escrow->user->ID, $escrow->ID, $owner_txid_failed ? 'error' : $owner_txid );
			DCE_Transactions::save( array( 'amount' => $amount_for_target_display, 'error' => $target_txid_failed ? $target_txid : false ), 'sent', $escrow->target_user->ID, $escrow->ID, $target_txid_failed ? 'error' : $target_txid );

			// notification mails
			if ( $owner_txid_failed || $target_txid_failed )
			{
				// notify owner
				if ( $owner_txid_failed )
				{
					wp_mail( $escrow->user->user_email, 
							__( 'Escrow Transaction Failure', 'dce' ), 
							sprintf( $settings['escrow_trans_failure_notify_mail'], esc_attr( $escrow->url() ), $escrow->target_user->display_name(), $amount_for_owner_display ) 
					);
				}

				// notify target
				if ( $target_txid_failed )
				{
					wp_mail( $escrow->target_user->user_email, 
							__( 'Escrow Transaction Failure', 'dce' ), 
							sprintf( $settings['escrow_trans_failure_notify_mail'], esc_attr( $escrow->url() ), $escrow->user->display_name(), $amount_for_target_display ) 
					);
				}
			}
			else
			{
				// both transactions successful

				// notify owner
				wp_mail( $escrow->user->user_email,
						__( 'Escrow Successfully Completed', 'dce' ),
						sprintf( $settings['escrow_success_notify_mail'], esc_attr( $escrow->url() ), $escrow->target_user->display_name(), $amount_for_owner_display, esc_attr( $escrow->feedback_url() ) )
				);

				// notify owner
				wp_mail( $escrow->target_user->user_email,
						__( 'Escrow Successfully Completed', 'dce' ),
						sprintf( $settings['escrow_success_notify_mail'], esc_attr( $escrow->url() ), $escrow->user->display_name(), $amount_for_target_display, esc_attr( $escrow->feedback_url() ) )
				);
			}

			// set as completed
			$escrow->change_status( 'completed' );

			// wp action
			do_action( 'dce_escrow_success', $escrow );
		}
	}

	// reset mail content type
	remove_filter( 'wp_mail_content_type', 'dce_set_mail_html_content_type' );
}
























