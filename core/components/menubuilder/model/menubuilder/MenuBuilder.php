<?php
/**
 *
 * Manage Materialized Paths: http://en.wikipedia.org/wiki/Materialized_path
 *  - https://communities.bmc.com/communities/docs/DOC-9902
 *  - http://www.cloudconnected.fr/2009/05/26/trees-in-sql-an-approach-based-on-materialized-paths-and-normalization-for-mysql/
 *
 * DB table needs columns:
 *  - parent_id INT - this will still allow for the Adjacency List
 *  - sequence(rank/menuindex) INT - how the children will be ordered
 *  - depth INT - at what depth/level is the current node at
 *  - path VARCHAR or TEXT - ex: topparent/parent/current or in ids: 1/2/3
 *
 */
class MenuBuilder {

    /**
     * the form data config
     *  array(
     *     parent => parent_id,
     *     rank => rank
     *     depth => depth
     *     path => path
     *     )
     */
    protected $config = array();

    /**
     * the processed branch data
     * @param (array) $branch_data
     *
     *  array(
     *     parent => parent_id,
     *     rank => rank
     *     depth => depth
     *     path => path
     *     )
     */
    protected $branch_data = array();
    /**
     * @var array
     */
    protected $classes = array();
    /**
     * @var array
     */
    protected $chunks = array();
    /**
     * @var bool
     * @access protected
     */
    protected $debug = false;

    /**
     * @var modX object
     */
    public $modx;

    /**
     * @param modX $modx
     * @param (array) $config - the for
     */
    function __construct(modX &$modx, array $config = array() ) {
        $this->modx =& $modx;
        $this->config = array(
            'separator' => '.',// @TODO system settings
            'path_decimal_places' => 3, // int

            'displayStart' => false, // bool
            'viewHidden' => false, // bool
            'viewUnpublished' => false, // bool
            'viewDeleted' => false, // bool
            'templates' => null, // comma separated list if IDs
            'contexts' => null, // comma separated list of context keys
            'limit' => null, // int
            'offset' => 0, // int
            'scheme' => $this->modx->getOption('link_tag_scheme', null, 'abs'),
            'where' => null, // JSON ~ SQL
            'debugSql' => false,

            // TODO
            'includeDocs',
            'excludeDocs',
            'limitDepthItems',
            'sortBy',
            'TVs',
        );
        $this->config = array_merge($this->config, $config);

        $core_path = $modx->getOption('menubuilder.core_path', null, $this->modx->getOption('core_path').'components/menubuilder/');
        // add package:
        $this->modx->addPackage('menubuilder', $core_path . 'model/');

    }

    // Options:
    /**
     * @param string $type
     * @param string $name
     * @param int|null $depth
     *
     * @return $this ~ chainable
     */
    public function setChunk($type, $name, $depth=null)
    {
        if ( !empty( $depth ) ) {
            $depth = (int)$depth;
        }
        $valid_options = array();
        if ( in_array($type, $valid_options) ) {
            $this->chunks[$type.$depth] = $name;
        } else {
            // @TODO log error:

        }
        return $this;
    }

    /**
     * @param string $type
     * @param string $name
     * @param int|null $depth
     *
     * @return $this ~ chainable
     */
    public function setClass($type, $name, $depth=null)
    {

        if ( !empty( $depth ) ) {
            $depth = (int)$depth;
        }
        $valid_options = array();
        if ( in_array($type, $valid_options) ) {
            $this->classes[$type.$depth] = $name;
        } else {
            // @TODO log error:

        }
        return $this;
    }

    /**
     * @param string $option
     * @param mixed $value
     *
     * @return $this ~ chainable
     */
    public function setOption($option, $value)
    {
        $valid_options = array(
            'separator',
            'path_decimal_places',

            'displayStart', // bool
            'viewHidden', // bool
            'viewUnpublished', // bool
            'viewDeleted', // bool
            'templates', // comma separated list if IDs
            'contexts', // comma separated list of context keys
            'limit', // int
            'offset', // int
            'scheme',
            'where', // JSON ~ SQL
            'debugSql', // bool

            // TODO
            'includeDocs',
            'excludeDocs',
            'limitDepthItems',
            'sortBy',
            'TVs'
        );
        if ( in_array($option, $valid_options) ) {
            $this->config[$option] = $value;

        } else {
            // @TODO log error:
        }
        return $this;
    }



    /**
     * @param bool|true $debug
     */
    public function setDebug($debug=true)
    {
        $this->debug = $debug;
    }


    /**
     * Will get the entire tree and all child nodes
     *
     */
    public function buildTree()
    {
        $this->buildBranch(0);
    }



    /**
     * @param int $parent_id
     * @param array $criteria
     * @param int $parent_depth
     * @param null $parent_path
     */
    public function buildBranch($parent_id, $criteria=array(), $parent_depth=0, $parent_path=NULL)
    {

        // sequence
        /**
         * Gets all:
        SELECT
        C.`id`,
        C.`context_key`,
        C.`parent`,
        C.`menuindex` AS `rank`,
        P.`id` AS `mb_id`,
        P.`depth`,
        P.`path`,
        P.`org_parent`,
        P.`org_menuindex`,
        FROM modx_site_content AS C
        LEFT JOIN modx_mb_sequence AS P ON P.`resource_id` = C.`id`
        WHERE C.`parent` = $parent_id
        ORDER BY C.`parent` ASC, C.`menuindex` ASC, C.`publishedon` DESC
         */

        // get the tree:
        $resourcesQuery = $this->modx->newQuery('MbResource');
        $resourcesQuery->leftJoin('MbSequence', 'Sequence');
        $resourcesQuery->select($this->modx->getSelectColumns('MbResource', 'MbResource','', array('id','context_key', 'parent', 'menuindex')));
        $resourcesQuery->select($this->modx->getSelectColumns('MbSequence', 'Sequence', 'mb_', array('id')));
        $resourcesQuery->select($this->modx->getSelectColumns('MbSequence', 'Sequence','', array('depth', 'path', 'org_parent', 'org_menuindex')));
        $resourcesQuery->where(array('parent' => $parent_id));
        // $criteria?
        $resourcesQuery->sortby('parent', 'ASC');
        $resourcesQuery->sortby('menuindex', 'ASC');
        $resourcesQuery->sortby('publishedon', 'DESC');

        $resourcesQuery->prepare();
        $sql = $resourcesQuery->toSQL();
        if ( $this->debug ) {
            $this->modx->log(modX::LOG_LEVEL_ERROR,'[MenuBuilder->buildBranch] SQL: '.$sql);
            /*
            echo $sql.'<hr>';
            echo '<pre>'.str_replace(
                        array('`, '              , 'FROM',                 'LEFT JOIN',         'WHERE',         'ORDER BY'),
                        array('`,'.PHP_EOL.'    ', PHP_EOL.'FROM', PHP_EOL.'LEFT JOIN', PHP_EOL.'WHERE', PHP_EOL.'ORDER BY'), $sql);
            */
            //exit();
        }
        $results = $this->modx->query($sql);

        if ( is_object($results) ) {
            $rank = 0;
            while ($row = $results->fetch(PDO::FETCH_ASSOC)) {
                // set the current resource depth:
                $current_depth = $parent_depth +1;

                if ( !isset($this->branch_data[$row['parent']]) ) {
                    // depth of parent starts at 0
                    if ( !isset($parents[$row['parent']]) ) {
                        $parents[$row['parent']] = 0;
                    }
                    $this->branch_data[$row['parent']] = array(
                        'rank' => 0,
                        'depth' => $current_depth,// how to get this? 1 if parent is 0
                        'path' => ''
                    );
                }

                $current_path = '';
                if ( !empty($parent_path) ) {
                    $current_path = $parent_path.$this->config['separator'];
                }
                $current_path .= str_pad(++$rank, $this->config['path_decimal_places'], "0", STR_PAD_LEFT);// makes 001

                if ( !isset($row['mb_id']) || empty($row['mb_id']) ) {
                    // new object
                    $mbSequence = $this->modx->newObject('MbSequence');
                    $mbSequence->set('resource_id', $row['id']);
                    if ( $this->debug ) {
                        $this->modx->log(modX::LOG_LEVEL_ERROR, '[MenuBuilder->buildBranch] NewObject, Resource ID: ' . $row['id']);
                    }

                } elseif ( $current_depth == $row['depth'] && $current_path == $row['path'] ) {
                    // no update:
                    continue;
                } else {
                    $mbSequence = $this->modx->getObject('MbSequence', $row['mb_id']);
                    if ( $this->debug ) {
                        $this->modx->log(modX::LOG_LEVEL_ERROR, '[MenuBuilder->buildBranch] UpdateObject, Resource ID: '
                            . $row['id'].' MbSequenceID: '.$row['mb_id']);
                    }
                }

                $mbSequence->set('path', $current_path);
                $mbSequence->set('depth', $current_depth);
                $mbSequence->set('org_parent', $row['parent']);
                $mbSequence->set('org_menuindex', $row['menuindex']);
                $mbSequence->save();

                $this->buildBranch($row['id'], array(), $current_depth, $current_path);
            }
        }

    }

    /**
     * Will get the entire tree and all child nodes
     *
     * @param (Array) $cretria - xPDO criteria
     * @param (INT) $depth - how deep to go, default is 0 meaning infinite
     * @param (String) $return_type - (PDO) fetch, (PDO) query, (xPDO) getCollection, (xPDO) getIterator
     * @return (Array) $data | (Object) $data
     */
    public function getTree($criteria=array(), $depth=0, $return_type='query') {
        return $this->getBranch(0, $criteria, $depth, $return_type);
    }

    /**
     * Will get all children of current node
     *
     * @param int $start_id - the $id of the object to get its children or branch
     * @param int $depth - how deep to go, default is 0 meaning infinite
     * @param bool|false $forward
     * @param string $return_type - (PDO) fetch, (PDO) query, (xPDO) getCollection, (xPDO) getIterator
     *
     * @return array|null
     */
    public function getBranch($start_id, $depth=0, $forward=false, $return_type='query') {
        $start_id = (int) $start_id;
        $depth = (int) $depth;
        /**
         * Get all:
        SELECT
        C.*,
        P.`id` AS `mb_id`,
        P.`depth`,
        P.`path`,
        (SELECT path FROM modx_mb_sequence AS S WHERE S.`resource_id` = $start_id ) AS `st_path`,
        (SELECT depth FROM modx_mb_sequence AS S WHERE S.`resource_id` = $start_id ) AS `st_depth`
        FROM modx_site_content AS C
        JOIN modx_mb_sequence AS P ON P.`resource_id` = C.`id`
        WHERE
            P.`path` LIKE CONCAT( (SELECT path FROM modx_mb_sequence AS S WHERE S.`resource_id` = $start_id ), '%') AND
            P.`depth` BETWEEN
                (SELECT depth FROM modx_mb_sequence AS S WHERE S.`resource_id` = $start_id ) AND
                ( (SELECT depth FROM modx_mb_sequence AS S WHERE S.`resource_id` = $start_id ) + 0)
        ORDER BY
            P.`path` ASC, C.`menuindex` ASC, C.`publishedon` DESC
         */
        $resourcesQuery = $this->modx->newQuery('MbResource');
        $resourcesQuery->leftJoin('MbSequence', 'Sequence');
        $resourcesQuery->select($this->modx->getSelectColumns('MbResource', 'MbResource','', array('id','context_key', 'pagetitle', 'parent', 'menuindex')));
        $resourcesQuery->select($this->modx->getSelectColumns('MbSequence', 'Sequence', 'mb_', array('id')));
        $resourcesQuery->select($this->modx->getSelectColumns('MbSequence', 'Sequence','', array('depth', 'path', 'org_parent', 'org_menuindex')));
        // start info as column data:
        $pathQuery = $this->modx->newQuery('MbSequence');
        $pathQuery->select($this->modx->getSelectColumns('MbSequence', 'MbSequence', '', array('path')));
        $pathQuery->where(array('resource_id' => $start_id ));
        $pathQuery->prepare();

        $path_sql = $pathQuery->toSQL();
        $resourcesQuery->select('('.$path_sql.') AS `st_path` ');

        $depthQuery = $this->modx->newQuery('MbSequence');
        $depthQuery->select($this->modx->getSelectColumns('MbSequence', 'MbSequence', '', array('depth')));
        $depthQuery->where(array('resource_id' => $start_id ));
        $depthQuery->prepare();

        $depth_sql = $depthQuery->toSQL();
        $resourcesQuery->select('('.$depth_sql.') AS `st_depth` ');


        if ( $start_id > 0 ) {
            $resourcesQuery->where(array('Sequence.path:LIKE' => '[[+path]]'));
        }
        if ( $depth > 0 && $start_id == 0) {
            $resourcesQuery->where(array('Sequence.depth:<=' => $depth));
        } else if ( $depth > 0 ) {
            $resourcesQuery->where(array('Sequence.depth' => '[[+depth]]'));
        }

        if (!empty($this->config['contexts'])) {
            $resourcesQuery->where(array('mbResource.context_key:IN' => explode(',',$this->config['contexts'])));
            $resourcesQuery->sortby('context_key','DESC');
        }

        // view deleted:
        if ( $this->modx->hasPermission('view_deleted') && $this->config['viewDeleted']) {

        } else {
            $resourcesQuery->where(array('mbResource.deleted:=' => 0));
        }

        // @TODO Published remove children from query as well
        // $this->modx->user->hasSessionContext('mgr') ?
        if ( $this->modx->hasPermission('view_unpublished') && $this->config['viewUnpublished']) {
            // no query
        } else {
            $resourcesQuery->where(array('mbResource.published:=' => 1));
        }

        // @TODO Hide from menus remove children from query as well
        if ( $this->config['viewHidden'] ) {

        } else {
            $resourcesQuery->where(array('mbResource.hidemenu:=' => 0));
        }

        /**
         * @TODO Resource Groups
         * Can this be done in SQL?
         */
        /* JSON where ability */
        if (!empty($this->config['where'])) {
            $where = $this->modx->fromJSON($this->config['where']);
            if (!empty($where)) {
                $resourcesQuery->where($where);
            }
        }
        if (!empty($this->config['templates'])) {
            $resourcesQuery->where(array(
                'mbResources.template:IN' => explode(',',$this->config['templates']),
            ));
        }

        // @TODO option to exclude Collections, Article and custom Resource type children

        /* add the limit to the query */
        if (!empty($this->config['limit'])) {
            $offset = !empty($this->config['offset']) ? $this->config['offset'] : 0;
            $resourcesQuery->limit($this->config['limit'], $offset);
        }

        if ( $forward ) {
            $resourcesQuery->sortby('path', 'ASC');
            $resourcesQuery->sortby('menuindex', 'ASC');
            $resourcesQuery->sortby('publishedon', 'DESC');
        } else {
            $resourcesQuery->sortby('path', 'DESC');
            $resourcesQuery->sortby('menuindex', 'DESC');
            $resourcesQuery->sortby('publishedon', 'ASC');
        }

        $resourcesQuery->prepare();
        $sql = $resourcesQuery->toSQL();

        // now replace the placeholders:
        if ( $start_id > 0 ) {
            $sql = str_replace("'[[+path]]'", ' CONCAT( (' . $path_sql . '), \'%\') ', $sql);
        }

        if ( $depth > 0 && $start_id > 0) {
            $sql = str_replace('`depth` = ', '`depth` ', $sql);

            $sql = str_replace(
                    "'[[+depth]]'",
                    ' BETWEEN(' . $depth_sql . ') AND ( (' . $depth_sql . ') + ' . $depth.') ',
                    $sql);
        }

        if ( $this->debug  ) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, '[MenuBuilder->getBranch] SQL: ' . $sql);
        }
        if ( $this->config['debugSql']  ) {
            echo 'Not pretty SQL: <br>'.$sql.'<hr> Prettier SQL';
            echo '<pre>'.str_replace(
                        array('`, '              , 'FROM',                 'LEFT JOIN',         'WHERE',         'ORDER BY'),
                        array('`,'.PHP_EOL.'    ', PHP_EOL.'FROM', PHP_EOL.'LEFT JOIN', PHP_EOL.'WHERE', PHP_EOL.'ORDER BY'), $sql);
            exit();
        }

        $data = null;
        // @TODO not sure this will work any more:
        switch ($return_type) {
            case 'getCollection':
                $data = $this->modx->getCollection('MbResource', $resourcesQuery);
                break;
            case 'getIterator':
                $data = $this->modx->getIterator('MbResource', $resourcesQuery);
                break;
            case 'sql':
                $sql;
                break;
            case 'fetch':
                $stmt = $this->modx->query($sql);
                if ( is_object($stmt) ) {
                    $data = $stmt->fetch(PDO::FETCH_ASSOC);
                }
                break;
            case 'query':
                // no break
            default:
                $data = $this->modx->query($sql);
                break;
        }
        return $data;
    }
    /**
     * @param int $start_id
     * @param int $depth
     * @param array $limits
     *
     * @return string $output
     */
    public function buildMenu ($start_id, $depth, $limits=array() )
    {
        $output = '';
        /**
         * array( Parent_path => string)
         */
        $parts = array();
        // this is returning the branch upside down/backwards:
        $items = $this->getBranch($start_id, $depth);
        if ( is_object($items) ) {
            $total = count($items);
            $count = 0;
            // Standard PDO fetch:
            while ($item = $items->fetch(PDO::FETCH_ASSOC)) {
                if ( $count == 0 ) {
                    // last item:
                }
                ++$count;
                $parent_path = substr($item['path'], 0, (strrpos($item['path'], $this->config['separator'])) );

                $depth = $item['depth'] - $item['st_depth'] + 1;

                // now get the item:
                $children  = null;
                if ( isset($parts[$item['path']]) ) {
                    $children = $parts[$item['path']];
                }
                $item_string = $this->getItem($item, $depth, $children);

                if ( $item['path'] == $item['st_path'] || $item['depth'] == 1 ) {
                    // top level:
                    if ( $item['id'] == $start_id && $this->config['displayStart'] ) {
                        $output = '';
                    } else {
                        $output = $item_string . $output;
                    }
                } else {
                    if (isset($parts[$parent_path])) {
                        // append to siblings:
                        $parts[$parent_path] = $item_string . $parts[$parent_path];
                    } else {
                        $parts[$parent_path] = $item_string;
                    }
                }

            }

        }
        // now wrap output:
        $output = $this->getWrapper($item, $depth, $output);
        echo $output;
        exit();
        return $output;
    }
    /**
     * @param array $item
     * @param int $depth
     * @param string $children
     * @return string
     */
    protected function getWrapper($item, $depth, $children)
    {
        if (empty($children) ) {
            return '';
        }
        $chunk = null;
        if ( isset($this->chunks['chunkWrapper'.$depth]) && !empty($this->chunks['chunkWrapper'.$depth]) ) {
            $chunk = $this->chunks['chunkWrapper'.$depth];
        } else if ( isset($this->chunks['chunkWrapper']) && !empty($this->chunks['chunkWrapper']) ) {
            $this->chunks['chunkWrapper'];
        }
        if ( empty($chunk) ) {
            $output = '<ul class="wrapper-depth-' . $depth . '">' . PHP_EOL .
                '   ' . $children . PHP_EOL .
                '</ul>';
        } else {
            $placeholders = array(
                'mbClasses' => '',
                'mbChildren' => $children,
                'mbDepth' => $depth
            );
            $placeholders = array_merge($placeholders, $item);
            $output = $this->modx->getChunk($chunk, $placeholders);
        }
        return $output;
    }

    /**
     * @param array $item
     * @param int $depth
     * @param string $children
     * @return string
     */
    protected function getItem($item, $depth, $children)
    {
        // @TODO use the system settings and config override for makeUrl() scheme
        $url = $this->modx->makeUrl($item['id'], '', '', $this->config['scheme']);

        $chunk = null;
        if ( isset($this->chunks['chunkItem'.$depth]) && !empty($this->chunks['chunkItem'.$depth]) ) {
            $chunk = $this->chunks['chunkItem'.$depth];
        } else if ( isset($this->chunks['chunkItem']) && !empty($this->chunks['chunkItem']) ) {
            $this->chunks['chunkItem'];
        }
        if ( empty($chunk) ) {
            $output = PHP_EOL.
                '<li class="item-depth-'.$depth.'">'.PHP_EOL.
                '    <a href="'.$url.'" class="" id="">'.$item['pagetitle'].'</a>'.PHP_EOL.
                // children
                $this->getWrapper($item, $depth+1, $children).
                '</li>'.PHP_EOL;
        } else {
            $placeholders = array(
                'mbClasses' => '',
                'mbItemClasses' => '',
                'mbChildren' => $children,
                'mbDepth' => $depth,
                'mbUrl' => $url,
                // @TODO make option:
                'mbTitle' => $item['pagetitle']
            );
            $placeholders = array_merge($placeholders, $item);
            $output = $this->modx->getChunk($chunk, $placeholders);
        }
        return $output;
    }
}