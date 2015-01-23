<?php

// Initial version of this plugin: https://github.com/atallo/ttrss_fullpost/
// Now with preference panel, ripped out of: https://github.com/mbirth/ttrss_plugin-af_feedmod
// Relies on PHP-Readability: https://github.com/feelinglucky/php-readability

// This Version is changed by ManuelW to get ALL! feeds with fulltext

// Note that this will consider the feed to match if the feed's "link" URL contains any
// element's text. Most notably, Destructoid's posts are linked through Feedburner, and
// so "destructoid.com" doesn't match--but there is a "Destructoid" in the Feedburner URL,
// so "destructoid" will. (Link comparisons are case-insensitive.)

class Af_Fullpost extends Plugin implements IHandler
{
	private $host;

	function about() {
		return array(0.01,
			"Full post for ALL articles (requires CURL).",
			"ManuelW");
	}
	
	function api_version() {
		return 2;
	}

	function init($host) {
		$this->host = $host;

		$host->add_hook($host::HOOK_ARTICLE_FILTER, $this);
	}
	
	function hook_article_filter($article) {
		if (!function_exists("curl_init"))
			return $article;
		
		$owner_uid = $article['owner_uid'];
		
		// 2/23/14: stripos() is a case-insensitive version of strpos()
		if (strpos($article['plugin_data'], "fullpost,$owner_uid:") !== false) {
			// do not process an article more than once
			if (isset($article['stored']['content'])) $article['content'] = $article['stored']['content'];
			break;
		}
		
		// 6/16/14: try/catch the Readbility call, in case it fails
		try {
			$article['content'] = $this->get_full_post($article['link']);
			$article['plugin_data'] = "fullpost,$owner_uid:" . $article['plugin_data'];
		} catch (Exception $e) {
			// Readability failed to parse the page (?); don't process this article and keep going
		}
		
		return $article;
	}
	
	private function get_full_post($request_url) {
		// https://github.com/feelinglucky/php-readability
		
		include_once 'Readability.inc.php';
		
		$handle = curl_init();
		curl_setopt_array($handle, array(
			CURLOPT_USERAGENT => USER_AGENT,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HEADER  => false,
			CURLOPT_HTTPGET => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_URL => $request_url
		));
		
		$source = curl_exec($handle);
		curl_close($handle);
		
		//if (!$charset = mb_detect_encoding($source)) {
		//}
		preg_match("/charset=([\w|\-]+);?/", $source, $match);
		$charset = isset($match[1]) ? $match[1] : 'utf-8';
		
		$Readability = new Readability($source, $charset);
		$Data = $Readability->getContent();
		
		//$title   = $Data['title'];
		//$content = $Data['content'];
		
		return $Data['content'];
	}
	
	
	function hook_prefs_tabs($args)
	{
		print '<div id="fullpostConfigTab" dojoType="dijit.layout.ContentPane"
					href="backend.php?op=af_fullpost"
					title="' . __('FullPost') . '"></div>';
	}
	
	function index()
	{
		$pluginhost = PluginHost::getInstance();
	}
	
	function csrf_ignore($method)
	{
		$csrf_ignored = array("index", "edit");
		return array_search($method, $csrf_ignored) !== false;
	}

	function before($method)
	{
		if ($_SESSION["uid"]) {
			return true;
		}
		return false;
	}
	
	function after()
	{
		return true;
	}

}
?>
