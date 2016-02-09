NP_Keywords
====================

This plugin adds keywords support to your items (entries).

Use of the skin variable
----------------------
`<%Keywords%>` in your archive or item skins will
allow you to include meta keywords in your HTML <HEAD> section.

i.e. `<meta name="keywords" content="<%Keywords%>" />`

In this context plugin accepts second parameter which is blog and third which is divider for keywords
list - it is comma by default. Use SPACE_PLACEHOLDER if you need space as divider
and COMMA_PLACEHOLDER for comma
If you use `<%Keywords(3)%>` inside templates, it will produce keyword-based "see also" links.
Number is how many links to produce for each of keywords.
You can also use `<%Keywords(3,anyblog)%>` to make links to other blog's entries too.

@copyright c. 2003, terry chay, 2004 Alexander Gagin

@license BSD

@author  terry chay <tychay@php.net>, Alexander Gagin

@version 0.4

History
----------------------
*  0.31 Added gray keyword before a link to a seealso article
*  0.32 Changed PostUpdateItem to PostAddItem (bug from original?)
       Relationship table linked to item table with foreign key
*  0.33 TemplateVar now accepts second parameter which could be "anyblog"
*        if link should point at posts from any blogs installed on the system
*  0.34 Added third skinvar parameter for keywords divider
*  0.35 added idraft=0 check at templatevar to not show links to drafts
*  0.36 20 feb 05 fixed not closed input tag
*  0.37 11 apr 05 changed keywords printout format in temlatevar according to new design
*  0.38 16 aug 06
 1. added comment with alternative install string as current is not working on some MySQL versions (here: http://www.nucleus.com.ru/forum/index.php?showtopic=83&st=0&p=1240&#entry1240)
 2. added itime check to not show in seealso list future articles
