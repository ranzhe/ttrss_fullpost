<?php

// Initial version of this plugin: https://github.com/atallo/ttrss_fullpost/
// Now with preference panel, ripped out of: https://github.com/mbirth/ttrss_plugin-af_feedmod
// Relies on PHP-Readability: https://github.com/feelinglucky/php-readability

// Expects the preference field to be valid JSON, which for arrays means we need square braces:
// [
//	 "kotaku.com",
//	 "destructoid",
//	 "arstechnica.com"
// ]

// Note that this will consider the feed to match if the feed's "link" URL contains any
// element's text. Most notably, Destructoid's posts are linked through Feedburner, and
// so "destructoid.com" doesn't match--but there is a "Destructoid" in the Feedburner URL,
// so "destructoid" will. (Link comparisons are case-insensitive.)

class Af_Fullpost extends Plugin implements IHandler
{
	private $host;

	function about() {
		return array(0.04,
			"Full post (requires CURL).",
			"atallo");
	}
	
	function api_version() {
		return 2;
	}

	function init($host) {
		$this->host = $host;

		$host->add_hook($host::HOOK_PREFS_TABS, $this);
		$host->add_hook($host::HOOK_ARTICLE_FILTER, $this);
	}
	
	function hook_article_filter($article) {
		if (!function_exists("curl_init"))
			return $article;
		
		$json_conf = $this->host->get($this, 'json_conf');
		$owner_uid = $article['owner_uid'];
		$data = json_decode($json_conf, true);
		
		if (!is_array($data)) {
			// no valid JSON or no configuration at all
			return $article;
		}
		
		foreach ($data as $urlpart) {
			// 2/23/14: stripos() is a case-insensitive version of strpos()
			if (stripos($article['link'], $urlpart) === false) continue; // skip this entry, if the URL doesn't match
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
			break;
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
		$json_conf = $pluginhost->get($this, 'json_conf');
		
		print "<form dojoType=\"dijit.form.Form\">";
		
		print "<script type=\"dojo/method\" event=\"onSubmit\" args=\"evt\">
			evt.preventDefault();
			if (this.validate()) {
				new Ajax.Request('backend.php', {
					parameters: dojo.objectToQuery(this.getValues()),
					onComplete: function(transport) {
						if (transport.responseText.indexOf('error')>=0) notify_error(transport.responseText);
						else notify_info(transport.responseText);
					}
				});
				//this.reset();
			}
			</script>";
		
		print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"op\" value=\"pluginhandler\">";
		print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"method\" value=\"save\">";
		print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"plugin\" value=\"af_fullpost\">";
		
		print "<table width='100%'><tr><td>";
		print "<textarea dojoType=\"dijit.form.SimpleTextarea\" name=\"json_conf\" style=\"font-size: 12px; width: 99%; height: 500px;\">$json_conf</textarea>";
		print "</td></tr></table>";
		
		print "<p><button dojoType=\"dijit.form.Button\" type=\"submit\">".__("Save")."</button>";
		
		print "</form>";
	}
	
	function save()
	{
		$json_conf = $_POST['json_conf'];
		
		if (is_null(json_decode($json_conf))) {
			echo __("error: Invalid JSON!");
			return false;
		}
		
		$this->host->set($this, 'json_conf', $json_conf);
		echo __("Configuration saved.");
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
