<?php
/**
 * DokuWiki StyleSheet creator fork
 *
 * @license    GPL3 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <andi@splitbrain.org>
 * @author     Samuel Fischer <sf@notomorrow.de>
 */

if(!defined('DOKU_INC')) define('DOKU_INC',dirname(__FILE__).'/../../');
if(!defined('NOSESSION')) define('NOSESSION',true); // we do not use a session or authentication here (better caching)
if(!defined('DOKU_DISABLE_GZIP_OUTPUT')) define('DOKU_DISABLE_GZIP_OUTPUT',1); // we gzip ourself here
require_once(DOKU_INC.'inc/init.php');
require_once(DOKU_INC.'inc/pageutils.php');
require_once(DOKU_INC.'inc/httputils.php');
require_once(DOKU_INC.'inc/io.php');
require_once(DOKU_INC.'inc/confutils.php');

// Main (don't run when UNIT test)
if(!defined('SIMPLE_TEST')){
    header('Content-Type: text/css; charset=utf-8');
    css_out_tfh();
}


// ---------------------- functions ------------------------------

/**
 * Output all needed Styles
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 */
function css_out_tfh(){
    global $conf,$config_cascade;
    global $lang;
    $style = '';
    if (isset($_REQUEST['s']) &&
        in_array($_REQUEST['s'], array('all', 'print', 'feed'))) {
        $style = $_REQUEST['s'];
    }

    $tpl = trim(preg_replace('/[^\w-]+/','',$_REQUEST['t']));
    if($tpl){
        #$tplinc = DOKU_INC.'lib/tpl/'.$tpl.'/';
        $tpldir = DOKU_BASE.'lib/tpl/'.$tpl.'/';
    }else{
        #$tplinc = DOKU_TPLINC;
        $tpldir = DOKU_TPL;
    }

    $cache = '';

    // changes for farming start here

    // meant to detect changes in config_cascade
    foreach( $config_cascade['template_dir'] as $k => $v ) {
        $cache.= str_replace( array( ':', '/' ), '', implode( $v ));   
    }
    $cache .= $tpl;

    // The generated script depends on some dynamic options
    $cache = getCacheName('styles'.$_SERVER['HTTP_HOST'].$_SERVER['SERVER_PORT'].DOKU_BASE.$cache.$style,'.css');

    // load template styles
    $tplstyles = array();
    $style_ini = css_getpath( $tpl, 'style.ini' );
    if( $style_ini ){
        $ini = parse_ini_file( $style_ini, true);
        if( count( $ini )) {
            foreach($ini['stylesheets'] as $file => $mode){
                $tplstyles[$mode][css_getpath( $tpl, $file )] = $tpldir;
            }
        }
    }
#    if(@file_exists($tplinc.'style.ini')){
#        $ini = parse_ini_file($tplinc.'style.ini',true);
#        foreach($ini['stylesheets'] as $file => $mode){
#            $tplstyles[$mode][$tplinc.$file] = $tpldir;
#        }
#    }

    // Array of needed files and their web locations, the latter ones
    // are needed to fix relative paths in the stylesheets
    $files   = array();
    //if (isset($tplstyles['all'])) $files = array_merge($files, $tplstyles['all']);
    if(!empty($style)){
        $files[DOKU_INC.'lib/styles/'.$style.'.css'] = DOKU_BASE.'lib/styles/';
        // load plugin, template, user styles
        $files = array_merge($files, css_pluginstyles($style));
        if (isset($tplstyles[$style])) $files = array_merge($files, $tplstyles[$style]);
        elseif (isset($tplstyles['screen'])) $files = array_merge($files, $tplstyles['screen']); // FIX

        if(isset($config_cascade['userstyle'][$style])){
            $files[$config_cascade['userstyle'][$style]] = DOKU_BASE;
        }
    }else{
        $files[DOKU_INC.'lib/styles/style.css'] = DOKU_BASE.'lib/styles/';
        // load plugin, template, user styles
        $files = array_merge($files, css_pluginstyles('screen'));
        if (isset($tplstyles['screen'])) $files = array_merge($files, $tplstyles['screen']);
        if($lang['direction'] == 'rtl'){
            if (isset($tplstyles['rtl'])) $files = array_merge($files, $tplstyles['rtl']);
        }
        if(isset($config_cascade['userstyle']['default'])){
            $files[$config_cascade['userstyle']['default']] = DOKU_BASE;
        }
    }

    // check cache age & handle conditional request
    header('Cache-Control: public, max-age=3600');
    header('Pragma: public');
    if(css_cacheok_tfh($cache,array_merge( array_keys($files)), css_getpath( $tpl, 'style.ini' ))){     //added style.init
        http_conditionalRequest(filemtime($cache));
        if($conf['allowdebug']) header("X-CacheUsed: $cache");

        // finally send output
        if ($conf['gzip_output'] && http_gzip_valid($cache)) {
          header('Vary: Accept-Encoding');
          header('Content-Encoding: gzip');
          readfile($cache.".gz");
        } else {
          if (!http_sendfile($cache)) readfile($cache);
        }

        return;
    } else {
        http_conditionalRequest(time());
    }

    // start output buffering and build the stylesheet
    ob_start();

    // print the default classes for interwiki links and file downloads
    css_interwiki();
    css_filetypes();

    // load files
    foreach($files as $file => $location){
        print css_loadfile($file, $location);
    }

    // end output buffering and get contents
    $css = ob_get_contents();
    ob_end_clean();

    // apply style replacements
    $css = css_applystyle_tfh($css,$tpl);   // removed tplinc

    // compress whitespace and comments
    if($conf['compress']){
        $css = css_compress($css);
    }

    // save cache file
    io_saveFile($cache,$css);
    if(function_exists('gzopen')) io_saveFile("$cache.gz",$css);

    // finally send output
    if ($conf['gzip_output']) {
      header('Vary: Accept-Encoding');
      header('Content-Encoding: gzip');
      print gzencode($css,9,FORCE_GZIP);
    } else {
      print $css;
    }
}

/**
 * Checks if a CSS Cache file still is valid
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 */
function css_cacheok_tfh($cache,$files,$style_ini){
    global $config_cascade;

    if(isset($_REQUEST['purge'])) return false; //support purge request

    $ctime = @filemtime($cache);
    if(!$ctime) return false; //There is no cache

    // some additional files to check
    $files = array_merge($files, getConfigFiles('main'));
    if( $style_ini ) $files[] = $style_ini;     // remoted tplinc
    $files[] = __FILE__;

    // now walk the files
    foreach($files as $file){
        if(@filemtime($file) > $ctime){
            return false;
        }
    }
    return true;
}

/**
 * Does placeholder replacements in the style according to
 * the ones defined in a templates style.ini file
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 */
function css_applystyle_tfh($css, $tpl ){
    global $conf;

      if( !$file = getConfigPath( 'template_dir', $tpl.'/style.ini' ))
        $file = getConfigPath( 'template_dir', $conf['default_tpl'].'/style.ini' );

    $ini = parse_ini_file( $file, true);
    $css = strtr($css,$ini['replacements']);

    // #TODO  
    // implement logic to overwrite deffinitions ? would be nice to inherit style replacements
    // this affects cache 
      #global $conf;

#    if(@file_exists($tpldir.'style.ini')){
#        $ini = parse_ini_file($tpldir.'style.ini',true);
#        $css = strtr($css,$ini['replacements']);
#    }
    return $css;
}


function css_getpath( $t, $file ) {
    global $conf;

    if( !$t ) { $t = $conf['template']; }
    if( !$t || !$include = getConfigPath( 'template_dir', $t.'/'.$file )) {

        if( $conf['template'] && $t != $conf['template'] )
            $include = getConfigPath( 'template_dir', $conf['template'].'/'.$file );
        elseif( $conf['default_tpl'] && $t != $conf['default_tpl'] )
            $include = getConfigPath( 'template_dir', $conf['default_tpl'].'/'.$file );
    }
    if( !$include ) {
        $include = getConfigPath( 'template_dir', $conf['base_tpl'].'/'.$file );
    }
#echo "include($file): $include<br>\n";

    return $include; 

}
//Setup VIM: ex: et ts=4 enc=utf-8 :
