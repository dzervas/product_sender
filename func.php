<?php
use Tygh\Registry;
if (!defined("AREA")) die("Access Denied");
require_once(dirname(__FILE__) . "/lib/restclient.php");

$old_product_data = array();

function put_product($new_data, $product_code, $from) {
	$send_data = array();
	foreach ($new_data as $key => $val) {
		if (Registry::get("addons.dzervas_product_sender.".$key))
			$send_data[$key] = $val;
	}

	if (!$product_code || empty($send_data)) return;
	$api = new RestClient([
		"base_url" => Registry::get("addons.dzervas_product_sender.remote") . "/api",
		"username" => Registry::get("addons.dzervas_product_sender.api_email"),
		"password" => Registry::get("addons.dzervas_product_sender.api_key"),
		"headers" => [ "content-type" => "" ]
	]);

	$rid = $api->get("products", ["pcode" => $product_code]);
	if ($rid->info->http_code != 200) {
		fn_set_notification("E", "Product Sender", "Failed to communicate with remote server");
		return;
	}
	$rid = $rid->decode_response();
	if (count($rid->products) != 1) {
		fn_set_notification("W", "Product Sender", "Product $product_code not found or duplicate SKU on remote");
		return;
	}
	$rid = $rid->products[0];

	if (Registry::get("addons.dzervas_product_sender.dummy") == "N") {
		$res = $api->put("products/" . $rid->product_id, $send_data);
		if ($res->info->http_code != 200) {
			fn_set_notification("E", "Product Sender", "Failed to communicate with remote server");
			return;
		}
	}

	fn_set_notification("N", "Product Sender", "Product $product_code sent " . implode(", ", array_keys($send_data)) . " on remote");
}

function post_product($product_data, $from) {
	if (!$product_data["product_code"] || Registry::get("addons.dzervas_product_sender.create") != "Y") return;
	$api = new RestClient([
		"base_url" => Registry::get("addons.dzervas_product_sender.remote") . "/api",
		"username" => Registry::get("addons.dzervas_product_sender.api_email"),
		"password" => Registry::get("addons.dzervas_product_sender.api_key")
	]);

	if (Registry::get("addons.dzervas_product_sender.dummy") == "N") {
		$res = $api->post("products/", $product_data);
		if ($res->info->http_code != 200) {
			fn_set_notification("E", "Product Sender", "Failed to communicate with remote server");
			return;
		}
	}

	fn_set_notification("N", "Product Sender", "Product " . $product_data["product_code"] . " created on remote");
}

function fn_dzervas_product_sender_tools_change_status($params, $result) {
	if ($params["table"] == "products" && $params["id_name"] == "product_id" && $result) {
		$product_data = fn_get_product_data($params["id"]);
		put_product(array("status" => $product_data["status"]), $product_data["product_code"], "tools_change_status");
	}
}

function fn_dzervas_product_sender_update_product_pre($product_data, $product_id, $lang_code, $can_update) {
	global $old_product_data;
	$old_product_data[$product_id] = fn_get_product_data($product_id);;
}

function fn_dzervas_product_sender_update_product_post($product_data, $product_id, $lang_code, $create) {
	global $old_product_data;
	$more_product_data = fn_get_product_data($product_id);
	$new_data = array();

	if ($create) {
		post_product($more_product_data, "update_product_post");
	} else {
		if ($old_product_data[$product_id]["amount"] !== $more_product_data["amount"])
			$new_data["amount"] = $more_product_data["amount"];
		if ($old_product_data[$product_id]["status"] !== $more_product_data["status"])
			$new_data["status"] = $more_product_data["status"];
		if ($old_product_data[$product_id]["product_code"] !== $more_product_data["product_code"])
			$new_data["product_code"] = $more_product_data["product_code"];
		if ($old_product_data[$product_id]["part_number"] !== $more_product_data["part_number"])
			$new_data["part_number"] = $more_product_data["part_number"];

		put_product($new_data, $old_product_data[$product_id]["product_code"], "update_product_post");
	}
}

function fn_dzervas_product_sender_update_product_amount($new_amount, $product_id, $cart_id, $tracking) {
	$product_data = fn_get_product_data($product_id);
	put_product(array("amount" => $new_amount), $product_data["product_code"], "update_product_amount");
}
?>
