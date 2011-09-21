<?php
/**
 * DokuWiki plugin template changing preload
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Samuel Fischer <sf@notomorrow.de>
 */


if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');

require_once(DOKU_PLUGIN.'templateconfhelper/inc/confutils.php');
require_once(DOKU_PLUGIN.'templateconfhelper/inc/template.php');

global $config_cascade, $conf;
if( !isset( $config_cascade['template_dir'] )) {
   $config_cascade['template_dir'] = array(    // used in confutils	
        'default' => array( dirname( DOKU_TPLINC ).'/' ),
   );
}

$conf['default_tpl'] = $conf['template'];
if( !isset( $conf['base_tpl'] )) 
    $conf['base_tpl'] = $conf['plugin']['templateconfhelper']['base_tpl'];

/**
 * intercept css.php calls
 */
if( strpos( $_SERVER['PHP_SELF'], 'css.php' ) !== false ) {
  $e = new action_plugin_templateconfhelper_templateaction( );
  $e->template_action( );
  require_once(DOKU_PLUGIN.'templateconfhelper/exe/css.php');
  exit;
}  

