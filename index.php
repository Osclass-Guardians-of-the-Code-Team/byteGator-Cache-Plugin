<?php
/*
Plugin Name: Cache Plugin
Plugin URI: http://www.osclass.org/
Description: Cache system for OSClass, make your website load faster!
Version: 3.0.0
Author: OSClass
Author URI: http://www.osclass.org/
Short Name: cache_plugin
Plugin update URI: 
*/

	function cacheplugin_subdomain() {
    $current_host = parse_url($_SERVER['HTTP_HOST'], PHP_URL_HOST);
    if( $current_host === null ) {
      $current_host = $_SERVER['HTTP_HOST'];
    }
    return $current_host;
	}

	if(!function_exists('osc_item_is_enabled')) {
    function osc_item_is_enabled() {
      return (osc_item_field("b_enabled")==1);
    }
	}

	function cacheplugin_recursiveRemove($dir) {
		$structure = glob(rtrim($dir, "/").'/*');
    if (is_array($structure)) {
      foreach($structure as $file) {
        if (is_dir($file)) cacheplugin_recursiveRemove($file);
        elseif (is_file($file)) unlink($file);
      }
    }
    rmdir($dir);
	}

  function cacheplugin_install() {
		@mkdir(osc_content_path().'uploads/cache_files/', 0777, true);

    osc_set_preference('upload_path', osc_content_path().'uploads/cache_files/', 'cacheplugin', 'STRING');
    osc_set_preference('main_time', '1', 'cacheplugin', 'INTEGER');
    osc_set_preference('item_time', '24', 'cacheplugin', 'INTEGER');
    osc_set_preference('static_time', '24', 'cacheplugin', 'INTEGER');
    osc_set_preference('main_cache', 'active', 'cacheplugin', 'INTEGER');
    osc_set_preference('item_cache', 'active', 'cacheplugin', 'INTEGER');
    osc_set_preference('static_cache', 'active', 'cacheplugin', 'INTEGER');
    osc_set_preference('posted_item_clean_cache', 'active', 'cacheplugin', 'INTEGER');
    osc_set_preference('item_storage_folder', 'Y-m-d', 'cacheplugin', 'STRING');
  }

  function cacheplugin_uninstall() {
    osc_delete_preference('upload_path', 'cacheplugin');
    osc_delete_preference('item_time', 'cacheplugin');
    osc_delete_preference('main_time', 'cacheplugin');
    osc_delete_preference('static_time', 'cacheplugin');
    osc_delete_preference('main_cache', 'cacheplugin');
    osc_delete_preference('item_cache', 'cacheplugin');
    osc_delete_preference('static_cache', 'cacheplugin');
    osc_delete_preference('posted_item_clean_cache', 'cacheplugin');
    osc_delete_preference('item_storage_folder', 'cacheplugin');

    $dir = osc_content_path().'uploads/cache_files/'; // IMPORTANT: with '/' at the end
    cacheplugin_recursiveRemove($dir);
  }
  
  global $cacheplugin_docache;

	if(!function_exists('cacheplugin_cache_start')) {
    function cacheplugin_cache_start() {
      global $cacheplugin_docache;

      $cacheplugin_docache = false;

      if (osc_is_web_user_logged_in()) return;
      $flashmessage = Session::newInstance()->_getMessage('pubMessages');
      if (isset($flashmessage['msg']) && $flashmessage['msg'] != '') {
        return;
      }  
      if ($flashmessage != '') {
        return;
      }
      if (osc_is_home_page()) {
        if (osc_get_preference('main_cache', 'cacheplugin') == 'active') {
          $cachefile = osc_content_path().'uploads/cache_files/main/'.cacheplugin_subdomain().'.html';
          $cachetime = osc_get_preference('main_time', 'cacheplugin')*3600;
          if (file_exists($cachefile) && (time() - $cachetime < filemtime($cachefile))) {
            include($cachefile);
            echo "<!-- Cached ".date('jS F Y H:i', filemtime($cachefile))." -->";
            exit;
          }
          $cacheplugin_docache = true;
          ob_start();
        }

      } elseif (osc_is_ad_page()) {
        if (osc_get_preference('item_cache', 'cacheplugin')== 'active' && !osc_item_is_spam() && osc_item_is_active() && osc_item_is_enabled()) {
          $ItemStorageFolder = osc_get_preference('item_storage_folder', 'cacheplugin') ;
          $PubbDate = osc_item_pub_date();
          $DatePubb = date_create($PubbDate);
          $cachePubbDate = date_format($DatePubb, $ItemStorageFolder);
          $cachetitle = osc_item_id();
          $cachefile = osc_content_path().'uploads/cache_files/item/'.$cachePubbDate.'/'.$cachetitle.'.html';
          $cachetime = osc_get_preference('item_time', 'cacheplugin')*3600;
          if (file_exists($cachefile) && (time() - $cachetime < filemtime($cachefile))) {
            include($cachefile);
            echo "<!-- Cached ".date('jS F Y H:i', filemtime($cachefile))." -->";
            exit;
          }
          $cacheplugin_docache = true;
          ob_start();
        }

      } elseif (osc_is_static_page() || osc_get_osclass_location() == 'contact') {
        if (osc_get_preference('static_cache', 'cacheplugin') == 'active') {
          $cachetime = osc_get_preference('static_time', 'cacheplugin')*3600;

          if(osc_get_osclass_location() == 'contact') {
            $cachefile = osc_content_path().'uploads/cache_files/static/contact.html';
          } else {
            $page = Page::newInstance()->findByPrimaryKey(Params::getParam('id'));
            $cachetitle = $page['pk_i_id'];
            $cachefile = osc_content_path().'uploads/cache_files/static/'.$cachetitle.'.html';
          }

          if (file_exists($cachefile) && (time() - $cachetime < filemtime($cachefile))) {
            include($cachefile);
            echo "<!-- Cached ".date('jS F Y H:i', filemtime($cachefile))." -->";
            exit;
          }
          $cacheplugin_docache = true;
          ob_start();
        }  
      }
    }
  }

	if(!function_exists('cacheplugin_cache_end')) {
  	function cacheplugin_cache_end() {

      global $cacheplugin_docache;
      
      if ($cacheplugin_docache) {
        if (osc_is_home_page()) {
          $cachefile = osc_content_path().'uploads/cache_files/main/'.cacheplugin_subdomain().'.html';
          $cachetime = osc_get_preference('main_time', 'cacheplugin')*3600;

          @mkdir(osc_content_path().'uploads/cache_files/main/', 0777, true);
          // open the cache file for writing
          $fp = fopen($cachefile, 'w');
          // save the contents of output buffer to the file
          fwrite($fp, ob_get_contents());
          // close the file
          fclose($fp);
          // Send the output to the browser
          ob_end_flush();

        } elseif (osc_is_ad_page()) {
          $ItemStorageFolder = osc_get_preference('item_storage_folder', 'cacheplugin') ;
          $PubbDate = osc_item_pub_date();
          $DatePubb = date_create($PubbDate);
          $cachePubbDate = date_format($DatePubb, $ItemStorageFolder);
          $cachetitle = osc_item_id();
          $cachefile = osc_content_path().'uploads/cache_files/item/'.$cachePubbDate.'/'.$cachetitle.'.html';
          $cachetime = osc_get_preference('item_time', 'cacheplugin')*3600;

          @mkdir(osc_content_path().'uploads/cache_files/item/'.$cachePubbDate.'/', 0777, true);
          // open the cache file for writing
          $fp = fopen($cachefile, 'w');
          // save the contents of output buffer to the file
          fwrite($fp, ob_get_contents());
          // close the file
          fclose($fp);
          // Send the output to the browser
          ob_end_flush();

        } elseif (osc_is_static_page() || osc_get_osclass_location() == 'contact') {
          $cachetime = osc_get_preference('static_time', 'cacheplugin')*3600;

          if(osc_get_osclass_location() == 'contact') {
            $cachefile = osc_content_path().'uploads/cache_files/static/contact.html';
          } else {
            $page = Page::newInstance()->findByPrimaryKey(Params::getParam('id'));
            $cachetitle = $page['pk_i_id'];
            $cachefile = osc_content_path().'uploads/cache_files/static/'.$cachetitle.'.html';
          }

          @mkdir(osc_content_path().'uploads/cache_files/static/', 0777, true);
          // open the cache file for writing
          $fp = fopen($cachefile, 'w');
          // save the contents of output buffer to the file
          fwrite($fp, ob_get_contents());
          // close the file
          fclose($fp);
          // Send the output to the browser
          ob_end_flush(); 
        }
			}
		}
	}

  function cacheplugin_add_comment($item) {
    $ItemStorageFolder = osc_get_preference('item_storage_folder', 'cacheplugin') ;
    $PubbDate = osc_item_pub_date($item);
    $DatePubb = date_create($PubbDate);
    $cachePubbDate = date_format($DatePubb, $ItemStorageFolder);
    $IdItem = osc_item_id($item);
    $files = rglob(osc_content_path().'uploads/cache_files/item/'.$cachePubbDate.'/'.$IdItem.'.html');
    foreach($files as $f) {
      @unlink($f);
    }
  }

  function cacheplugin_item_edit_post($item) {
    $ItemStorageFolder = osc_get_preference('item_storage_folder', 'cacheplugin') ;
    $PubbDate = osc_item_pub_date($item);
    $DatePubb = date_create($PubbDate);
    $cachePubbDate = date_format($DatePubb, $ItemStorageFolder);
    $IdItem = osc_item_id($item);
    $files = rglob(osc_content_path().'uploads/cache_files/item/'.$cachePubbDate.'/'.$IdItem.'.html');
    foreach($files as $f) {
      @unlink($f);
    }
  }

  function cacheplugin_delete_item($id) {
    $ItemStorageFolder = osc_get_preference('item_storage_folder', 'cacheplugin') ;
    $PubbDate = osc_item_pub_date($id);
    $DatePubb = date_create($PubbDate);
    $cachePubbDate = date_format($DatePubb, $ItemStorageFolder);
    $files = rglob(osc_content_path().'uploads/cache_files/item/'.$cachePubbDate.'/'.$id.'.html');
    foreach($files as $f) {
      @unlink($f);
    }
    $files = rglob(osc_content_path().'uploads/cache_files/main/*');
    foreach($files as $f) {
      @unlink($f);
    }
  }

  function cacheplugin_posted_item($item){
    if ($item['fk_i_user_id'] == 0) 
      return;
    
    $files = rglob(osc_content_path().'uploads/cache_files/main/*');
    foreach($files as $f) {
      @unlink($f);
    }
  }

  function cacheplugin_clear_item() {
    $files = rglob(osc_content_path().'uploads/cache_files/item/');
    foreach($files as $f) {
      cacheplugin_recursiveRemove($f);
    }
  }

  function cacheplugin_clear_static() {
    $files = rglob(osc_content_path().'uploads/cache_files/static/*');
    foreach($files as $f) {
      @unlink($f);
    }
  }
                
  function cacheplugin_clear_main() {
    $files = rglob(osc_content_path().'uploads/cache_files/main/*');
    foreach($files as $f) {
      @unlink($f);
    }
  }

  function cacheplugin_clear_all() {
    cacheplugin_clear_item();
    cacheplugin_clear_static();
    cacheplugin_clear_main();
  }

  function cacheplugin_edit_comment($id) {
    $conn = getConnection();
    $ItemIds = $conn->osc_dbFetchResult("SELECT fk_i_item_id FROM %st_item_comment WHERE pk_i_id = %d", DB_TABLE_PREFIX, $id);
    $ItemId = $ItemIds['fk_i_item_id'];
    $PubbDate = osc_item_pub_date($ItemId['fk_i_item_id']);
    $DatePubb = date_create($PubbDate);
    $cachePubbDate = date_format($DatePubb, 'Y-m-d');
    $files = rglob(osc_content_path().'uploads/cache_files/item/'.$cachePubbDate.'/'.$ItemId.'.html');
    foreach($files as $f) {
      @unlink($f);
    }
  }
		 
	// This can be removed once we no longer support Osclass 310 and older.
  function cacheplugin_admin_menu_old() {
    echo '<h3><a href="#">Cache Plugin</a></h3>
      <ul>
        <li><a href="' . osc_admin_render_plugin_url(osc_plugin_folder(__FILE__) . 'admin/conf.php') . '">&raquo; ' . __('Settings', 'cacheplugin') . '</a></li>
        <li><a href="' . osc_admin_render_plugin_url(osc_plugin_folder(__FILE__) . 'admin/help.php') . '">&raquo; ' . __('Help', 'cacheplugin') . '</a></li>
      </ul>';
  }

  function cacheplugin_admin_menu() {
		osc_add_admin_submenu_divider( 'plugins', 'Cache Plugin', 'cacheplugin', $capability = null);
		osc_admin_menu_plugins( __('Settings', 'cacheplugin'), osc_admin_render_plugin_url(osc_plugin_folder(__FILE__) . 'admin/conf.php'), 'cacheplugin-setings', $capability = null, $icon_url = null );
		osc_admin_menu_plugins( __('Help', 'cacheplugin'), osc_admin_render_plugin_url(osc_plugin_folder(__FILE__) . 'admin/help.php'), 'cacheplugin-help', $capability = null, $icon_url = null );
	}

	function cacheplugin_admin_configure() {
		osc_admin_render_plugin(osc_plugin_path(osc_plugin_folder(__FILE__)) . 'admin/conf.php') ;
	}

	/**
	 * ADD HOOKS
	 */
	osc_register_plugin(osc_plugin_path(__FILE__), 'cacheplugin_install');
	osc_add_hook(osc_plugin_path(__FILE__)."_configure", 'cacheplugin_admin_configure');
	osc_add_hook(osc_plugin_path(__FILE__)."_uninstall", 'cacheplugin_uninstall');
	osc_add_hook(osc_plugin_path(__FILE__)."_disable", 'cacheplugin_clear_all');

  // hooks for create cache
  osc_add_hook('before_html', 'cacheplugin_cache_start',8);
  osc_add_hook('after_html', 'cacheplugin_cache_end',2);

  // clear cahe after item actions
  if(osc_version()<320) {
    osc_add_hook('item_edit_post', 'cacheplugin_item_edit_post');
  } else {
    osc_add_hook('edited_item', 'cacheplugin_item_edit_post');
  }

  if (osc_get_preference('posted_item_clean_cache', 'cacheplugin')== 'active') {
    if(osc_version()<320) {
      osc_add_hook('item_form_post', 'cacheplugin_posted_item');
    } else {
      osc_add_hook('posted_item', 'cacheplugin_posted_item');
    }
	}
	
	osc_add_hook('theme_activate', 'cacheplugin_clear_all'); // clear all cache when theme change

	osc_add_hook('activate_item', 'cacheplugin_delete_item');
	osc_add_hook('deactivate_item', 'cacheplugin_delete_item');
	osc_add_hook('enable_item', 'cacheplugin_delete_item');
	osc_add_hook('disable_item', 'cacheplugin_delete_item');
	osc_add_hook('delete_item', 'cacheplugin_delete_item');
	osc_add_hook('item_spam_on', 'cacheplugin_delete_item');
	osc_add_hook('item_spam_off', 'cacheplugin_delete_item');
    osc_add_hook('item_renewed', 'cacheplugin_delete_item');


	// clear cache after comment
	osc_add_hook('add_comment', 'cacheplugin_add_comment');
	osc_add_hook('activate_comment', 'cacheplugin_edit_comment');
	osc_add_hook('deactivate_comment', 'cacheplugin_edit_comment');
	osc_add_hook('enable_comment', 'cacheplugin_edit_comment');
	osc_add_hook('disable_comment', 'cacheplugin_edit_comment');
	osc_add_hook('delete_comment', 'cacheplugin_edit_comment');

	// FANCY MENU
	if(osc_version()<320) {
		osc_add_hook('admin_menu', 'cacheplugin_admin_menu_old');
	} else {
    osc_add_hook('admin_menu_init', 'cacheplugin_admin_menu');
	}

?>
