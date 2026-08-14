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
require_once dirname( __DIR__ ) . '/core/QT_Prerequisite.php';
require_once dirname( __DIR__ ) . '/core/QT_Person.php';
require_once dirname( __DIR__ ) . '/core/QT_DueDateCalculator.php';

# Minimal MantisBT constants so the pure parts of the core files load without a
# running MantisBT (only the data-definition helpers are unit-tested).
if( !defined( 'CUSTOM_FIELD_TYPE_STRING' ) )  { define( 'CUSTOM_FIELD_TYPE_STRING', 0 ); }
if( !defined( 'CUSTOM_FIELD_TYPE_NUMERIC' ) ) { define( 'CUSTOM_FIELD_TYPE_NUMERIC', 1 ); }
if( !defined( 'CUSTOM_FIELD_TYPE_ENUM' ) )    { define( 'CUSTOM_FIELD_TYPE_ENUM', 3 ); }
if( !defined( 'CUSTOM_FIELD_TYPE_DATE' ) )    { define( 'CUSTOM_FIELD_TYPE_DATE', 8 ); }

require_once dirname( __DIR__ ) . '/core/QT_CustomFields.php';
require_once dirname( __DIR__ ) . '/core/QT_CatalogImport.php';
require_once dirname( __DIR__ ) . '/core/QT_Profile.php';
require_once dirname( __DIR__ ) . '/core/QT_Assignment.php';
require_once dirname( __DIR__ ) . '/core/QT_Generator.php';
require_once dirname( __DIR__ ) . '/core/QT_SollIst.php';
require_once dirname( __DIR__ ) . '/core/QT_Completion.php';
require_once dirname( __DIR__ ) . '/core/QT_Integration.php';
require_once dirname( __DIR__ ) . '/core/QT_Event.php';
require_once dirname( __DIR__ ) . '/core/QT_Participant.php';
require_once dirname( __DIR__ ) . '/core/QT_Matrix.php';
require_once dirname( __DIR__ ) . '/core/QT_Expiry.php';
require_once dirname( __DIR__ ) . '/core/QT_Integration.php';
require_once dirname( __DIR__ ) . '/core/QT_Reactivation.php';
require_once dirname( __DIR__ ) . '/core/QT_Escalation.php';
require_once dirname( __DIR__ ) . '/core/QT_Ruhen.php';
require_once dirname( __DIR__ ) . '/core/QT_RunLog.php';
require_once dirname( __DIR__ ) . '/core/QT_ModeChange.php';
