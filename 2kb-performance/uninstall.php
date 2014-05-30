<?php
//if uninstall not called from WordPress exit
if (!defined('WP_UNINSTALL_PLUGIN'))
    exit();

/**
 * If not included for some reason.
 */
require_once 'plugin.php';

/**
 * @remove options
 */
getKbPerformance()->clearOptions();

/**
 * @remove cache dir
 */
rmdir(getKbPerformance()->uploadsPath);