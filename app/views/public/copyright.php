<?php
/**
 * Temporary compatibility bridge.
 *
 * The legacy page still owns request handling and markup for this page.
 * Move its logic into a controller and its markup into this view before
 * replacing this bridge.
 */
require BASE_PATH . '/pages/copyright.php';
