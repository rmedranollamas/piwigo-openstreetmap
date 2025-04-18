<?php
/*
Plugin Name: OpenStreetMap
Version: auto
Description: OpenStreetMap integration for piwigo
Plugin URI: http://piwigo.org/ext/extension_view.php?eid=701
Author: xbmgsharp
Author URI: https://github.com/xbgmsharp/piwigo-openstreetmap
Has Settings: webmaster
*/

// Chech whether we are indeed included by Piwigo.
if (!defined('PHPWG_ROOT_PATH')) die('Hacking attempt!');

// Define the path to our plugin.
global $conf, $prefixeTable;

define('OSM_PATH', PHPWG_PLUGINS_PATH . basename(dirname(__FILE__)).'/');

define('osm_place_table', $prefixeTable.'osm_places');

// Prepare configuration
$conf['osm_conf'] = safe_unserialize($conf['osm_conf']);

// GPX support
include_once(dirname(__FILE__).'/gpx.inc.php');

// Plugin on picture page
if (script_basename() == 'picture')
{
	include_once(dirname(__FILE__).'/picture.inc.php');
}
elseif (script_basename() == 'index')
{
    include_once(dirname(__FILE__).'/category.inc.php');
    include_once(dirname(__FILE__).'/menu.inc.php');
}

// Do we have to show a link on the left menu
if ($conf['osm_conf']['left_menu']['enabled'])
{
	// Hook to add link on the left menu
	add_event_handler('blockmanager_apply', 'osm_blockmanager_apply');
}

// Hook to add worldmap link on the album/category thumbnails
add_event_handler('loc_begin_index_category_thumbnails', 'osm_index_cat_thumbs_displayed');

// Hook to add worldmap link on the index thumbnails page
add_event_handler('loc_end_index', 'osm_end_index' );

// Hook to watch if GPX file selected to be displayed on map is deleted
add_event_handler('begin_delete_elements', 'osm_begin_delete_elements' );

function osm_index_cat_thumbs_displayed()
{
	global $page;
	$page['osm_cat_thumbs_displayed'] = true;
}

define('OSM_ACTION_MODEL', '<a href="%s" title="%s" rel="nofollow" class="pwg-state-default pwg-button"%s><span class="pwg-icon pwg-icon-globe">&nbsp;</span><span class="pwg-button-text">%s</span></a>');
function osm_end_index()
{
	global $page, $filter, $template;

	if ( isset($page['chronology_field']) || $filter['enabled'] )
		return;

	if ( 'categories' == @$page['section'])
	{ // flat or no flat ; has subcats or not;  ?
		if ( ! @$page['osm_cat_thumbs_displayed'] and empty($page['items']) )
			return;
	}
	else
	{
		if (
			!in_array( @$page['section'], array('tags','search','recent_pics','list') )
			)
			return;
		if ( empty($page['items']) )
			return;
	}

	include_once( dirname(__FILE__) .'/include/functions.php');

	if ( !empty($page['items']) )
	{
		if (!@$page['osm_items_have_latlon'] and ! osm_items_have_latlon( $page['items'] ) )
			return;
	}
	osm_load_language();

	global $conf;
	$layout = isset($conf['osm_conf']['left_menu']['layout']) ? $conf['osm_conf']['left_menu']['layout'] : '2';
	$map_url = osm_duplicate_map_index_url( array(), array('start') ) ."&v=".$layout;
	$link_title = sprintf( l10n('DISPLAY_ON_MAP'), strip_tags($page['title']) );
	$template->concat( 'PLUGIN_INDEX_ACTIONS' , "\n<li>".sprintf(OSM_ACTION_MODEL,
		$map_url, $link_title, '', 'map', l10n('MAP')
		).'</li>');
}

// If admin do the init
if (defined('IN_ADMIN')) {
	include_once(OSM_PATH.'/admin/admin_boot.php');
}

//if community active
if (isset($pwg_loaded_plugins['community']))
{
  include_once(OSM_PATH.'admin/admin_batchmanager.php');

  add_event_handler('community_loc_end_element_set_global', 'osm_loc_end_element_set_global');
  
  add_event_handler('community_element_set_global_action', 'osm_element_set_global_action');
  
}
// file containing API function
$ws_file = OSM_PATH . 'include/ws_functions.inc.php';
add_event_handler('ws_invoke_allowed', 'osm_ws_images_setInfo',
    EVENT_HANDLER_PRIORITY_NEUTRAL, $ws_file);


function osm_blockmanager_apply($mb_arr)
{
	if ($mb_arr[0]->get_id() != 'menubar' )
		return;
	if ( ($block=$mb_arr[0]->get_block('mbMenu')) != null )
	{
		include_once( dirname(__FILE__) .'/include/functions.php');
		load_language('plugin.lang', OSM_PATH);
		global $conf;
		$linkname = isset($conf['osm_conf']['left_menu']['link']) ? $conf['osm_conf']['left_menu']['link'] : l10n('OSWorldMap');
		$layout = isset($conf['osm_conf']['left_menu']['layout']) ? $conf['osm_conf']['left_menu']['layout'] : '2';
		$link_title = sprintf( l10n('DISPLAY_ON_MAP'), strip_tags($conf['gallery_title']) );
		$block->data['osm'] = array(
			'URL' => osm_make_map_index_url( array('section'=>'categories') ) ."&v=".$layout,
			'TITLE' => $link_title,
			'NAME' => $linkname,
			'REL'=> 'rel=nofollow'
		);
	}
}

function osm_strbool($value)
{
	return $value ? 'true' : 'false';
}

function osm_begin_delete_elements($ids)
{
  global $conf;

  $osm_gpx_file_to_display = $conf['osm_conf']['category_description']['display_gpx'];

  if(in_array($osm_gpx_file_to_display, $ids))
  {
    $conf['osm_conf']['category_description']['display_gpx'] = null;
    conf_update_param('osm_conf', serialize($conf['osm_conf']));
  }

}

?>
