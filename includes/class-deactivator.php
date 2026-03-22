<?php

namespace AlyntWCOrderManager;

defined( 'ABSPATH' ) || exit;

class Deactivator {
	public static function deactivate() {
		\awcom_deactivate();
	}
}
