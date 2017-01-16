<?php

/*
 * Title   : Conekta Payment Extension for WooCommerce
 * Author  : Cristina Randall
 * Url     : https://www.conekta.io/es/docs/plugins/woocommerce
 */

/**
 * Build the line items hash
 * @param array $items
 */
function build_line_items($items)
{
    $line_items = array();
    foreach ($items as $item) {
        $productmeta = new WC_Product($item['product_id']);
        $sku         = $productmeta->get_sku();
        $unit_price  = (floatval($item['line_subtotal']) * 1000) / floatval($item['qty']);
        if ($productmeta->is_downloadable()) {
            $type = 'downloadable';
        } else {
            if ($productmeta->is_virtual()) {
                $type = 'service';
            } else {
                $type = 'physical';
            }
        }
        $line_items  = array_merge($line_items, array(array(
         'name'        => $item['name'],
         'unit_price'  => intval(round(floatval($unit_price) / 10), 2),
         'description' => $item['name'],
         'quantity'    => intval($item['qty']),
         'sku'         => $sku,
         'type'        => $type,
         'tags'        => ['WooCommerce']
         ))
        );
    }
    
    return $line_items;
}

function build_tax_lines($taxes)
{
    $tax_lines = array();

    foreach ($taxes as $tax) {
        $tax_amount = floatval($tax['tax_amount']) * 1000;
        $tax_lines  = array_merge($tax_lines, array(
            array(
                'description' => $tax['label'],
                'amount'      => intval(round(floatval($tax_amount) / 10), 2)
            )
        ));
    }

    return $tax_lines;
}

function build_shipping_lines($data)
{
    $shipping_lines = $data['shipping_lines'];

    return $shipping_lines;
}

function build_discount_lines($data)
{
    if (!empty($data['discount_lines'])) {
        $discount_lines = $data['discount_lines'];
    }

    return $discount_lines;
}

function build_shipping_contact($data)
{
    $shipping_contact = array_merge($data['shipping_contact'], array('metadata' => array('soft_validations' => true)));

    return $shipping_contact;
}

function build_customer_info($data)
{
    $customer_info = array_merge($data['customer_info'], array('metadata' => array('soft_validations' => true)));

    return $customer_info;
}

/**
* Bundle and format the order information
* @param WC_Order $order
* Send as much information about the order as possible to Conekta
*/
function getRequestData($order)
{
    if ($order AND $order != null)
    {
        // Discount Lines
        $order_coupons  = $order->get_items('coupon');
        $discount_lines = array();

        foreach($order_coupons as $index => $coupon) {
            $discount_lines = array_merge($discount_lines, array(array(
                'description' => $coupon['name'],
                'kind'        => $coupon['type'],
                'amount'      => $coupon['discount_amount'] * 100
            )));
        }

        // Shipping Lines
        $shipping_method = $order->get_shipping_method();
        $shipping_lines  = array(
            array(
                'description' => $shipping_method,
                'amount'      => (float)$order->get_total_shipping() * 100,
                'carrier'     => $shipping_method,
                'method'      => $shipping_method
            )
        );

        // Shipping Contact
        $shipping_contact = array(
            'email'    => $order->billing_email,
            'phone'    => $order->billing_phone,
            'receiver' => sprintf('%s %s', $order->shipping_first_name, $order->shipping_last_name),
            'address' => array(
                'street1' => $order->shipping_address_1,
                'street2' => $order->shipping_address_2,
                'city'    => $order->shipping_city,
                'state'   => $order->shipping_state,
                'country' => $order->shipping_country,
                'zip'     => $order->shipping_postcode
            ),
        );

        // Customer Info
        $customer_info = array(
            'name'  => sprintf('%s %s', $order->billing_first_name, $order->billing_last_name),
            'phone' => $order->billing_phone,
            'email' => $order->billing_email
        );

        $data = array(
            'amount'               => (float) $order->get_total() * 100,
            'token'                => $_POST['conekta_token'],
            'monthly_installments' => (int) $_POST['monthly_installments'],
            'currency'             => strtolower(get_woocommerce_currency()),
            'description'          => sprintf('Charge for %s', $order->billing_email),
            'customer_info'        => $customer_info,
            'shipping_contact'     => $shipping_contact
        );

        if(!empty($discount_lines)) {
            $data = array_merge($data, array('discount_lines' => $discount_lines));
        }
        if (!empty($shipping_method)) {
            $data = array_merge($data, array('shipping_lines' => $shipping_lines));
        }

        return $data;
    }

    return false;
}