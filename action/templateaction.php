<?php
/**
 * DokuWiki plugin template helper
 *
 * @license    GPL3 (http://www.gnu.org/licenses/gpl.html)
 * @author     Samuel Fischer <sf@notomorrow.de>
 */


if(!defined('DOKU_INC')) die();


class action_plugin_templateconfhelper_templateaction extends DokuWiki_Action_Plugin {

  function getInfo(){
    return array(
        'author' => 'sf',
        'email'  => 'sf@notomorrow.de',
        'date'   => '2010-02-07',
        'name'   => 'template actions',
        'desc'   => 'switch template based on user selection',
        'url'    => 'samfisch.de',
    );
  }

  function register(&$controller) {/*{{{*/

      $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE',  $this, 'template_action' );

  }/*}}}*/

  function template_action( ) {/*{{{*/
    global $conf;
    $tpl    = $conf['template'];
    $theme  = '';
    $switch = false;

    $u = $this->get_user( ); // init user data

    if( isset( $u['template'] ) && $u['template'] != $tpl ) {
	$tpl = $u['template'];
	$switch = true;
    }
    if( isset( $u['template_theme'] ) && $u['template_theme'] != $tpl ) {
	$theme = $u['template_theme'];
	$switch = true;
    }

    if( isset( $_GET['utpl'] ) && preg_match( '/^[\w-]+$/', $_GET['utpl'] )) {
        if( $_GET['utpl'] != $tpl && $_GET['utpl'] != $conf['template'] ) { 
	    $switch = true;
        }
	$tpl = $_GET['utpl']; 
        $this->save_session( 'template', $tpl );
    }
    if( isset( $_GET['utpl'] ) && $_GET['utpl'] == "" ) {
	$tpl = $conf['default_tpl']; 
        $this->save_session( 'template', '' );	// fix subconf switch
	$switch = false;
    }


    if( isset( $_GET['utpl_theme'] ) && preg_match( '/^[\w-]*$/', $_GET['utpl_theme'] )) { 
        if( $_GET['utpl_theme'] ) {
            $theme = $_GET['utpl_theme'];
            $switch = true;
        }
        $this->save_session( 'template_theme', $_GET['utpl_theme'] );
    }

    if( $switch && preg_match( '/^[\w-]+$/', $tpl )) {
	$this->_switch( $tpl, $theme );
    }
      
  }/*}}}*/

  public function get_user( $var=false ) {/*{{{*/
    if( !defined('NOSESSION' )) {
      if( !isset( $this->u['load'] )) {
        @session_start();
        $this->u = ( isset( $_SESSION[DOKU_COOKIE]['tpl'] )) ? $_SESSION[DOKU_COOKIE]['tpl'] : false;
      }

      if( !isset( $this->u['load'] )) {
	  $this->u['load'] = 1;
	  // TODO: load wikiuser selection from file
      }
      if( $var ) return isset( $this->u[$var] ) ? $this->u[$var] : false;
      return $this->u;
    } else {
	return array( );
    }
  }/*}}}*/

  public function save_session( $var, $val ) {/*{{{*/
    if( !defined('NOSESSION' )) {
      $this->u[$var] = $val;

      @session_start();
      $_SESSION[DOKU_COOKIE]['tpl'] = $this->u;
      session_write_close();
    }
  }/*}}}*/

  public function save_user( $var, $val ) {
    return false;
  }

  /**
   * actual helper function
   * changes style after doku init 
   */

  public function tpl_switch(  $tpl ) {/*{{{*/
    global $conf;
    if( $conf['template'] == $tpl ) { return ''; }

  // prevent userstyle from beeing overwritten ... one or the other way 
    if( $this->get_user( 'template' )) { return ''; }
    if( preg_match( '/^[\w-]+$/', $tpl )) {
	    $this->_switch( $tpl );
    }
  }/*}}}*/

  function _switch( $tpl, $theme='' ) {/*{{{*/
    global $conf;
    global $tpl_configloaded;

    if( $theme ) {
        $conf['template_theme'] = $theme;
    }

    $conf['default_tpl'] = $conf['template'];
    $conf['template'] = $tpl;

    $tconf = $this->tpl_loadconfig( $tpl ); 
    if ($tconf !== false){
      foreach ($tconf as $key => $value){
	if (isset($conf['tpl'][$tpl][$key])) continue;
	$conf['tpl'][$tpl][$key] = $value;
      }
      $tpl_configloaded = true;
    }
  }/*}}}*/

  public function tpl_loadconfig( $tpl ) {/* {{{*/
        $file = DOKU_TPLINC.'../'.$tpl.'/conf/default.php';
        $conf = array();
        if (!@file_exists($file)) return false;

        include($file);
        return $conf;
    } /*}}}*/

}

// pirating into css.php 
require_once( DOKU_PLUGIN.'/templateconfhelper/inc/preload.php' ); // tpl_... functions                              
