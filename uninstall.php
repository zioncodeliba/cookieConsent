<?php
if (!defined('WP_UNINSTALL_PLUGIN')) { exit; }
// Clean stored options on uninstall
delete_option('wpccm_options');
