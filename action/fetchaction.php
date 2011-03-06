<?
/**
 * DokuWiki plugin template helper
 *
 * @license    GPL3 (http://www.gnu.org/licenses/gpl.html)
 * @author     Samuel Fischer <sf@notomorrow.de>
 */


if(!defined('DOKU_INC')) die();

class action_plugin_templateconfhelper_fetchaction extends DokuWiki_Action_Plugin {

  function getInfo(){
    return array(
        'author' => 'Samuel Fischer',
        'email'  => 'sf',
        'date'   => '2010-02-07',
        'name'   => 'template functions',
        'desc'   => 'serv files from templatedir',
        'url'    => 'samfisch.de',
    );
  }

  function register(&$controller) {/*{{{*/

      $controller->register_hook('FETCH_MEDIA_STATUS', 'BEFORE', $this, 'fetch_action' );

  }/*}}}*/

  function fetch_action( $evt ) {/*{{{*/
    #$data = $evt->data;
    global $data, $conf, $MEDIA, $EXT, $WIDTH, $HEIGHT;

    if( isset( $_GET['mode'] ) && $_GET['mode'] == 'styleimg' ) {
        // fallthrough for other errors than not found
        if( $data['status'] != '404' ) {
            return false;
        }

	$tpl    = $_GET['template'];
	$media  = $MEDIA;   // cleaned by getID( )
        $imgdir = '/images/';

        if( !preg_match( '/^[\w-]*$/', $tpl )) {
            $data['status'] = '400';
            $data['statusmessage'] = 'Bad Syntax';
            return false;
        }

        $media = str_replace( ':', '/', $media );

        if( !$tpl || !$file = getConfigPath( 'template_dir', $tpl.$imgdir.$media )) {
          // init event seems not to be fired so manual call 
            $e = new action_plugin_templateconfhelper_templateaction( );
            $e->template_action( );

            if( $conf['template'] && $tpl != $conf['template'] ) {
                $file = getConfigPath( 'template_dir', $conf['template'].$imgdir.$media );
            } elseif( $conf['default_tpl'] && $t != $conf['default_tpl'] ) {
                $file = getConfigPath( 'template_dir', $conf['default_tpl'].$imgdir.$media );
            } else {
                $file = getConfigPath( 'template_dir', $conf['base_tpl'].$imgdir.$media );
            }
        }

        //fall through with 404
        if(!@file_exists( $file )) {
            return false;
        }

        $orig = $file;
      
      //handle image resizing/cropping
        if((substr($MIME,0,5) == 'image') && $WIDTH){
         if($HEIGHT){
            $file = media_crop_image($file,$EXT,$WIDTH,$HEIGHT);
         }else{
            $file = media_resize_image($file,$EXT,$WIDTH,$HEIGHT);
         }
       }  

       $data['status'] = '200';
       $data['statusmessage'] = null;
       $data['orig'] = $orig;
       $data['file'] = $file;
    }
  
  }/*}}}*/
}
