<?php
/**
 * overrides for DokuWiki StyleSheet creator
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
    _css_out();
}


// ---------------------- functions ------------------------------

/**
 * Output all needed Styles
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 */
function _css_out(){
    global $conf;
    global $lang;
    global $config_cascade;
    global $INPUT;

    if ($INPUT->str('s') == 'feed') {
        $mediatypes = array('feed');
        $type = 'feed';
    } else {
        $mediatypes = array('screen', 'all', 'print');
        $type = '';
    }

    $tpl = trim(preg_replace('/[^\w-]+/','',$INPUT->str('t')));
    if($tpl){
        #$tplinc = DOKU_INC.'lib/tpl/'.$tpl.'/';
        $tpldir = DOKU_BASE.'lib/tpl/'.$tpl.'/';
    }else{
        #$tplinc = tpl_incdir();
        $tpldir = tpl_basedir();
    }

    foreach( array( 'style.ini', 'style.local.ini' ) as $ini ) {
        $ini = _css_getpath( $tpl, $ini );
        if( @file_exists( $ini )) {
            $tplinc[] = $ini;
        }
    }

    // used style.ini file
    $styleini = _css_styleini($tplinc);

    // The generated script depends on some dynamic options
    $cache = new cache('styles'.$_SERVER['HTTP_HOST'].$_SERVER['SERVER_PORT'].DOKU_BASE.$cache.$mediatype,'.css');

    // load template styles
    $tplstyles = array();
    if ($styleini) {
        foreach($styleini['stylesheets'] as $file => $mode) {
            $tplstyles[$mode][_css_getpath( $tpl, $file )] = $tpldir;
        }
    }

    // if old 'default' userstyle setting exists, make it 'screen' userstyle for backwards compatibility
    if (isset($config_cascade['userstyle']['default'])) {
        $config_cascade['userstyle']['screen'] = $config_cascade['userstyle']['default'];
    }

    // Array of needed files and their web locations, the latter ones
    // are needed to fix relative paths in the stylesheets
    $files = array();

    $cache_files = getConfigFiles('main');
    foreach( $tplinc as $ini ) {
	    $cache_files[] = $ini;
    }
    $cache_files[] = __FILE__;

    foreach($mediatypes as $mediatype) {
        $files[$mediatype] = array();
        // load core styles
        $files[$mediatype][DOKU_INC.'lib/styles/'.$mediatype.'.css'] = DOKU_BASE.'lib/styles/';
        // load jQuery-UI theme
        if ($mediatype == 'screen') {
            $files[$mediatype][DOKU_INC.'lib/scripts/jquery/jquery-ui-theme/smoothness.css'] = DOKU_BASE.'lib/scripts/jquery/jquery-ui-theme/';
        }
        // load plugin styles
        $files[$mediatype] = array_merge($files[$mediatype], css_pluginstyles($mediatype));
        // load template styles
        if (isset($tplstyles[$mediatype])) {
            $files[$mediatype] = array_merge($files[$mediatype], $tplstyles[$mediatype]);
        }
        // load user styles
        if(isset($config_cascade['userstyle'][$mediatype])){
            $files[$mediatype][$config_cascade['userstyle'][$mediatype]] = DOKU_BASE;
        }
        // load rtl styles
        // note: this adds the rtl styles only to the 'screen' media type
        // @deprecated 2012-04-09: rtl will cease to be a mode of its own,
        //     please use "[dir=rtl]" in any css file in all, screen or print mode instead
        if ($mediatype=='screen') {
            if($lang['direction'] == 'rtl'){
                if (isset($tplstyles['rtl'])) $files[$mediatype] = array_merge($files[$mediatype], $tplstyles['rtl']);
                if (isset($config_cascade['userstyle']['rtl'])) $files[$mediatype][$config_cascade['userstyle']['rtl']] = DOKU_BASE;
            }
        }

        $cache_files = array_merge($cache_files, array_keys($files[$mediatype]));
    }

    // check cache age & handle conditional request
    // This may exit if a cache can be used
    http_cached($cache->cache,
                $cache->useCache(array('files' => $cache_files)));

    // start output buffering
    ob_start();

    // build the stylesheet
    foreach ($mediatypes as $mediatype) {

        // print the default classes for interwiki links and file downloads
        if ($mediatype == 'screen') {
            print '@media screen {';
            css_interwiki();
            css_filetypes();
            print '}';
        }

        // load files
        $css_content = '';
        foreach($files[$mediatype] as $file => $location){
            $css_content .= css_loadfile($file, $location);
        }
        switch ($mediatype) {
            case 'screen':
                print NL.'@media screen { /* START screen styles */'.NL.$css_content.NL.'} /* /@media END screen styles */'.NL;
                break;
            case 'print':
                print NL.'@media print { /* START print styles */'.NL.$css_content.NL.'} /* /@media END print styles */'.NL;
                break;
            case 'all':
            case 'feed':
            default:
                print NL.'/* START rest styles */ '.NL.$css_content.NL.'/* END rest styles */'.NL;
                break;
        }
    }
    // end output buffering and get contents
    $css = ob_get_contents();
    ob_end_clean();

    // apply style replacements
    $css = _css_applystyle($css,$tplinc);

    // place all @import statements at the top of the file
    $css = css_moveimports($css);

    // compress whitespace and comments
    if($conf['compress']){
        $css = css_compress($css);
    }

    // embed small images right into the stylesheet
    if($conf['cssdatauri']){
        $base = preg_quote(DOKU_BASE,'#');
        $css = preg_replace_callback('#(url\([ \'"]*)('.$base.')(.*?(?:\.(png|gif)))#i','css_datauri',$css);
    }

    http_cached_finish($cache->cache, $css);
}

function _css_applystyle($css,$tplinc){
    $styleini = _css_styleini($tplinc);

    if($styleini){
        $css = strtr($css,$styleini['replacements']);
    }
    return $css;
}
function _css_styleini($tplinc) {
    $styleini = array();

    foreach( $tplinc as $ini) {
        $tmp = (@file_exists($ini)) ? parse_ini_file($ini, true) : array();

        foreach($tmp as $key => $value) {
            if(array_key_exists($key, $styleini) && is_array($value)) {
                $styleini[$key] = array_merge($styleini[$key], $tmp[$key]);
            } else {
                $styleini[$key] = $value;
            }
        }
    }
    return $styleini;
}
function _css_getpath( $t, $file ) {
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
    echo "/* _css_getpath: include($file): $include */\n";

    return $include; 
}

//Setup VIM: ex: et ts=4 :
