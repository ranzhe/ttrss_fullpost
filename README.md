ttrss_fullpost
==============

A Tiny Tiny RSS plugin to convert feeds with partial posts into feeds with full posts. 
Relies on PHP-Readability to suss out the full article text. 

This Plugin process ALL articles, except this in the preferences excluded.
You can specify which feeds should NOT be processed.

Second thing, it repairs some img links. Some sites do: img src="//www.site.com" for safe to get images with http and https.
So some images wont shown in mobile apps. This plugin repairs the links by replace // with http://

Installation
------------------------

Create an "af_fullpost" folder in your TT-RSS "plugins" folder. 
Put copies of "init.php", "JSLikeHTMLElement.php" and "Readability.php" into that folder.


Configuration
------------------------
In the TT-RSS preferences, you should now find a new tab called "Exclude FullPost." 
In that tab is a giant text field, where you can specify the feeds you want to EXCLUDE through PHP-Readability comma-separated:

site1.com, site2.org, site3.de

Note that this will consider the feed to match if the feed's "link" URL contains any element's text. 


References
------------------------
The original version of this (and all credit for the idea): https://github.com/atallo/ttrss_fullpost

The preference pane code was pretty much ripped wholesale from: https://github.com/mbirth/ttrss_plugin-af_feedmod

Relies on PHP-Readability by fivefilters.org: http://code.fivefilters.org/php-readability/
