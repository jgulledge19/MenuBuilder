# MenuBuilder
Development Version

## Description
Alternative/Replacement for the Wayfinder extra. 

## Features
 - Use different Chunks for every level of your menu if you choose
 - Currently loads one or all menus with a single query, should be fast
 - Simpler design and usage, faster to get up and going and more flexible.

## Manual Install
 - Download files and put in your MODX install
 - Create Snippets in MODX Manager, code for [Snippets](https://rtfm.modx.com/revolution/2.x/developing-in-modx/basic-development/snippets): 
 [core/components/menubuilder/elements/snippets](../master/core/components/menubuilder/elements/snippets)
    - menuBuilder
    - installMenuBuilder
 - Run the install Snippet in a Resource, this will create the database table
 - Create the [Plugin](https://rtfm.modx.com/revolution/2.x/developing-in-modx/basic-development/plugins): 
 buildMenuSequence, see code:  [core/components/menubuilder/elements/plugins](../master/core/components/menubuilder/elements/plugins) 
 and attach System Events:
    - OnDocFormSave
    - OnResourceSort
 - Save or sort a Resource in the MODX Tree and it will populate the database and will for every on change.
 - Ready to use
 - Optional, create namespace: menubuilder and then three system settings as Yes/No. This will allow you to turn/off on when rebuilds happen:
    - menubuilder.rebuildOnCacheUpdate
    - menubuilder.rebuildOnDocFormSave
    - menubuilder.rebuildOnResourceSort
    
## Build a navigation menu

Place a Snippet call into your appropriate Template/Chunk/Resource. Like:
[[menuBuilder?
    &startId=`1`
]]

## Chunks used
By default will generate an unordered list. 

Note (bool) stands for boolean which is true/false or 1/0.

| Property | Description | Default |
|--- |--- |--- |
| chunkItem | The Chunk name that will be used to render the item/Resource/link  |  |
| chunkWrapper | The Chunk that will wrap a level or list of items |  |
| chunkItem#Level | Replace #Level with a number, it will override the chunkItem Chunk for related level  |  |
| chunkWrapper#Level | Replace #Level with a number, it will override the chunkWrapper Chunk for related level  |  |
| chunkItemResource#ID | Replace #ID with a valid Resource ID number, it will override the chunkItem and related chunkItem#Level |  |
| chunkWrapperResource#ID | Replace #ID with a valid Resource ID number, it will override the chunkWrapper and related chunkWrapper#Level  |  |


## General options

| Property | Description | Default |
|--- |--- |--- |
| startId | The starting point (Resource ID) for the menu to list documents from. Specify 0 to start from the site root. | 0 | 
| displayStart | Show the document as referenced by &startId in the menu. | (bool) 0 |
| level | Depth (number of levels) to build the menu from. '0' goes through all levels. | 0 |
| limit | The limit parameter the total number of items specified | |
| limitLevelItems | [JSON style](https://rtfm.modx.com/xpdo/2.x/class-reference/xpdo/xpdo.fromjson) ex: ```  &limitLevelItems=`{"1":"5","2":"4"}` ``` The name/left is the level and the right is the limit. | |
| resourceColumns | Comma separated list of [Resource columns](https://rtfm.modx.com/revolution/2.x/making-sites-with-modx/commonly-used-template-tags#CommonlyUsedTemplateTags-AllTags) to add to existing columns to be included for items | id, context_key, pagetitle, longtitle, menutitle, parent, menuindex, link_attributes, template |
| viewHidden | (bool) Hide/Show based on the value of "Hide From Menus" checkbox | 0 |
| viewUnpublished | (bool) | 0 |
| viewDeleted | (bool) | 0 |
| placeholder | Name of a placeholder to send results to, instead of directly returning the output. | |
| debug | (bool) Set to '1' to enable debug mode for extra troubleshooting. | 0 |
| debugSql | (bool) Will output the SQL that is generated by your property settings | 0 |
| iterateType | getIterator or PDO | getIterator |
| where | JSON style [filtering](https://rtfm.modx.com/xpdo/2.x/class-reference/xpdoquery/xpdoquery.where) option. For example when trying to hide blog or news from the Articles addon: &where=`[{"class_key:!=": "Article"}]`  |  |
| contexts | Comma separated list of Context_keys to use for building the menu. | Default is current context |
| scheme | format for how URLs are generated.  | [System Setting](https://rtfm.modx.com/revolution/2.x/administering-your-site/settings/system-settings/link_tag_scheme) |
| rawTvs | Comma separated list of TVs to include you will then use like: [[+tvMainImage]], the first letter of the name will be made uppercase |  |
| processTvs | Comma separated list of TVs to process include you will then use like: [[+tvMainImage]], the first letter of the name will be made uppercase. Note: the iterateType property must be set to getIterator |  |


## TODO
- Resource Group/ACLs 
- Build script to package in MODX Extras