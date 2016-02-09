<?php
 
/**
* This plugin adds keywords support to your items (entries).
*
* Use of the skin variable <%Keywords%> in your archive or item skins will
* allow you to include meta keywords in your HTML <HEAD> section.
* i.e. <meta name="keywords" content="<%Keywords%>" />
* In this context plugin accepts second parameter which is blog and third which is divider for keywords
* list - it is comma by default. Use SPACE_PLACEHOLDER if you need space as divider
* and COMMA_PLACEHOLDER for comma
* If you use <%Keywords(3)%> inside templates, it will produce keyword-based "see also" links.
* Number is how many links to produce for each of keywords.
* You can also use <%Keywords(3,anyblog)%> to make links to other blog's entries too.
*
* @todo make default limit to work by default
* @todo separate code and layout for linklist
* @todo find a source of linebreak in SkinVar when used in meta keywords
* @todo make prefixing seealso link with keyword configurable in plugin settings?
*
* @access  public
* @copyright c. 2003, terry chay, 2004 Alexander Gagin
* @license BSD
* @author  terry chay <tychay@php.net>, Alexander Gagin
* @version 0.4
*
* History:
*  0.31 Added gray keyword before a link to a seealso article
*  0.32 Changed PostUpdateItem to PostAddItem (bug from original?)
*       Relationship table linked to item table with foreign key
*  0.33 TemplateVar now accepts second parameter which could be "anyblog"
*        if link should point at posts from any blogs installed on the system
*  0.34 Added third skinvar parameter for keywords divider
*  0.35 added idraft=0 check at templatevar to not show links to drafts
*  0.36 20feb05 fixed not closed input tag
*  0.37 11apr05 changed keywords printout format in temlatevar according to new design
*  0.38 16aug06 1. added comment with alternative install string
*                  as current is not working on some MySQL versions (here: http://www.nucleus.com.ru/forum/index.php?showtopic=83&st=0&p=1240&#entry1240)
*               2. added itime check to not show in seealso list future articles
*/
class NP_Keywords extends NucleusPlugin {
    // Plugin list data {{{
    function getName()    { return 'Keywords Plugin'; }
    function getAuthor()  { return 'terry chay, Alexander Gagin, nucleuscms.org'; }
    function getURL()     { return 'https://github.com/NucleusCMS/NP_Keywords'; } 
    function getVersion() { return '0.4'; }
    function getDescription()   { 
      return 'This plugin allows keywords to be included with your items
              and enables things so that the keywords can be displayed
              as a meta tag in the &lt;HEAD&gt; HTML section.
              If you use <%Keywords(3)%> inside templates, it will produce keyword-based "see also" links.
              Number is how many links to produce for each of keywords.';
     }
    function supportsFeature($feature) {
      switch($feature) {
        case 'SqlTablePrefix':
          return 1;
        default:
          return 0;
      }
    }
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
        sql_query(sprintf("CREATE TABLE %s (keyword_id int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY, keyword varchar(255) NOT NULL default '') ENGINE=MYISAM",sql_table('plug_keywords_keyword')));
        sql_query(sprintf("CREATE TABLE %s (keyword_id int(11) NOT NULL, key_id int(11) NOT NULL, FOREIGN KEY (keyword_id) REFERENCES %s(keyword_id), FOREIGN KEY (key_id) REFERENCES %s(inumber)) ENGINE=MYISAM",sql_table('plug_keywords_relationship'),sql_table('plug_keywords_keyword'),sql_table('item')));
    }
    /**
     * This destroys the tables related to keywords.
     *
     */
    function uninstall()
    {
        sql_query(sprintf("DROP TABLE %s",sql_table('plug_keywords_relationship')));
        sql_query(sprintf("DROP TABLE %s",sql_table('plug_keywords_keyword')));
     }
   /**
    * skinvar parameters:
    *      - blogname (optional)
    * SkinVar prints keywords list (for meta field)
    */ 
    function doSkinVar($skinType, $blogName = '', $divider = ',') {
        /*
            select which month to show
                - for archives: use that month
                - otherwise: use current month
        */ 
        switch($skinType) { 
            case 'archive': 
                //sscanf($GLOBALS['archive'],'%d-%d-%d',$y,$m,$d); 
                //$time = mktime(0,0,0,$m,1,$y);
                $keywords = $this->_selectKeywordsFromBlogDate($this->_getBlogid($blogName), $GLOBALS['archive']);
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
        //change space and comma placeholders to real symbols. Seems it's wrong way to do it.
        $divider=str_replace("SPACE_PLACEHOLDER"," ",$divider);
        $divider=str_replace("COMMA_PLACEHOLDER",",",$divider);
        echo implode($divider,$keywords);
    }
    
    /**
        For keywords list user Skinvar.
    This TemplVar function make "see also" links to articles with same keywords
        @param int $limit number of links for each article's keywords
        @param string $anyblog If set to "anyblog", will produce see-also links not only to current blog's entries, but all blogs
    */
    function doTemplateVar(&$item, $limit = 5, $anyblog = "")
    {
        $keys=array(0=>$item->itemid);
        $sql = sprintf('SELECT keyword_id FROM %s WHERE key_id=%d', sql_table('plug_keywords_relationship'), intval($item->itemid));
        $res = sql_query($sql);
        
        if ($anyblog=="anyblog") $onlyblog = "";
        else                     $onlyblog = "AND i.iblog = " .  $this->_getBlogid();
        
    echo '<ul>';
        // get keyword IDs for this article, now need to get list of articles that have same keyword
        while ($o = sql_fetch_array($res)) {
                $sql2 = sprintf('SELECT i.inumber,
                                        i.ititle,
                                        k.keyword
                                   FROM %s as kr,
                                        %s as i,
                                        %s as k
                                  WHERE kr.keyword_id = %d
                                    AND kr.key_id = i.inumber
                                    AND i.idraft = 0
                    AND i.itime<=%s
                    AND k.keyword_id = kr.keyword_id
                                        %s
                               ORDER BY i.itime DESC
                                  LIMIT %d',
                                 sql_table('plug_keywords_relationship'),
                                 sql_table('item'),
                                 sql_table('plug_keywords_keyword'),
                                 intval($o[0]),
                 // next string is based on code from BLOG.php, not sure how it works
                 mysqldate(time() + 3600 * $manager->settings['btimeoffset']),
                                 $onlyblog,
                                 intval($limit)
                                );
                $res2 = sql_query($sql2);
                while ($o2 = sql_fetch_array($res2)) {
                        // uniques only
                        if ( ! in_array($o2[0],$keys) ) {
                                //echo '<font color=gray>' . $o2[2] . ':</font> <a href="' . createItemLink($o2[0]) . '">' . $o2[1] .'</a><br/>';
                                echo '<li><a href="' . createItemLink($o2[0]) . '">' . $o2[1] .'</a> <span>('.$o2[2] .')</span></li>';
                                $keys[]=$o2[0];
                        }
                }
                sql_free_result($res2);
        }
        sql_free_result($res);
    echo '</ul>';
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
        printf('Keywords: <input name="plug_keywords_keywords" type="text" size="60" maxlength="256" value="%s"><br />', $keywordstring);
    }
    /**
     * Handle adding of keywords to database on a new entry.
     *
     * @param array $params contains the itemid.
     */
    function event_PostAddItem(&$params)
    {
        $itemid = $params['itemid'];
        $keywords = explode(',',postvar('plug_keywords_keywords'));
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
        $newkeywords = explode(',',postvar('plug_keywords_keywords'));
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
        return array(sql_table('plug_keywords_relationship'),sql_table('plug_keywords_keyword'));
    }
    
    function _selectKeywords($itemid)
    {
        $sql = sprintf('SELECT k.keyword FROM %s as k,%s as kr WHERE kr.key_id=%d AND kr.keyword_id = k.keyword_id ORDER BY k.keyword',sql_table('plug_keywords_keyword'),sql_table('plug_keywords_relationship'),intval($itemid));
        $res = sql_query($sql);
        $returns = array();
        while ($o = sql_fetch_array($res)) {
            $returns[] = $o[0];
        }
        sql_free_result($res);
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
        $sql = sprintf("SELECT k.keyword
                          FROM %s as k,
                               %s as kr,
                               %s as i
                         WHERE i.iblog = %d
                           AND i.itime >= '%s'
                           AND i.itime < DATE_ADD('%s',INTERVAL 1 %s)
                           AND kr.key_id = i.inumber
                           AND k.keyword_id = kr.keyword_id
                         ORDER BY keyword",
                       sql_table('plug_keywords_keyword'),sql_table('plug_keywords_relationship'),
                       sql_table('item'),
                       intval($blogid),
                       sql_real_escape_string($date),
                       sql_real_escape_string($date),
                       $len);
        $res = sql_query($sql);
        while ($o = sql_fetch_array($res)) {
            $returns[] = $o[0];
        }
        sql_free_result($res);
        return $returns;
    }
    function _deleteKeywords($itemid)
    {
        $sql = sprintf('DELETE FROM %s WHERE key_id=%d', sql_table('plug_keywords_relationship'), intval($itemid));
        sql_query($sql);
    }
    /**
    Returns KeywordID for keyword string or 0 if there's no one
    */
    function _getKeywordID($keyword)
    {
        $sql = sprintf("SELECT keyword_id FROM %s WHERE keyword='%s'", sql_table('plug_keywords_keyword'), sql_real_escape_string($keyword));
        $res = sql_query($sql);
        if (sql_num_rows($res)) {
            $o = sql_fetch_array($res);
            $return = $o[0];
        } else {
            $return = 0;
        }
        sql_free_result($res);
        return $return;
    }
    function _addKeyword($itemid,$keyword)
    {
        //check to see if keyword exists
        $keywordid = $this->_getKeywordID($keyword);
        if ($keywordid == 0) {
            $sql = sprintf("INSERT INTO %s (keyword) VALUES ('%s')", sql_table('plug_keywords_keyword'), sql_real_escape_string($keyword));
            sql_query($sql);
            $keywordid = sql_insert_id();
        }
        $sql = sprintf('INSERT INTO %s (keyword_id, key_id) VALUES (%d, %d)', sql_table('plug_keywords_relationship'), intval($keywordid),intval($itemid));
        sql_query($sql);
    }
    function _deleteKeyword($itemid,$keyword)
    {
        $keywordid = $this->_getKeywordID($keyword);
        $sql = sprintf('DELETE FROM %s WHERE key_id=%d AND keyword_id=%d', sql_table('plug_keywords_relationship'), intval($itemid), intval($keywordid));
        sql_query($sql);
    }
    
    function _getBlogid($blogname = '')
    {
        global $manager, $blog, $CONF;
 
        /*
            find out which blog to use:
                1. try the blog chosen in skinvar parameter
                2. try to use the currently selected blog
                3. use the default blog
        */
        if ($blogName) $b =& $manager->getBlog(getBlogIDFromName($params[2]));
        elseif($blog)  $b =& $blog;
        else           $b =& $manager->getBlog($CONF['DefaultBlog']);
        
        return $b->getID();
    }
}
