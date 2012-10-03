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

// you are here less verbose 
function tpl_youarehere_lv($sep=' &raquo; '){
  global $conf;
  global $ID;
  global $lang;
  
  // check if enabled
  if(!$conf['youarehere']) return false;
  
  $parts = explode(':', $ID);
  $count = count($parts);
  
  echo '<span class="bchead">'.$lang['youarehere'].'</span>&nbsp;';
  if( $count > 1 ) {                                                                                                                                                            

     // always print the startpage
      $title = useHeading('navigation') ? p_get_first_heading($conf['start']) : $conf['start'];
      if(!$title) $title = $conf['start'];
      #tpl_link(wl($conf['start']),hsc($title),'title="'.$conf['start'].'"');

      // print intermediate namespace links
      $part = '';
      for($i=0; $i<$count - 1; $i++){
        $part .= $parts[$i].':';
        $page = $part;
        resolve_pageid('',$page,$exists);
        if ($page == $conf['start']) continue; // Skip startpage

        // output
	echo "<div><nobr>";
        echo $sep;
        if($exists){
          #$title = useHeading('navigation') ? p_get_first_heading($page) : $parts[$i];
          $title = $parts[$i];
          tpl_link(wl($page),hsc($title),'title="'.$page.'"');
        }else{
          tpl_link(wl($page),$parts[$i],'title="'.$page.'" class="wikilink2" rel="nofollow"');
        }
	echo "</nobr></div>";

      }
      //
      // print current page, skipping start page, skipping for namespace index
      if(isset($page) && $page==$part.$parts[$i]) return;
      $page = $part.$parts[$i];
      if($page == $conf['start']) return;
      echo "<div class='active'><nobr>";
      echo $sep;

      if(page_exists($page)){
        #$title = useHeading('navigation') ? p_get_first_heading($page) : $parts[$i];
        $title = $parts[$i];
        tpl_link(wl($page),hsc($title),'title="'.$page.'"');
      }else{
        tpl_link(wl($page),$parts[$i],'title="'.$page.'" class="wikilink2" rel="nofollow"');
      }
      echo "</nobr></div>";
  }
  
  return true;
}

// userinfo less verbose
function tpl_userinfo_lv(){
    global $lang;
    global $INFO;
    if(isset($_SERVER['REMOTE_USER'])){
        print $lang['loggedinas'].': ('.hsc($_SERVER['REMOTE_USER']).')';
        return true;
    }
    return false;
}
