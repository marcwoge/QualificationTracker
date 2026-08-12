<?php
/**
 * PHPUnit bootstrap for QualificationTracker.
 *
 * Loads only the side-effect-free core logic. These files must be requirable
 * without a running MantisBT session (no output, no $_POST, no DB access at
 * load time), which is why the pure helpers live apart from the pages.
 *
 * @package QualificationTracker
 * @license MIT
 */

require_once dirname( __DIR__ ) . '/core/QT_Catalog.php';
