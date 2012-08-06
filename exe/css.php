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
    css_out_tfh();
}


// ---------------------- functions ------------------------------

function css_out_tfh(){
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

    // The generated script depends on some dynamic options
    $cache = new cache('styles'.$_SERVER['HTTP_HOST'].$_SERVER['SERVER_PORT'].DOKU_BASE.$cache.$mediatype,'.css');

    // load template styles
    $tplstyles = array();
    $style_ini = css_getpath( $tpl, 'style.ini' );
    if(@file_exists($style_ini)){
        $ini = parse_ini_file($style_ini,true);
        foreach($ini['stylesheets'] as $file => $mode){
            $tplstyles[$mode][css_getpath( $tpl, $file )] = $tpldir;
        }
    }

    // start output buffering
    ob_start();

    foreach($mediatypes as $mediatype) {
        // Array of needed files and their web locations, the latter ones
        // are needed to fix relative paths in the stylesheets
        $files   = array();
        // load core styles
        $files[DOKU_INC.'lib/styles/'.$mediatype.'.css'] = DOKU_BASE.'lib/styles/';
        // load jQuery-UI theme
        if ($mediatype == 'screen') {
            $files[DOKU_INC.'lib/scripts/jquery/jquery-ui-theme/smoothness.css'] = DOKU_BASE.'lib/scripts/jquery/jquery-ui-theme/';
        }
        // load plugin styles
        $files = array_merge($files, css_pluginstyles($mediatype));
        // load template styles
        if (isset($tplstyles[$mediatype])) {
            $files = array_merge($files, $tplstyles[$mediatype]);
        }
        // if old 'default' userstyle setting exists, make it 'screen' userstyle for backwards compatibility
        if (isset($config_cascade['userstyle']['default'])) {
            $config_cascade['userstyle']['screen'] = $config_cascade['userstyle']['default'];
        }
        // load user styles
        if(isset($config_cascade['userstyle'][$mediatype])){
            $files[$config_cascade['userstyle'][$mediatype]] = DOKU_BASE;
        }
        // load rtl styles
        // note: this adds the rtl styles only to the 'screen' media type
        // @deprecated 2012-04-09: rtl will cease to be a mode of its own,
        //     please use "[dir=rtl]" in any css file in all, screen or print mode instead
        if ($mediatype=='screen') {
            if($lang['direction'] == 'rtl'){
                if (isset($tplstyles['rtl'])) $files = array_merge($files, $tplstyles['rtl']);
            }
        }

        $cache_files = array_merge(array_keys($files), getConfigFiles('main'));
        $cache_files[] = $tplinc.'style.ini';
        $cache_files[] = __FILE__;

        // check cache age & handle conditional request
        // This may exit if a cache can be used
        http_cached($cache->cache,
                    $cache->useCache(array('files' => $cache_files)));

        // build the stylesheet

        // print the default classes for interwiki links and file downloads
        if ($mediatype == 'screen') {
            css_interwiki();
            css_filetypes();
        }

        // load files
        $css_content = '';
        foreach($files as $file => $location){
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
    $css = css_applystyle_tfh($css,$tpl);   // removed tplinc

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

function css_applystyle_tfh($css, $tpl ){
    global $conf;

      if( !$file = getConfigPath( 'template_dir', $tpl.'/style.ini' ))
        $file = getConfigPath( 'template_dir', $conf['default_tpl'].'/style.ini' );
    if(@file_exists($file)){
        $ini = parse_ini_file($file,true);
        $css = strtr($css,$ini['replacements']);
    }

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
    echo "/* css_getpath: include($file): $include */\n";

    return $include; 

}
