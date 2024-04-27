<?php
/**
 * NAIOP Tickets 2024-04-10
 *
 * @package     NAIOP Tickets
 * @author      Scott Dohei
 * 
 */

 add_filter('naiop_tickets_name', 'naiop_tickets_name');
 function naiop_tickets_name($object) {
	 return "NAIOP Tickets";
 }

/*add_filter( 'mt_add_to_cart_input', 'add_to_cart_input', 10, 8 );
function add_to_cart_input($html, $input_type, $type, $value, $attributes, $unknown, $remaining, $available) {
	if ( $type === 'complimentary' ) {
		return "";
	}
	return $html;
}*/

add_filter( 'naiop_ticket_row', 'filter_ticket_rows', 10, 2 );
function filter_ticket_rows($html, $type) {
	if ( $type === 'complimentary' ) {
		
		return false;
	}
	return $html;
}

/* don't append to cart page (we'll use WC) */
add_filter('naiop_add_to_cart_page', 'use_my_tickets_cart', 10, 1);
function use_my_tickets_cart($use_cart) {
	return false;
}

add_filter( 'naiop_add_to_cart_output', 'add_to_cart_output', 10, 3 );
function add_to_cart_output($html, $checkout_url, $event) {
	
	$event_product_id = ( is_object( $event ) ) ? $event->event_product : "";

	//$event_product_id = ( is_object( $event ) ) ? $event->event_product : "";
	
	//return $html;

	$event_id = ( is_object( $event ) ) ? $event->event_post : $event;
	$nonce   = wp_nonce_field( 'mt-cart-nonce', '_wpnonce', true, false );

	$form = "<div class='mt-order'><form class='ticket-orders'>";
	$form .= "<input type='hidden' name='my-tickets' value='true'>";
	$form .= $nonce;
	$form .= "<input type='hidden' name='mt_event_id' value='$event_id' />";
	$form .= "<input type='hidden' name='naiop_product_id' value='$event_product_id' />";
	$after = "</form></div>";
	return $form . "<button type='submit' name='mt_add_to_cart'>Register Now!<span class='mt-processing'><img src='http://localhost/wordpress/wp-admin/images/spinner-2x.gif' alt='Working'></span></button>" . $after;

	//return "<div class='wp-block-button'><a class='wp-block-button__link wp-element-button' href='" . $checkout_url ."'>Register Now!</a></div>";
}

function naiop_ajax_handler() {
	$options = mt_get_settings();
	// verify nonce.
	if ( ! check_ajax_referer( 'mt-cart-nonce', 'security', false ) ) {
		wp_send_json(
			array(
				'response' => __( 'Invalid security response.', 'my-tickets' ),
				'saved'    => false,
			)
		);
	}
	if ( 'naiop_add_to_cart' === $_REQUEST['function'] ) {
		parse_str( $_REQUEST['data'], $data );
		$data = map_deep( $data, 'sanitize_text_field' );
		// reformat request data to multidimensional array.
		$cart = mt_get_cart();
		foreach ( $data as $k => $d ) {
			if ( 'mt_tickets' === $k ) {
				foreach ( $d as $n => $value ) {
					if ( $cart ) {
						$data[ $k ][ $n ] = array(
							'count' => $value,
						);
					} else {
						$data[ $k ][ $n ] = $value;
					}
				}
			}
		}
		$submit = $data;

		$product_id = $submit['naiop_product_id'];
		if ( $product_id ) {
			WC()->cart->add_to_cart( $product_id );
		}

		$response = apply_filters( 'mt_ajax_updated_success', sprintf( __( "Your cart is updated. <a href='%s'>Checkout</a>", 'my-tickets' ), "" ) );
		$return = array(
			'response' => $response,
			'success'  => "1",
			'event_id' => $submit['mt_event_id'],
		);
		wp_send_json( $return );
	}
}
add_action( 'wp_ajax_mt_ajax_handler', 'naiop_ajax_handler' );
add_action( 'wp_ajax_nopriv_mt_ajax_handler', 'naiop_ajax_handler' );

