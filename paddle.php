<?php
/**
 * Ensures that the module init file can't be accessed directly, only within the application.
 */
defined('BASEPATH') or exit('No direct script access allowed');
/*
Module Name: Paddle
Description: Paddle.io
Author: Techy4m
Author URI: https://codecanyon.net/user/techy4m
Version: 1.0.0
Requires at least: 2.6.*
*/ 
register_payment_gateway('Paddle_gateway', 'paddle');


