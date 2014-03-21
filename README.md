ttrss_fullpost
==============

A Tiny Tiny RSS plugin to convert feeds with partial posts into feeds with full posts. Relies on PHP-Readability to suss out the full article text, and (new!) now features a tab in the TT-RSS preferences where you can specify which feeds should be processed.


Installation
------------------------

Create an "af_fullpost" folder in your TT-RSS "plugins" folder. Put copies of both "init.php" and "Readability.inc.php" into that folder.


Configuration
------------------------
In the TT-RSS preferences, you should now find a new tab called "FullPost." In that tab is a giant text field, where you can specify the feeds you want to run through PHP-Readability in a JSON array:

    [
      "kotaku.com",
      "destructoid",
      "arstechnica.com"
    ]

Note that this will consider the feed to match if the feed's "link" URL contains any element's text. Most notably, Destructoid's posts are linked through Feedburner, and so "destructoid.com" doesn't match--but there is a "Destructoid" in the Feedburner URL, so "destructoid" will. (Link comparisons are case-insensitive.)


References
------------------------
The original version of this (and all credit for the idea): https://github.com/atallo/ttrss_fullpost

The preference pane code was pretty much ripped wholesale from: https://github.com/mbirth/ttrss_plugin-af_feedmod

PHP-Readability is from: https://github.com/feelinglucky/php-readability
