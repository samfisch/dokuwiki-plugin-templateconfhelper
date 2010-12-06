<?
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
if( !isset( $config_cascade['template'] )) {
   $config_cascade['template'] = array(
        'default'     => array( DOKU_CONF.'template.php'),
   );
}

// wrong default_tpl means no template might be loaded so it is hardcoded for now
#if( !isset( $conf['default_tpl'] )) $conf['default_tpl'] = $conf['template'];
$conf['default_tpl'] = 'std';
if( !isset( $conf['base_tpl'] )) $conf['base_tpl'] = 'default';

foreach (array('default','local','protected') as $config_group) {
    if (empty($config_cascade['template'][$config_group])) continue;
    foreach ($config_cascade['template'][$config_group] as $config_file) {
      if(@file_exists($config_file)){
     
	$conf_template = array( );
	include($config_file);
     
	foreach($conf_template as $id => $tpl ) {
	  if ($id!='default' && preg_match($id, $request_id)) {
	    $conf['template'] = $tpl;
	    break 2;
	  }
	}
      
      }
    }
}


/**
 * intercept css.php calls
 */
if( strpos( $_SERVER['PHP_SELF'], 'css.php' ) !== false ) {
  $e = new action_plugin_templateconfhelper_templateaction( );
  $e->template_action( );
  require_once(DOKU_PLUGIN.'templateconfhelper/exe/css.php');
  exit;
}  

#if(!defined('DOKU_REL')) define('DOKU_REL',getBaseURL(false));                                                                         
#if(!defined('DOKU_COOKIE')) define('DOKU_COOKIE', 'DW'.md5(DOKU_REL.(($conf['securecookie'])?$_SERVER['SERVER_PORT']:'')));           

#require_once(DOKU_INC.'inc/plugincontroller.class.php');
#require_once(DOKU_INC.'inc/plugin.php');
#require_once(DOKU_INC.'inc/pluginutils.php');
#require_once(DOKU_PLUGIN.'action.php');
#
#$plugin_types = array('admin','syntax','action','renderer', 'helper');
#global $plugin_controller_class, $plugin_controller;                                                                                   
#if (empty($plugin_controller_class)) $plugin_controller_class = 'Doku_Plugin_Controller';
# 
#$plugin_controller = new $plugin_controller_class();                                                                                   
#
#if( ( $tplfn = &plugin_load( 'action', 'templateconfhelper' ))) {
#    $tplfn->template_action( );
#}
