<?php
if (!defined("AREA")) die("Access Denied");

fn_register_hooks("update_product_pre");
fn_register_hooks("update_product_post");
fn_register_hooks("update_product_amount");
fn_register_hooks("tools_change_status");
?>
