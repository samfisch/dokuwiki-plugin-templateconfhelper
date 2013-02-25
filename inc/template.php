<?php
/**
 * DokuWiki plugin template functions
 *
 * @license    GPL3 (http://www.gnu.org/licenses/gpl.html)
 * @author     Samuel Fischer <sf@notomorrow.de>
 */

/**
 * include a template file
 */

function tpl_include( $file, $t=false, $allowphp=true ) {
    global $conf, $ID, $INFO;
    static $included_templates;
    if( !is_array( $included_templates )) $included_templates = array( );

    if( !$t ) { $t = $conf['template']; }
    if( !$t || ( !$include = getConfigPath( 'template_dir', $t.'/'.$file )) || in_array( $include, $included_templates )) {
        if( $t != $conf['default_tpl'] )
            $include = getConfigPath( 'template_dir', $conf['default_tpl'].'/'.$file );
    }
    
    if( !$include || in_array( $include, $included_templates )) {
	$include = getConfigPath( 'template_dir', $conf['base_tpl'].'/'.$file );
    }
    if( !$include ) return false;

    if( $allowphp || $conf['tpl_allowphp'] ) {
        $included_templates[] = $include;
        include( $include );
    } else {
        // TODO, read file 
    }
    return $include;

}
