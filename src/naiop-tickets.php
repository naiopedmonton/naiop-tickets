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

add_filter('mt_settings', 'naiop_save_settings', 10, 2);
function naiop_save_settings($settings, $post) {
	if (isset($post['naiop_ticket_cat'])) {
		$naiop_ticket_cat = (int) $post['naiop_ticket_cat'];
		$settings['naiop_ticket_cat'] = $naiop_ticket_cat;
	}
	return $settings;
}

add_filter('mt_ticketing_settings_fields', 'naiop_ticketing_settings_fields', 10, 2);
function naiop_ticketing_settings_fields($html, $options) {
	$current_product_cat = ( is_numeric( $options['naiop_ticket_cat'] ) ) ? sprintf( __( 'Currently: %1$s (%2$s)', 'my-tickets' ), "<a href='" . get_the_permalink( $options['naiop_ticket_cat'] ) . "'>" . get_the_title( $options['naiop_ticket_cat'] ) . '</a>', get_the_category_by_ID( $options['naiop_ticket_cat'] ) ) : __( 'Not defined', 'my-tickets' );

	$input = '<p><input type="text" size="6" class="suggest" id="naiop_ticket_cat" name="naiop_ticket_cat" value="' . stripslashes( esc_attr( $options['naiop_ticket_cat'] ) ) . '" required aria-required="true" />';
	$input .= '<label for="naiop_ticket_cat"> ' . __( 'WC Product Category for Tickets', 'my-tickets' );
	$input .= ' <em class="current">' . wp_kses_post( $current_product_cat ) . '</em></label></p>';
	return $input;
}

/* add product id to the ticket row */
add_filter( 'naiop_custom_cart_inputs', 'add_to_cart_input', 10, 4 );
function add_to_cart_input($html, $type, $event_id, $registration) {
	if ( $type === 'complimentary' ) {
		return "";
	}

	$product_id = "";
	$seats = "1";
	if ( isset( $registration['prices'] ) && isset( $registration['prices'][$type] ) ) {
		if ( isset( $registration['prices'][$type]['product_id'] ) ) {
			$product_id = $registration['prices'][$type]['product_id'];
		}
		if ( isset( $registration['prices'][$type]['seats'] ) ) {
			$seats = $registration['prices'][$type]['seats'];
		}
	}
	
	$return = "<div class='naiop-ticket-field-custom'>";
		$return .= "<input type='hidden' name='naiop_product_id[$type]' id='naiop_product_id_$type" . '_' . "$event_id' value='$product_id' />";
		$return .= "<span class='naiop-ticket-seats'>Seats " . $seats . "</span>";
	$return .= "</div>";

	return $return;
}

add_filter( 'naiop_ticket_row', 'filter_ticket_rows', 10, 4 );
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

/* fix event ticket selling toggle */
add_filter('naiop_disable_sell', 'toggle_event_selling', 10, 1);
function toggle_event_selling($post_id) {
	update_post_meta( $post_id, '_mt_sell_tickets', 'false' );
}		

add_filter( 'mt_tickets_remaining_continuous_text', 'naiop_add_to_cart_remaining_notice', 10, 1 );
function naiop_add_to_cart_remaining_notice($notice) {
	return "%s seats remaining.";
}

add_filter( 'naiop_add_to_cart_output', 'add_to_cart_output', 10, 3 );
function add_to_cart_output($html, $checkout_url, $event) {
	
	return $html;

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
		if ( isset( $submit['mt_tickets'] ) && isset( $submit['naiop_product_id'] ) ) {
			foreach ($submit['mt_tickets'] as $ticket_id => $reg_info) {
				if ( !is_numeric($reg_info) || ((int)$reg_info) <= 0) {
					continue;
				}
				$product_id = "";
				if ( isset( $submit['naiop_product_id'][$ticket_id] ) && $submit['naiop_product_id'][$ticket_id] !== '' ) {
					$product_id = $submit['naiop_product_id'][$ticket_id];
				}
				if ( $product_id ) {
					WC()->cart->add_to_cart($product_id, $reg_info, 0, array(), array());
				} else {
					error_log("Failed to find product_id for ticket add-to-cart request: " . $ticket_id);
				}
			}
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


/* re-order (and filter?) available ticket models */
add_filter('naiop_ticket_models', 'naiop_ticket_models', 10, 1);
function naiop_ticket_models($models) {
	return array(
		'continuous' => __( 'Audience Types', 'my-tickets' ),
		'discrete'   => __( 'Seating Sections', 'my-tickets' ),
		'event'      => __( 'Event', 'my-tickets' ),
	);
}

/* add seats to ticket setup header */
add_filter('naiop_ticketing_header', 'naiop_ticketing_header', 10, 3);
function naiop_ticketing_header($header, $counting, $label) {
	$return    = "<table class='widefat mt-pricing mt-$counting'>
					<caption>" . __( 'Ticket Prices and Availability', 'my-tickets' ) . "</caption>
					<thead>
						<tr>
							<th scope='col'>" . __( 'Move', 'my-tickets' ) . "</th>
							<th scope='col' width='20%'>" . $label . "</th>
							<th scope='col'>" . __( 'Price', 'my-tickets' ) . "</th>
							<th scope='col'>" . __( 'Seats', 'my-tickets' ) . "</th>
							<th scope='col'>" . __( 'Available', 'my-tickets' ) . "</th>
							<th scope='col'>" . __( 'Sold', 'my-tickets' ) . "</th>
							<th scope='col'>" . __( 'Close Sales', 'my-tickets' ) . '</th>
						</tr>
					</thead>
					<tbody>';
	return $return;
}

/* custom fields on new price group */
add_filter('naiop_ticketing_new_price_custom_fields', 'naiop_ticketing_new_price_fields', 10, 2);
function naiop_ticketing_new_price_fields($pattern, $counting) {
	// insert hidden product_id
	$return = "<input type='hidden' name='naiop_product_id$pattern' id='naiop_product_id_" . $counting . "' value='' />";

	if ('discrete' === $counting || 'event' === $counting) {
		$return .= "<input type='hidden' name='naiop_seats$pattern' id='naiop_seats_" . $counting . "' value='inherit' />1";
		return $return;
	}
	$return .= "<input type='text' name='naiop_seats$pattern' id='naiop_seats_" . $counting . "' value='' size='8' />";
	return $return;
}

/* custom fields for existing price group */
add_filter('naiop_ticket_custom_fields', 'naiop_ticket_custom_fields', 10, 4);
function naiop_ticket_custom_fields($pattern, $counting, $label, $options) {
	$pid = isset($options['product_id']) ? $options['product_id'] : "";
	$return = "<input type='hidden' name='naiop_product_id$pattern' id='naiop_product_id_$counting" . '_' . "$label' value='" . esc_attr($pid) . "' />";

	if ('discrete' === $counting || 'event' === $counting) {
		$return .= "<input type='hidden' name='naiop_seats$pattern' step='1' id='naiop_seats_$counting" . '_' . "$label' value='1' size='4' />1";
		return $return;
	}
	$seats = isset($options['seats']) ? $options['seats'] : "0";
	$return .= "<input type='number' name='naiop_seats$pattern' step='1' id='naiop_seats_$counting" . '_' . "$label' value='" . esc_attr($seats) . "' size='4' />";
	return $return;
}

/* default to continous ticketing */
add_filter('naiop_default_ticketing_tab', 'naiop_default_ticketing_tab', 10, 1);
function naiop_default_ticketing_tab($default) {
	return "continuous";
}

add_filter('naiop_total_tickets_label', 'naiop_total_tickets_label', 10, 1);
function naiop_total_tickets_label($default) {
	return "Total Seats Available";
}

/* save ticketing (with seats) */
add_filter('naiop_setup_pricing', 'naiop_setup_pricing', 10, 6);
function naiop_setup_pricing($post_id, $pricing_array, $post, $model, $sold = array(), $times = array()) {
	$event_category = "Events";
	if (function_exists('mt_get_settings')) {
		$options  = mt_get_settings();
		$event_category = $options['naiop_ticket_cat'];
	}

	if (!is_null($model)) { //ticket settings
		$labels         = ( isset( $post['mt_label'][ $model ] ) ) ? $post['mt_label'][ $model ] : array();
		$prices         = ( isset( $post['mt_price'][ $model ] ) ) ? $post['mt_price'][ $model ] : array();
		$seats          = ( isset( $post['naiop_seats'][ $model ] ) ) ? $post['naiop_seats'][ $model ] : array();
		$product_id     = ( isset( $post['naiop_product_id'] ) ) ? $post['naiop_product_id'][ $model ] : array();
		$close          = ( isset( $post['mt_close'][ $model ] ) ) ? $post['mt_close'][ $model ] : array();
		$availability   = ( isset( $post['mt_tickets'][ $model ] ) ) ? $post['mt_tickets'][ $model ] : array();
	} else { //event-ticketing form
		$labels         = ( isset( $post['mt_label'] ) ) ? $post['mt_label'] : array();
		$prices         = ( isset( $post['mt_price'] ) ) ? $post['mt_price'] : array();
		$seats          = ( isset( $post['naiop_seats'] ) ) ? $post['naiop_seats'] : array();
		$product_ids    = ( isset( $post['naiop_product_id'] ) ) ? $post['naiop_product_id'] : array();
		$close          = ( isset( $post['mt_close'] ) ) ? $post['mt_close'] : array();
		$availability   = ( isset( $post['mt_tickets'] ) ) ? $post['mt_tickets'] : array();
	}
	$event_title = ( ( isset($post['event_title']) && $post['event_title'] !== '' ) ) ? $post['event_title'] : "Unknown";
	$event_desc = ( ( isset($post['content']) ) ) ? $post['content'] : "";
	$event_excerpt = ( ( isset($post['event_short']) ) ) ? $post['event_short'] : "";
	$attachment_id = ( ( isset($post['event_image_id']) ) ) ? (int) $post['event_image_id'] : false;

	// initally all products are candidates for deletion
	$product_to_delete = array();
	if ( $post_id ) {
		$old_registration = get_post_meta( $post_id, '_mt_registration_options', true );
		if ( isset( $old_registration['prices'] ) ) {
			foreach ( $old_registration['prices'] as $key => $price_data ) {
				if ( is_numeric( $price_data['product_id'] ) ) {
					array_push($product_to_delete, (int) $price_data['product_id']);
				}
			}
		}
	}

	$return = array();
	if ( is_array( $labels ) ) {
		$i = 0;
		foreach ( $labels as $key => $label ) {
			if ( $label ) {
				$label          = ( isset( $times[ $key ] ) ) ? $label . ' ' . $times[ $key ] : $label;
				$internal_label = sanitize_title( $label );
				$price          = ( is_numeric( $prices[ $i ] ) ) ? $prices[ $i ] : (int) $prices[ $i ];
				$product_id     = ( isset( $product_ids[ $i ] ) ) ? $product_ids[ $i ] : '';
				if ( isset( $seats[ $i ] ) && '' !== $seats[ $i ] ) {
					$seat_count = ( is_numeric( $seats[ $i ] ) ) ? $seats[ $i ] : (int) $seats[ $i ];
				} else {
					$seat_count = 1;
				}
				$product_name   = $event_title . ": " . $label;
				$product_name   .= ( ( $seat_count > 1 ) ? ( " (seats " . $seat_count . ")" ) : "" );
				if ( isset( $availability[ $i ] ) && '' !== $availability[ $i ] ) {
					$tickets = ( is_numeric( $availability[ $i ] ) ) ? $availability[ $i ] : (int) $availability[ $i ];
				} else {
					$tickets = '';
				}

				// empty $product_id -> create a new Product
				if ( $product_id === '' && $label !== 'Complimentary') {
					$product = new WC_Product_Simple();
					$product->set_name($product_name);
					$product->set_description($event_desc);
					$product->set_short_description($event_excerpt);
					$product->set_catalog_visibility('hidden');
					$product->set_regular_price($price);
					$product->set_category_ids( [ $event_category ] );
					if ($attachment_id) {
						$product->set_image_id($attachment_id);
					}
					$product->save();
					$product_id = $product->get_id();
				} else if ( $product_id !== '' ) { // existing product
					$product = wc_get_product($product_id);
					if ($product) {
						if ( ($index = array_search($product->get_id(), $product_to_delete)) !== false ) {
							unset($product_to_delete[$index]); // mark the product as being used
						}
						$product->set_name($product_name);
						$product->set_description($event_desc);
						$product->set_short_description($event_excerpt);
						$product->set_catalog_visibility('hidden');
						$product->set_regular_price($price);
						$product->set_category_ids( [ $event_category ] );
						if ($attachment_id) {
							$product->set_image_id($attachment_id);
						}
						$product->save();
					} else {
						// TODO: create a new Product?
						error_log("Failed to find Product for POST value: " . $product_id);
						$product_id = "";
					}
				}

				$sold_tickets              = ( isset( $sold[ $i ] ) ) ? (int) $sold[ $i ] : '';
				$closing                   = ( isset( $close[ $i ] ) ) ? strtotime( $close[ $i ] ) : '';
				$return[ $internal_label ] = array(
					'label'   => esc_html( $label ),
					'price'   => $price,
					'seats'   => $seat_count,
					'product_id' => $product_id,
					'tickets' => $tickets,
					'sold'    => $sold_tickets,
					'close'   => $closing,
				);
			}
			++$i;
		}
	}
	if (count($product_to_delete) > 0) {
		error_log("TODO delete orphaned products: " . print_r($product_to_delete, true));
	}

	return $return;
}

add_action( 'mc_delete_event', 'naiop_delete_event_handler', 10, 2 );
function naiop_delete_event_handler($event_id, $post_id) {
	// TODO: delete Products associated with it
	error_log("TODO: delete Products associated with " . $event_id);
}

