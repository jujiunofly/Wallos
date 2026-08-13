<?php

$timezone = getenv('TZ');
if (!is_string($timezone) || $timezone === '') {
    $timezone = date_default_timezone_get();
}
if (!is_string($timezone) || $timezone === '' || !in_array($timezone, timezone_identifiers_list(), true)) {
    $timezone = 'UTC';
}
date_default_timezone_set($timezone);
