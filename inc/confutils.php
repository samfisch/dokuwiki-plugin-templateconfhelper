<?php
/**
 * DokuWiki plugin config functions
 *
 * @license    GPL3 (http://www.gnu.org/licenses/gpl.html)
 * @author     Samuel Fischer <sf@notomorrow.de>
 */


/**
 * return the path for non overriding config files
 *
 * @author Samuel Fischer <sf@notomorrow.de>
 *
 * @param  string   $type     the configuration settings to be read, must correspond to a key/array in $config_cascade
 * @param  string   $file     the name of the wanted file
 * @return string             the full path to the first occurence of the file, searching in this order: protected, local, default
 */
function getConfigPath($type, $file) {
  global $config_cascade;

  if (!is_array($config_cascade[$type])) trigger_error('Missing config cascade for "'.$type.'"',E_USER_WARNING);
  foreach (array('protected', 'local','default') as $config_group) {
    if (empty($config_cascade[$type][$config_group])) continue;
    foreach( $config_cascade[$type][$config_group] as $path ) {
## DEBUG
#echo "check $path$file<br>\n";
        if( file_exists( $path.$file )) {
#echo "return $path$file<br>\n";
            return $path.$file;
        }
    }
  }
}

