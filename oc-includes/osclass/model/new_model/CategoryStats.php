<?php

    /*
     *      OSCLass – software for creating and publishing online classified
     *                           advertising platforms
     *
     *                        Copyright (C) 2010 OSCLASS
     *
     *       This program is free software: you can redistribute it and/or
     *     modify it under the terms of the GNU Affero General Public License
     *     as published by the Free Software Foundation, either version 3 of
     *            the License, or (at your option) any later version.
     *
     *     This program is distributed in the hope that it will be useful, but
     *         WITHOUT ANY WARRANTY; without even the implied warranty of
     *        MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
     *             GNU Affero General Public License for more details.
     *
     *      You should have received a copy of the GNU Affero General Public
     * License along with this program.  If not, see <http://www.gnu.org/licenses/>.
     */
    
    /**
     * Model database for CategoryStats table
     *
     * @package OSClass
     * @subpackage Model
     * @since unknown
     */
    class CategoryStats extends DAO
    {
        /**
         * It references to self object: CategotyStats.
         * It is used as a singleton
         *
         * @access private
         * @since unknown
         * @var CategoryStats
         */
        private static $instance ;

        /**
        * It creates a new CategoryStats object class if it has been created
        * before, it return the previous object
        *
        * @access public
        * @since unknown
        * @return CategoryStats
        */
        public static function newInstance()
        {
            if( !self::$instance instanceof self ) {
                self::$instance = new self ;
            }
            return self::$instance ;
        }

        /**
         * Set data related to t_category_stats table
         */
        function __construct()
        {
            parent::__construct();
            $this->setTableName('t_category_stats') ;
            $this->setPrimaryKey('fk_i_category_id') ;
            $this->setFields( array('fk_i_category_id', 'i_num_items') ) ;
        }
        
        /**
         * Increase number of category items, given a category id
         *
         * @access public
         * @since unknown
         * @param int $categoryId Category id 
         * @return int number of affected rows, id error occurred return false
         */
        public function increaseNumItems($categoryId) 
        {
            $sql = sprintf('INSERT INTO %s (fk_i_category_id, i_num_items) VALUES (%d, 1) ON DUPLICATE KEY UPDATE i_num_items = i_num_items + 1', $this->getTableName(), $categoryId);
            $return = $this->dao->query($sql);
            $result = Category::newInstance()->findByPrimaryKey($categoryId);
            if($return !== false) {
                if($result['fk_i_parent_id']!=NULL) {
                    $parent_res = $this->increaseNumItems($result['fk_i_parent_id']);
                    if($parent_res !== false){
                        $return += $parent_res ;
                    }else{
                        $return = false;
                    }
                }
            }
            return $return;
        }
        
        /**
         * Increase number of category items, given a category id
         * 
         * @access public
         * @since unknown
         * @param int $categoryId Category id 
         * @return int number of affected rows, id error occurred return false
         */
        public function decreaseNumItems($categoryId) 
        {
            $this->dao->select('i_num_items') ;
            $this->dao->from($this->getTableName()) ;
            $this->dao->where($this->getPrimaryKey(), $categoryId) ;
            $result = $this->dao->get() ;
            $categoryStat = $result->row() ; 
            $return = 0;
            if(isset($categoryStat['i_num_items'])) {
                $return = $this->dao->update($this->getTableName(), 'i_num_items = i_num_items - 1', 'i_num_items > 0 AND fk_i_category_id = '.$categoryId ) ;
            } else {
                $array_set = array(
                    'fk_i_category_id'  => $categoryId,
                    'i_num_items'       => 0
                );
                $res = $this->dao->insert($this->getTableName(), $array_set);
                if($res === false) {
                    $return = false;
                }
            }
            if($return !== false) {
                $result = Category::newInstance()->findByPrimaryKey($categoryId);
                if($result['fk_i_parent_id']!=NULL) {
                    $parent_res = $this->decreaseNumItems($result['fk_i_parent_id']);
                    if($parent_res !== false){
                        $return += $parent_res ;
                    }else{
                        $return = false;
                    }
                }
            }
            return $return;
        }
        
        /**
         * Find stats by category id
         * 
         * @access public
         * @since unknown
         * @param int $categoryId Category id 
         * @return array CategoryStats
         */
        public function findByCategoryId($categoryId) 
        {
            return $this->findByPrimaryKey($categoryId);
        }
        
        /**
         * Count items,  given a category id
         * 
         * @access public
         * @since unknown
         * @param type $categoryId Category id
         * @return int number of items into category
         */
        public function countItemsFromCategory($categoryId) 
        {
            $this->dao->select('i_num_items') ;
            $this->dao->from($this->getTableName()) ;
            $this->dao->where('fk_i_category_id', $categoryId) ;
            $result = $this->dao->get() ;
            $data = $result->row() ;
            if($data==null) { return 0; } else { return $data['i_num_items']; };
        }
        
        /**
         * Get number of items 
         *
         * @access public
         * @since unknown
         * @staticvar string $numItemsMap 
         * @param array $cat category array
         * @return int
         */
        function getNumItems($cat) 
        {
            static $numItemsMap = null;
            if(is_null($numItemsMap)) {
                $numItemsMap = $this->toNumItemsMap();
            }
            if(isset($numItemsMap['parent'][$cat['pk_i_id']]))
                return $numItemsMap['parent'][$cat['pk_i_id']]['numItems'];
            else if (isset($numItemsMap['subcategories'][$cat['pk_i_id']]))
                return $numItemsMap['subcategories'][$cat['pk_i_id']]['numItems'];
            else
                return 0;
        }
        /**
         *
         * @access public
         * @since unknown
         * @return array
         */
        public function toNumItemsMap() 
        {
            $map = array();
            $all = $this->listAll();
	
	    if( empty($all) ) return array();

            $roots = Category::newInstance()->findRootCategories();

            foreach($all as $a)
                $map[$a['fk_i_category_id']] = $a['i_num_items'];

            $new_map = array();
            foreach($roots as $root ){
                $root_description = Category::newInstance()->findByPrimaryKey($root['pk_i_id']);
                $new_map['parent'][ $root['pk_i_id'] ] =  array('numItems' => @$map[ $root['pk_i_id'] ], 's_name' => @$root_description['s_name'] );
                $subcategories = Category::newInstance()->findSubcategories($root['pk_i_id']);
                $aux = array();
                foreach($subcategories as $sub) {
                    $sub_description = Category::newInstance()->findByPrimaryKey($sub['pk_i_id']);
                    $aux[$sub['pk_i_id']] = array('numItems' => $map[$sub['pk_i_id']], 's_name' => $sub_description['s_name'] );
                }
                $new_map['subcategories'][$root['pk_i_id']] = $aux;
            }
            return $new_map;
        }
    }
?>