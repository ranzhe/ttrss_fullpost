<?php
// vim: set et sw=4 ts=4 sts=4 ft=php fdm=marker ff=unix fenc=utf8 nobomb:
/**
 * PHP Readability
 *
 * Readability PHP 版本，详见
 *      http://code.google.com/p/arc90labs-readability/
 *
 * ChangeLog:
 *      [+] 2014-02-08 Add lead image param and improved get title function.
 *      [+] 2013-12-04 Better error handling and junk tag removal.
 *      [+] 2011-02-17 初始化版本
 *
 * @date   2013-12-04
 * 
 * @author mingcheng<i.feelinglucky#gmail.com>
 * @link   http://www.gracecode.com/
 * 
 * @author Tuxion <team#tuxion.nl>
 * @link   http://tuxion.nl/
 */

define("READABILITY_VERSION", 0.21);

class Readability {
    // 保存判定结果的标记位名称
    const ATTR_CONTENT_SCORE = "contentScore";

    // DOM 解析类目前只支持 UTF-8 编码
    const DOM_DEFAULT_CHARSET = "utf-8";

    // 当判定失败时显示的内容
    const MESSAGE_CAN_NOT_GET = "Readability was unable to parse this page for content.";

    // DOM 解析类（PHP5 已内置）
    protected $DOM = null;

    // 需要解析的源代码
    protected $source = "";

    // 章节的父元素列表
    private $parentNodes = array();

    // Need to remove labels
    // Note: added extra tags from https://github.com/ridcully
    private $junkTags = Array("style", "form", "iframe", "script", "button", "input", "textarea", 
                                "noscript", "select", "option", "object", "applet", "basefont",
                                "bgsound", "blink", "canvas", "command", "menu", "nav", "datalist",
                                "embed", "frame", "frameset", "keygen", "label", "marquee", "link");

    // Need to remove the property
    private $junkAttrs = Array("style", "class", "onclick", "onmouseover", "align", "border", "margin");


    /**
     * Constructor
     *      @param $input_char 字符串的编码。默认 utf-8，可以省略
     */
    function __construct($source, $input_char = "utf-8") {
        $this->source = $source;

        // DOM parsing class can handle UTF-8 character format
        $source = mb_convert_encoding($source, 'HTML-ENTITIES', $input_char);

        // Pretreatment HTML tags, remove redundant labels
        $source = $this->preparSource($source);

        // 生成 DOM 解析类
        $this->DOM = new DOMDocument('1.0', $input_char);
        try {
            //libxml_use_internal_errors(true);
            // 会有些错误信息，不过不要紧 :^)
            if (!@$this->DOM->loadHTML('<?xml encoding="'.Readability::DOM_DEFAULT_CHARSET.'">'.$source)) {
                throw new Exception("Parse HTML Error!");
            }

            foreach ($this->DOM->childNodes as $item) {
                if ($item->nodeType == XML_PI_NODE) {
                    $this->DOM->removeChild($item); // remove hack
                }
            }

            // insert proper
            $this->DOM->encoding = Readability::DOM_DEFAULT_CHARSET;
        } catch (Exception $e) {
            // ...
        }
    }


    /**
     * Pretreatment HTML tags, so that it can be processed accurately DOM parsing classes
     *
     * @return String
     */
    private function preparSource($string) {
        // Excluding the extra HTML coding markup, avoid parsing errors
        preg_match("/charset=([\w|\-]+);?/", $string, $match);
        if (isset($match[1])) {
            $string = preg_replace("/charset=([\w|\-]+);?/", "", $string, 1);
        }

        // Replace all doubled-up <BR> tags with <P> tags, and remove fonts.
        $string = preg_replace("/<br\/?>[ \r\n\s]*<br\/?>/i", "</p><p>", $string);
        $string = preg_replace("/<\/?font[^>]*>/i", "", $string);

        // @see https://github.com/feelinglucky/php-readability/issues/7
        //   - from http://stackoverflow.com/questions/7130867/remove-script-tag-from-html-content
        $string = preg_replace("#<script(.*?)>(.*?)</script>#is", "", $string);

        return trim($string);
    }


    /**
     * Delete all of the DOM element $TagName tag
     *
     * @return DOMDocument
     */
    private function removeJunkTag($RootNode, $TagName) {
        
        $Tags = $RootNode->getElementsByTagName($TagName);
        
        //Note: always index 0, because removing a tag removes it from the results as well.
        while($Tag = $Tags->item(0)){
            $parentNode = $Tag->parentNode;
            $parentNode->removeChild($Tag);
        }
        
        return $RootNode;
        
    }

    /**
     * 删除元素中所有不需要的属性
     */
    private function removeJunkAttr($RootNode, $Attr) {
        $Tags = $RootNode->getElementsByTagName("*");

        $i = 0;
        while($Tag = $Tags->item($i++)) {
            $Tag->removeAttribute($Attr);
        }

        return $RootNode;
    }

    /**
     * 根据评分获取页面主要内容的盒模型
     *      判定算法来自：http://code.google.com/p/arc90labs-readability/
     *
     * @return DOMNode
     */
    private function getTopBox() {

		// Count div tags
        $checkParagraphs = $this->DOM->getElementsByTagName("div");
        while($paragraph = $checkParagraphs->item($i++)) {
        	$checkDIV += 1;
        }
		
		// Count p tags
        $checkParagraphs = $this->DOM->getElementsByTagName("p");
        while($paragraph = $checkParagraphs->item($i++)) {
        	$checkP += 1;
        }

        // Get all the chapters page, from the biggest count of tags div or p
        // workaround for pages that handle with div instead p tags
		if ($checkDIV > $checkP) {
	        $allParagraphs = $this->DOM->getElementsByTagName("div");
	    } 
	    else {
	        $allParagraphs = $this->DOM->getElementsByTagName("p");
	    }

        // Study all the paragraphs and find the chunk that has the best score.
        // A score is determined by things like: Number of <p>'s, commas, special classes, etc.
        $i = 0;
        while($paragraph = $allParagraphs->item($i++)) {
            $parentNode   = $paragraph->parentNode;
            $contentScore = intval($parentNode->getAttribute(Readability::ATTR_CONTENT_SCORE));
            $className    = $parentNode->getAttribute("class");
            $id           = $parentNode->getAttribute("id");

            // Look for a special classname
            if (preg_match("/(comment|meta|footer|footnote)/i", $className)) {
                $contentScore -= 50;
            } else if(preg_match(
                "/((^|\\s)(post|hentry|entry[-]?(content|text|body)?|article[-]?(content|text|body)?)(\\s|$))/i",
                $className)) {
                $contentScore += 25;
            }

            // Look for a special ID
            if (preg_match("/(comment|meta|footer|footnote)/i", $id)) {
                $contentScore -= 50;
            } else if (preg_match(
                "/^(post|hentry|entry[-]?(content|text|body)?|article[-]?(content|text|body)?)$/i",
                $id)) {
                $contentScore += 25;
            }

            // Add a point for the paragraph found
            // Add points for any commas within this paragraph
            if (strlen($paragraph->nodeValue) > 10) {
                $contentScore += strlen($paragraph->nodeValue);
            }

            // Preservation of the parent element determination score
            $parentNode->setAttribute(Readability::ATTR_CONTENT_SCORE, $contentScore);

            // Save sections of the parent element, so that the next quick access
            array_push($this->parentNodes, $parentNode);
        }

        $topBox = null;
        
        // Assignment from index for performance. 
        //     See http://www.peachpit.com/articles/article.aspx?p=31567&seqNum=5 
        for ($i = 0, $len = sizeof($this->parentNodes); $i < $len; $i++) {
            $parentNode      = $this->parentNodes[$i];
            $contentScore    = intval($parentNode->getAttribute(Readability::ATTR_CONTENT_SCORE));
            $orgContentScore = intval($topBox ? $topBox->getAttribute(Readability::ATTR_CONTENT_SCORE) : 0);

            if ($contentScore && $contentScore > $orgContentScore) {
                $topBox = $parentNode;
            }
        }
        
        // At this time, $topBox should have been the main content of the page after the determination of elements
        return $topBox;
    }


    /**
     * 获取 HTML 页面标题
     *
     * @return String
     */
    public function getTitle() {
        $split_point = ' - ';
        $titleNodes = $this->DOM->getElementsByTagName("title");

        if ($titleNodes->length 
            && $titleNode = $titleNodes->item(0)) {
            // @see http://stackoverflow.com/questions/717328/how-to-explode-string-right-to-left
            $title  = trim($titleNode->nodeValue);
            $result = array_map('strrev', explode($split_point, strrev($title)));
            return sizeof($result) > 1 ? array_pop($result) : $title;
        }

        return null;
    }


    /**
     * Get Leading Image Url
     *
     * @return String
     */
    public function getLeadImageUrl($node) {
        $images = $node->getElementsByTagName("img");

        if ($images->length && $leadImage = $images->item(0)) {
            return $leadImage->getAttribute("src");
        }

        return null;
    }


    /**
     * 获取页面的主要内容（Readability 以后的内容）
     *
     * @return Array
     */
    public function getContent() {
        if (!$this->DOM) return false;

        // 获取页面标题
        $ContentTitle = $this->getTitle();

        // 获取页面主内容
        $ContentBox = $this->getTopBox();
        
        //Check if we found a suitable top-box.
        if($ContentBox === null)
            throw new RuntimeException(Readability::MESSAGE_CAN_NOT_GET);
        
        // 复制内容到新的 DOMDocument
        $Target = new DOMDocument;
        $Target->appendChild($Target->importNode($ContentBox, true));

        // 删除不需要的标签
        foreach ($this->junkTags as $tag) {
            $Target = $this->removeJunkTag($Target, $tag);
        }

        // 删除不需要的属性
        foreach ($this->junkAttrs as $attr) {
            $Target = $this->removeJunkAttr($Target, $attr);
        }

        $content = mb_convert_encoding($Target->saveHTML(), Readability::DOM_DEFAULT_CHARSET, "HTML-ENTITIES");

        // 多个数据，以数组的形式返回
        return Array(
            'lead_image_url' => $this->getLeadImageUrl($Target),
            'word_count' => mb_strlen(strip_tags($content), Readability::DOM_DEFAULT_CHARSET),
            'title' => $ContentTitle ? $ContentTitle : null,
            'content' => $content
        );
    }

    function __destruct() { }
}