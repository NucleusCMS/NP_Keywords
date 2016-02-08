<?php

/**
* This plugin adds keywords support to your items (entries).
*
* Use of the skin variable <%Keywords%> in your archive or item skins will
* allow you to include meta keywords in your HTML <HEAD> section.
* i.e. <meta name="keywords" content="<%Keywords%>" />
*
* @access  public
* @copyright c. 2003, terry chay
* @license BSD
* @author  terry chay <tychay@php.net>
* @author  $Author$
* @version 0.1 ($Revision$)
* @since   $Date$
*/
class NP_Keywords extends NucleusPlugin {
    // Plugin list data {{{
    function getName() { return 'Keywords Plugin'; } 
    function getAuthor() { return '<a href="mailto:tychay@php.net">terry chay</a>'; } 
    function getURL() { return 'http://www.terrychay.com/nucleus/'; } 
    function getVersion() { return '0.1'; } 
    function getDescription()
    { 
      return 'This plugin allows keywords to be included with your items and enables things so that the keywords can be displayed as a meta tag in the &lt;HEAD&gt; HTML section.'; 
    }
    // }}}

    // Plugin installation {{{
   /**
    * On plugin install, create the tables for type and keywords.
    *
    * This inserts the type for the blog as well as the type for the keywords
    * table.
    *
    * @todo only create the type table if it doesn't exist.
    */ 
   function install()
   {
        sql_query("CREATE TABLE `tc_type` ( `type_id` tinyint(4) NOT NULL default '0', `name` char(64) NOT NULL default '', PRIMARY KEY  (`type_id`) ) TYPE=MyISAM COMMENT='defines various types of data';");
        sql_query("INSERT INTO `tc_type` VALUES (1, 'item');");
        sql_query("INSERT INTO `tc_type` VALUES (2, 'keyword');");
        sql_query("CREATE TABLE `tc_keyword` ( `keyword_id` int(11) NOT NULL default '0', `keyword` char(128) NOT NULL default '', PRIMARY KEY  (`keyword_id`) ) TYPE=MyISAM COMMENT='stores keywords';");
        sql_query("CREATE TABLE `tc_keyword_relationship` ( `keyword_id` int(11) NOT NULL default '0', `key_id` int(11) NOT NULL default '0', `type_id` tinyint(4) NOT NULL default '0' ) TYPE=MyISAM COMMENT='binds keywords to various items';");
        /*
        // create some options
        $this->createOption('Locale','Language (locale) to use','text','en');
        */
    } 
    /**
     * This destorys the tables related to keywords.
     *
     * @todo remove also the entry for keywords in the type table.
     */
    function uninstall()
    {
        sql_query('DROP TABLE `tc_keyword`;');
        sql_query('DROP TABLE `tc_keyword_relationship`;');
    }
    // }}}

   /**
    * skinvar parameters:
    *      - blogname (optional)
    */ 
    function doSkinVar($skinType, $blogName = '') { 
        global $manager, $blog, $CONF; 
        
        /*
            find out which blog to use:
                1. try the blog chosen in skinvar parameter
                2. try to use the currently selected blog
                3. use the default blog
        */ 
        if ($blogName) { 
            $b =& $manager->getBlog(getBlogIDFromName($params[2])); 
        } else if ($blog) { 
            $b =& $blog; 
        } else { 
            $b =& $manager->getBlog($CONF['DefaultBlog']); 
        } 
        
        /*
            select which month to show
                - for archives: use that month
                - otherwise: use current month
        */ 
        switch($skinType) { 
            case 'archive': 
                //sscanf($GLOBALS['archive'],'%d-%d-%d',$y,$m,$d); 
                //$time = mktime(0,0,0,$m,1,$y); 
                $keywords = $this->_selectKeywordsFromBlogDate($b->getID(), $GLOBALS['archive']);
                break;
            case 'item':
                $keywords = $this->_selectKeywords($GLOBALS['itemid']);
                break;
            case 'index':
            case 'archivelist':
            case 'member':
            case 'error':
            case 'search':
            case 'imagepopup':
            case 'template':
            default:
                return;
                //there are no associated keywords for these skins...
        } 
        
        echo implode(',',$keywords);
    }
    
    // Events {{{
    function getEventList()
    {
        return array('AddItemFormExtras', 'EditItemFormExtras', 'PreUpdateItem', 'PostAddItem','PreDeleteItem');
    }
    
    /**
     * Add a keywords entry field to the add item page or bookmarklet.
     *
     * @params an associative array containing 'blog' which is a reference to
     *      the blog object.
     */
    function event_AddItemFormExtras(&$params)
    {
        $this->_generateForm();
    }
    /**
     * Adds a keywords entry field to the edit item page or bookmarklet.
     *
     * @param array $params An associative array of <ul>
     *  <li><b>&blog</b>- reference to a BLOG object. </li>
     *  <li><b>variables</b>- an associative array containing all sorts of
     *        information on the item being edited: 'itemid', 'draft', ... </li>
     *  <li><b>itemid</b>- shortcut to the itemID</li>
     * </ul>
     */
    function event_EditItemFormExtras(&$params)
    {
        //echo '<pre>';print_r($params);echo '</pre>';
        $itemid = $params['itemid'];
        // look for keywords...
        $keywords=$this->_selectKeywords($itemid);
        // transform it into a string
        $this->_generateForm(implode(',',$keywords));
    }
    /**
     * Generate form for inputting keywords
     */
    function _generateForm($keywordstring='')
    {
        printf('Keywords: <input name="plug_keywords" type="text" size="60" maxlength="256" value="%s"', $keywordstring);
    }
    /**
     * Handle adding of keywords to database on a new entry.
     *
     * @param array $params contains the itemid.
     */
    function event_PostUpdateItem(&$params)
    {
        $itemid = $params['itemid'];
        $keywords = explode(',',postvar('plug_keywords'));
        foreach ($keywords as $keyword) {
            $this->_addKeyword($itemid,trim($keyword));
        }
    }
    /**
     * Handle adding of keywords to database when entry is modified.
     *
     * @param array $params contains 'itemid'
     */
    function event_PreUpdateItem(&$params)
    {
        $itemid = $params['itemid'];
        $newkeywords = explode(',',postvar('plug_keywords'));
        foreach ($newkeywords as $id=>$keyword) {
            $newkeywords[$id] = trim($keyword);
        }
        $oldkeywords = $this->_selectKeywords($itemid);
        $addkeywords = array_diff($newkeywords,$oldkeywords);
        $deletekeywords = array_diff($oldkeywords,$newkeywords);
        foreach ($addkeywords as $keyword) {
            $this->_addKeyword($itemid,$keyword);
        }
        foreach ($deletekeywords as $keyword) {
            $this->_deleteKeyword($itemid,$keyword);
        }
    }
    function event_PreDeleteItem(&$params)
    {
        $itemid = $params['itemid'];
        $this->_deleteKeywords($itemid);
    }
    
    // database {{{
    function getTableList()
    {
        return array('tc_type','tc_keyword','tc_keyword_relationship');
    }
    
    function _selectKeywords($itemid)
    {
        $sql = sprintf('SELECT k.keyword FROM tc_keyword as k, tc_keyword_relationship as kr WHERE kr.type_id=1 AND kr.key_id=%d AND kr.keyword_id = k.keyword_id',intval($itemid));
        $res = sql_query($sql);
        $returns = array();
        while ($o = mysql_fetch_array($res)) {
            $returns[] = $o[0];
        }
        mysql_free_result($res);
        return $returns;
    }
    function _selectKeywordsFromBlogDate($blogid,$date)
    {
        if (strlen($date) <= 4) {
            $len = 'YEAR';
            $date = $date.'-01-01';
        } elseif (strlen($date) <= 7) {
            $len = 'MONTH';
            $date = $date.'-01';
        } else {
            $len = 'DAY';
        }
        $sql = sprintf('SELECT k.keyword
                          FROM tc_keyword as k,
                               tc_keyword_relationship as r,
                               nucleus_item as i
                         WHERE i.iblog = %d
                           AND i.itime >= \'%s\'
                           AND i.itime < DATE_ADD(\'%s\',INTERVAL 1 %s)
                           AND r.key_id = i.inumber
                           AND r.type_id = 1
                           AND k.keyword_id = r.keyword_id',
                       intval($blogid),
                       mysql_escape_string($date),
                       mysql_escape_string($date),
                       $len);
        $res = sql_query($sql);
        $returns = array();
        while ($o = mysql_fetch_array($res)) {
            $returns[] = $o[0];
        }
        mysql_free_result($res);
        return $returns;
    }
    function _deleteKeywords($itemid)
    {
        $sql = sprintf('DELETE FROM tc_keyword_relationship WHERE key_id=%d AND type_id=1',intval($itemid));
        sql_query($sql);
    }
    function _selectKeyword($itemid,$keyword)
    {
        $sql = sprintf('SELECT keyword_id FROM tc_keyword WHERE keyword=\'%s\'', mysql_escape_string($keyword));
        $res = sql_query($sql);
        if (mysql_num_rows($res)) {
            $o = mysql_fetch_array($res);
            $return = $o[0];
        } else {
            $return = 0;
        }
        mysql_free_result($res);
        return $return;
    }
    function _addKeyword($itemid,$keyword)
    {
        //check to see if keyword exists
        $keywordid = $this->_selectKeyword($itemid, $keyword);
        if ($keywordid == 0) {
            $sql = sprintf('INSERT INTO tc_keyword (keyword) VALUES (\'%s\')', mysql_escape_string($keyword));
            sql_query($sql);
            $keywordid = mysql_insert_id();
        }
        $sql = sprintf('INSERT INTO tc_keyword_relationship (keyword_id, key_id, type_id) VALUES (%d, %d, 1)',intval($keywordid),intval($itemid));
        sql_query($sql);
    }
    function _deleteKeyword($itemid,$keyword)
    {
        $keywordid = $this->_selectKeyword($itemid,$keyword);
        $sql = sprintf('DELETE FROM tc_keyword_relationship WHERE key_id=%d AND type_id=1 AND keyword_id=%d', intval($itemid), intval($keywordid));
        sql_query($sql);
    }
    // }}}
    
    // }}}
} 
?>