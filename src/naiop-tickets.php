<?php
/**
 * NAIOP Tickets 2024-04-10
 *
 * @package     NAIOP Tickets
 * @author      Scott Dohei
 * 
 */

/*add_filter( 'mt_add_to_cart_input', 'add_to_cart_input', 10, 8 );
function add_to_cart_input($html, $input_type, $type, $value, $attributes, $unknown, $remaining, $available) {
	if ( $type === 'complimentary' ) {
		return "";
	}
	return $html;
}*/

add_filter( 'naiop_ticket_row', 'filter_ticket_rows', 10, 2 );
function filter_ticket_rows($html, $type) {
	error_log("test" . print_r($type, true));
	if ( $type === 'complimentary' ) {
		
		return false;
	}
	return $html;
}
