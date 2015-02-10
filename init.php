<?php
// Initial version of this plugin: https://github.com/atallo/ttrss_fullpost/
// Relies on PHP-Readability by fivefilters.org: http://code.fivefilters.org/php-readability/

// This Version is changed by ManuelW to get ALL! feeds with fulltext, except this in comma separated list

// Start Code
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

		$host->add_hook($host::HOOK_PREFS_TABS, $this);
		$host->add_hook($host::HOOK_ARTICLE_FILTER, $this);
	}
	
	function hook_article_filter($article) {
		if (!function_exists("curl_init"))
			return $article;

		// do not process an article more than once
		if (strpos($article['plugin_data'], "fullpost,$owner_uid:") !== false) {
			if (isset($article['stored']['content'])) $article['content'] = $article['stored']['content'];
			break;
		}

		$json_conf = $this->host->get($this, 'json_conf');
		$showInfoEnabled = sql_bool_to_bool($this->host->get($this, "af_fullpost_showinfo", bool_to_sql_bool(TRUE)));
		
		$owner_uid = $article['owner_uid'];

		// get url's for exclusion
		$data = explode(",", $json_conf);
		
		try {
			// if there is some stuff in the array
			if (is_array($data)) {
				// check url for excluded
				foreach ($data as $urlpart) {
					$urlpart = trim(str_replace("\n", "", $urlpart));
					if (stripos($article['link'], $urlpart) !== false) {
						$check_content = "Skipped";
						break;
					}
					else {
						$check_content = $this->get_full_post($article['link']);
					}
				}
			}
			// if the array is empty
			else {
				$check_content = $this->get_full_post($article['link']);
			}
			
			// Print some information if content was processed by readability if enabled
			if ($showInfoEnabled) {
				if ($check_content != "Failed" && $check_content != "Skipped" && $check_content != "") {
					$article['content'] = $check_content . "<br>Processed by Readability";
				}
				elseif ($check_content == "Skipped") {
					$article['content'] = $article['content'] . "<br>Skipped Readability";
				}
				else {
					$article['content'] = $article['content'] . "<br>Failed Processed by Readability";
				}
			}
			
			// mark article as processed
			$article['plugin_data'] = "fullpost,$owner_uid:" . $article['plugin_data'];
			
		} catch (Exception $e) {
			// Readability failed to parse the page (?); don't process this article and keep going
			// mark article as processed
			$article['content'] = $article['content'] . "<br>ERROR Processing by Readability<br>" . $e;
			$article['plugin_data'] = "fullpost,$owner_uid:" . $article['plugin_data'];
		}

		// clean links without http, some sites do <img src="//www.site.com"> for safe to get images with http and https
		$toClean = array("\"//");
		$article["content"] = str_replace($toClean, "\"http://", $article["content"], $count);
		if ($showInfoEnabled) {
			$article['content'] = $article['content'] . " + " . $count . " Replacements";
		}
		
		return $article;
	}
	
	private function get_full_post($request_url) {
		try {
			try {
				$handle = curl_init();
				curl_setopt_array($handle, array(
					CURLOPT_USERAGENT => "Tiny Tiny RSS",
					CURLOPT_FOLLOWLOCATION => true,
					CURLOPT_HEADER  => false,
					CURLOPT_HTTPGET => true,
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_TIMEOUT => 30,
					CURLOPT_URL => $request_url
				));
	
				$source = curl_exec($handle);
				curl_close($handle);
			}
			catch (Exception $e) {
				$source = file_get_contents($request_url);
			}

			// fix encoding -> done by itohsnap: https://github.com/itohsnap/ttrss_fullpost/commit/815e163b724fbfb426eff43bde6c3aa744a22ae5
			preg_match("/charset=([\w|\-]+);?/", $source, $match);
			$charset = isset($match[1]) ? $match[1] : 'utf-8';
			$source = mb_convert_encoding($source, 'UTF-8', $charset);

			// Clean with tidy, if exists
			if (function_exists('tidy_parse_string')) {
				$tidy = tidy_parse_string($source, array(), 'UTF8');
				$tidy->cleanRepair();
				$source = $tidy->value;
			}

			// get the Text
			require_once 'Readability.php';
			$readability = new Readability($source);
			$readability->debug = false;
			$readability->convertLinksToFootnotes = false;
			$result = $readability->init();
			$content = $readability->getContent()->innerHTML;
			// if we've got Tidy, let's clean it up for output
			if (function_exists('tidy_parse_string')) {
				$tidy = tidy_parse_string($content, array('indent'=>true, 'show-body-only' => true), 'UTF8');
				$tidy->cleanRepair();
				$content = $tidy->value;
			}

			$Data['content'] = $content;	
		}
		catch (Exception $e) {
			// do nothing if it dont grep fulltext succesfully
		}
		
		return $Data['content'];
	}
	
	
	function hook_prefs_tabs($args)
	{
		print '<div id="fullpostConfigTab" dojoType="dijit.layout.ContentPane"
					href="backend.php?op=af_fullpost"
					title="' . __('Exclude FullPost') . '"></div>';
	}
	
	function index()
	{
		$pluginhost = PluginHost::getInstance();
		$json_conf = $pluginhost->get($this, 'json_conf');
		
		$showInfoEnabled = $pluginhost->get($this, 'af_fullpost_showinfo');
		if ($showInfoEnabled) {
			$fullPostChecked = "checked=\"1\"";
		} else {
			$fullPostChecked = "";
		}
		
		print "<p>Comma-separated list of web addresses, for which you don't would fetch the full post.<br>Example: site1.com, site2.org, site3.de</p>";
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
		
		print "Show processed by Readability info on article bottom: <input dojoType=\"dijit.form.CheckBox\" type=\"checkbox\" name=\"af_fullpost_showinfo\" id=\"af_fullpost_showinfo\" $fullPostChecked>";
		print "</tr></td>";
		print "<tr><td>";
		
		print "<textarea dojoType=\"dijit.form.SimpleTextarea\" name=\"json_conf\" style=\"font-size: 12px; width: 99%; height: 500px;\">$json_conf</textarea>";
		print "</td></tr></table>";
		
		print "<p><button dojoType=\"dijit.form.Button\" type=\"submit\">".__("Save")."</button>";
		
		print "</form>";
	}

	function save()
	{
		$json_conf = $_POST['json_conf'];
		$this->host->set($this, 'json_conf', $json_conf);
		$this->host->set($this, "af_fullpost_showinfo", checkbox_to_sql_bool($_POST["af_fullpost_showinfo"]));
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
