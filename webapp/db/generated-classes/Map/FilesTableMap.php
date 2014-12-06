<?php

namespace Map;

use \Files;
use \FilesQuery;
use Propel\Runtime\Propel;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\InstancePoolTrait;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\DataFetcher\DataFetcherInterface;
use Propel\Runtime\Exception\PropelException;
use Propel\Runtime\Map\RelationMap;
use Propel\Runtime\Map\TableMap;
use Propel\Runtime\Map\TableMapTrait;


/**
 * This class defines the structure of the 'files' table.
 *
 *
 *
 * This map class is used by Propel to do runtime db structure discovery.
 * For example, the createSelectSql() method checks the type of a given column used in an
 * ORDER BY clause to know whether it needs to apply SQL to make the ORDER BY case-insensitive
 * (i.e. if it's a text column type).
 *
 */
class FilesTableMap extends TableMap
{
    use InstancePoolTrait;
    use TableMapTrait;

    /**
     * The (dot-path) name of this class
     */
    const CLASS_NAME = '.Map.FilesTableMap';

    /**
     * The default database name for this class
     */
    const DATABASE_NAME = 'lessrisk';

    /**
     * The table name for this class
     */
    const TABLE_NAME = 'files';

    /**
     * The related Propel class for this table
     */
    const OM_CLASS = '\\Files';

    /**
     * A class that can be returned by this tableMap
     */
    const CLASS_DEFAULT = 'Files';

    /**
     * The total number of columns
     */
    const NUM_COLUMNS = 9;

    /**
     * The number of lazy-loaded columns
     */
    const NUM_LAZY_LOAD_COLUMNS = 0;

    /**
     * The number of columns to hydrate (NUM_COLUMNS - NUM_LAZY_LOAD_COLUMNS)
     */
    const NUM_HYDRATE_COLUMNS = 9;

    /**
     * the column name for the id field
     */
    const COL_ID = 'files.id';

    /**
     * the column name for the risk_id field
     */
    const COL_RISK_ID = 'files.risk_id';

    /**
     * the column name for the name field
     */
    const COL_NAME = 'files.name';

    /**
     * the column name for the unique_name field
     */
    const COL_UNIQUE_NAME = 'files.unique_name';

    /**
     * the column name for the type field
     */
    const COL_TYPE = 'files.type';

    /**
     * the column name for the size field
     */
    const COL_SIZE = 'files.size';

    /**
     * the column name for the timestamp field
     */
    const COL_TIMESTAMP = 'files.timestamp';

    /**
     * the column name for the user field
     */
    const COL_USER = 'files.user';

    /**
     * the column name for the content field
     */
    const COL_CONTENT = 'files.content';

    /**
     * The default string format for model objects of the related table
     */
    const DEFAULT_STRING_FORMAT = 'YAML';

    /**
     * holds an array of fieldnames
     *
     * first dimension keys are the type constants
     * e.g. self::$fieldNames[self::TYPE_PHPNAME][0] = 'Id'
     */
    protected static $fieldNames = array (
        self::TYPE_PHPNAME       => array('Id', 'RiskId', 'Name', 'UniqueName', 'Type', 'Size', 'Timestamp', 'User', 'Content', ),
        self::TYPE_CAMELNAME     => array('id', 'riskId', 'name', 'uniqueName', 'type', 'size', 'timestamp', 'user', 'content', ),
        self::TYPE_COLNAME       => array(FilesTableMap::COL_ID, FilesTableMap::COL_RISK_ID, FilesTableMap::COL_NAME, FilesTableMap::COL_UNIQUE_NAME, FilesTableMap::COL_TYPE, FilesTableMap::COL_SIZE, FilesTableMap::COL_TIMESTAMP, FilesTableMap::COL_USER, FilesTableMap::COL_CONTENT, ),
        self::TYPE_FIELDNAME     => array('id', 'risk_id', 'name', 'unique_name', 'type', 'size', 'timestamp', 'user', 'content', ),
        self::TYPE_NUM           => array(0, 1, 2, 3, 4, 5, 6, 7, 8, )
    );

    /**
     * holds an array of keys for quick access to the fieldnames array
     *
     * first dimension keys are the type constants
     * e.g. self::$fieldKeys[self::TYPE_PHPNAME]['Id'] = 0
     */
    protected static $fieldKeys = array (
        self::TYPE_PHPNAME       => array('Id' => 0, 'RiskId' => 1, 'Name' => 2, 'UniqueName' => 3, 'Type' => 4, 'Size' => 5, 'Timestamp' => 6, 'User' => 7, 'Content' => 8, ),
        self::TYPE_CAMELNAME     => array('id' => 0, 'riskId' => 1, 'name' => 2, 'uniqueName' => 3, 'type' => 4, 'size' => 5, 'timestamp' => 6, 'user' => 7, 'content' => 8, ),
        self::TYPE_COLNAME       => array(FilesTableMap::COL_ID => 0, FilesTableMap::COL_RISK_ID => 1, FilesTableMap::COL_NAME => 2, FilesTableMap::COL_UNIQUE_NAME => 3, FilesTableMap::COL_TYPE => 4, FilesTableMap::COL_SIZE => 5, FilesTableMap::COL_TIMESTAMP => 6, FilesTableMap::COL_USER => 7, FilesTableMap::COL_CONTENT => 8, ),
        self::TYPE_FIELDNAME     => array('id' => 0, 'risk_id' => 1, 'name' => 2, 'unique_name' => 3, 'type' => 4, 'size' => 5, 'timestamp' => 6, 'user' => 7, 'content' => 8, ),
        self::TYPE_NUM           => array(0, 1, 2, 3, 4, 5, 6, 7, 8, )
    );

    /**
     * Initialize the table attributes and columns
     * Relations are not initialized by this method since they are lazy loaded
     *
     * @return void
     * @throws PropelException
     */
    public function initialize()
    {
        // attributes
        $this->setName('files');
        $this->setPhpName('Files');
        $this->setIdentifierQuoting(false);
        $this->setClassName('\\Files');
        $this->setPackage('');
        $this->setUseIdGenerator(true);
        // columns
        $this->addPrimaryKey('id', 'Id', 'INTEGER', true, null, null);
        $this->addColumn('risk_id', 'RiskId', 'INTEGER', true, null, null);
        $this->addColumn('name', 'Name', 'VARCHAR', true, 100, null);
        $this->addColumn('unique_name', 'UniqueName', 'VARCHAR', true, 30, null);
        $this->addColumn('type', 'Type', 'VARCHAR', true, 30, null);
        $this->addColumn('size', 'Size', 'INTEGER', true, null, null);
        $this->addColumn('timestamp', 'Timestamp', 'TIMESTAMP', true, null, 'CURRENT_TIMESTAMP');
        $this->addColumn('user', 'User', 'INTEGER', true, null, null);
        $this->addColumn('content', 'Content', 'BLOB', true, null, null);
    } // initialize()

    /**
     * Build the RelationMap objects for this table relationships
     */
    public function buildRelations()
    {
    } // buildRelations()

    /**
     * Retrieves a string version of the primary key from the DB resultset row that can be used to uniquely identify a row in this table.
     *
     * For tables with a single-column primary key, that simple pkey value will be returned.  For tables with
     * a multi-column primary key, a serialize()d version of the primary key will be returned.
     *
     * @param array  $row       resultset row.
     * @param int    $offset    The 0-based offset for reading from the resultset row.
     * @param string $indexType One of the class type constants TableMap::TYPE_PHPNAME, TableMap::TYPE_CAMELNAME
     *                           TableMap::TYPE_COLNAME, TableMap::TYPE_FIELDNAME, TableMap::TYPE_NUM
     *
     * @return string The primary key hash of the row
     */
    public static function getPrimaryKeyHashFromRow($row, $offset = 0, $indexType = TableMap::TYPE_NUM)
    {
        // If the PK cannot be derived from the row, return NULL.
        if ($row[TableMap::TYPE_NUM == $indexType ? 0 + $offset : static::translateFieldName('Id', TableMap::TYPE_PHPNAME, $indexType)] === null) {
            return null;
        }

        return (string) $row[TableMap::TYPE_NUM == $indexType ? 0 + $offset : static::translateFieldName('Id', TableMap::TYPE_PHPNAME, $indexType)];
    }

    /**
     * Retrieves the primary key from the DB resultset row
     * For tables with a single-column primary key, that simple pkey value will be returned.  For tables with
     * a multi-column primary key, an array of the primary key columns will be returned.
     *
     * @param array  $row       resultset row.
     * @param int    $offset    The 0-based offset for reading from the resultset row.
     * @param string $indexType One of the class type constants TableMap::TYPE_PHPNAME, TableMap::TYPE_CAMELNAME
     *                           TableMap::TYPE_COLNAME, TableMap::TYPE_FIELDNAME, TableMap::TYPE_NUM
     *
     * @return mixed The primary key of the row
     */
    public static function getPrimaryKeyFromRow($row, $offset = 0, $indexType = TableMap::TYPE_NUM)
    {
        return (int) $row[
            $indexType == TableMap::TYPE_NUM
                ? 0 + $offset
                : self::translateFieldName('Id', TableMap::TYPE_PHPNAME, $indexType)
        ];
    }

    /**
     * The class that the tableMap will make instances of.
     *
     * If $withPrefix is true, the returned path
     * uses a dot-path notation which is translated into a path
     * relative to a location on the PHP include_path.
     * (e.g. path.to.MyClass -> 'path/to/MyClass.php')
     *
     * @param boolean $withPrefix Whether or not to return the path with the class name
     * @return string path.to.ClassName
     */
    public static function getOMClass($withPrefix = true)
    {
        return $withPrefix ? FilesTableMap::CLASS_DEFAULT : FilesTableMap::OM_CLASS;
    }

    /**
     * Populates an object of the default type or an object that inherit from the default.
     *
     * @param array  $row       row returned by DataFetcher->fetch().
     * @param int    $offset    The 0-based offset for reading from the resultset row.
     * @param string $indexType The index type of $row. Mostly DataFetcher->getIndexType().
                                 One of the class type constants TableMap::TYPE_PHPNAME, TableMap::TYPE_CAMELNAME
     *                           TableMap::TYPE_COLNAME, TableMap::TYPE_FIELDNAME, TableMap::TYPE_NUM.
     *
     * @throws PropelException Any exceptions caught during processing will be
     *                         rethrown wrapped into a PropelException.
     * @return array           (Files object, last column rank)
     */
    public static function populateObject($row, $offset = 0, $indexType = TableMap::TYPE_NUM)
    {
        $key = FilesTableMap::getPrimaryKeyHashFromRow($row, $offset, $indexType);
        if (null !== ($obj = FilesTableMap::getInstanceFromPool($key))) {
            // We no longer rehydrate the object, since this can cause data loss.
            // See http://www.propelorm.org/ticket/509
            // $obj->hydrate($row, $offset, true); // rehydrate
            $col = $offset + FilesTableMap::NUM_HYDRATE_COLUMNS;
        } else {
            $cls = FilesTableMap::OM_CLASS;
            /** @var Files $obj */
            $obj = new $cls();
            $col = $obj->hydrate($row, $offset, false, $indexType);
            FilesTableMap::addInstanceToPool($obj, $key);
        }

        return array($obj, $col);
    }

    /**
     * The returned array will contain objects of the default type or
     * objects that inherit from the default.
     *
     * @param DataFetcherInterface $dataFetcher
     * @return array
     * @throws PropelException Any exceptions caught during processing will be
     *                         rethrown wrapped into a PropelException.
     */
    public static function populateObjects(DataFetcherInterface $dataFetcher)
    {
        $results = array();

        // set the class once to avoid overhead in the loop
        $cls = static::getOMClass(false);
        // populate the object(s)
        while ($row = $dataFetcher->fetch()) {
            $key = FilesTableMap::getPrimaryKeyHashFromRow($row, 0, $dataFetcher->getIndexType());
            if (null !== ($obj = FilesTableMap::getInstanceFromPool($key))) {
                // We no longer rehydrate the object, since this can cause data loss.
                // See http://www.propelorm.org/ticket/509
                // $obj->hydrate($row, 0, true); // rehydrate
                $results[] = $obj;
            } else {
                /** @var Files $obj */
                $obj = new $cls();
                $obj->hydrate($row);
                $results[] = $obj;
                FilesTableMap::addInstanceToPool($obj, $key);
            } // if key exists
        }

        return $results;
    }
    /**
     * Add all the columns needed to create a new object.
     *
     * Note: any columns that were marked with lazyLoad="true" in the
     * XML schema will not be added to the select list and only loaded
     * on demand.
     *
     * @param Criteria $criteria object containing the columns to add.
     * @param string   $alias    optional table alias
     * @throws PropelException Any exceptions caught during processing will be
     *                         rethrown wrapped into a PropelException.
     */
    public static function addSelectColumns(Criteria $criteria, $alias = null)
    {
        if (null === $alias) {
            $criteria->addSelectColumn(FilesTableMap::COL_ID);
            $criteria->addSelectColumn(FilesTableMap::COL_RISK_ID);
            $criteria->addSelectColumn(FilesTableMap::COL_NAME);
            $criteria->addSelectColumn(FilesTableMap::COL_UNIQUE_NAME);
            $criteria->addSelectColumn(FilesTableMap::COL_TYPE);
            $criteria->addSelectColumn(FilesTableMap::COL_SIZE);
            $criteria->addSelectColumn(FilesTableMap::COL_TIMESTAMP);
            $criteria->addSelectColumn(FilesTableMap::COL_USER);
            $criteria->addSelectColumn(FilesTableMap::COL_CONTENT);
        } else {
            $criteria->addSelectColumn($alias . '.id');
            $criteria->addSelectColumn($alias . '.risk_id');
            $criteria->addSelectColumn($alias . '.name');
            $criteria->addSelectColumn($alias . '.unique_name');
            $criteria->addSelectColumn($alias . '.type');
            $criteria->addSelectColumn($alias . '.size');
            $criteria->addSelectColumn($alias . '.timestamp');
            $criteria->addSelectColumn($alias . '.user');
            $criteria->addSelectColumn($alias . '.content');
        }
    }

    /**
     * Returns the TableMap related to this object.
     * This method is not needed for general use but a specific application could have a need.
     * @return TableMap
     * @throws PropelException Any exceptions caught during processing will be
     *                         rethrown wrapped into a PropelException.
     */
    public static function getTableMap()
    {
        return Propel::getServiceContainer()->getDatabaseMap(FilesTableMap::DATABASE_NAME)->getTable(FilesTableMap::TABLE_NAME);
    }

    /**
     * Add a TableMap instance to the database for this tableMap class.
     */
    public static function buildTableMap()
    {
        $dbMap = Propel::getServiceContainer()->getDatabaseMap(FilesTableMap::DATABASE_NAME);
        if (!$dbMap->hasTable(FilesTableMap::TABLE_NAME)) {
            $dbMap->addTableObject(new FilesTableMap());
        }
    }

    /**
     * Performs a DELETE on the database, given a Files or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or Files object or primary key or array of primary keys
     *              which is used to create the DELETE statement
     * @param  ConnectionInterface $con the connection to use
     * @return int             The number of affected rows (if supported by underlying database driver).  This includes CASCADE-related rows
     *                         if supported by native driver or if emulated using Propel.
     * @throws PropelException Any exceptions caught during processing will be
     *                         rethrown wrapped into a PropelException.
     */
     public static function doDelete($values, ConnectionInterface $con = null)
     {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(FilesTableMap::DATABASE_NAME);
        }

        if ($values instanceof Criteria) {
            // rename for clarity
            $criteria = $values;
        } elseif ($values instanceof \Files) { // it's a model object
            // create criteria based on pk values
            $criteria = $values->buildPkeyCriteria();
        } else { // it's a primary key, or an array of pks
            $criteria = new Criteria(FilesTableMap::DATABASE_NAME);
            $criteria->add(FilesTableMap::COL_ID, (array) $values, Criteria::IN);
        }

        $query = FilesQuery::create()->mergeWith($criteria);

        if ($values instanceof Criteria) {
            FilesTableMap::clearInstancePool();
        } elseif (!is_object($values)) { // it's a primary key, or an array of pks
            foreach ((array) $values as $singleval) {
                FilesTableMap::removeInstanceFromPool($singleval);
            }
        }

        return $query->delete($con);
    }

    /**
     * Deletes all rows from the files table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public static function doDeleteAll(ConnectionInterface $con = null)
    {
        return FilesQuery::create()->doDeleteAll($con);
    }

    /**
     * Performs an INSERT on the database, given a Files or Criteria object.
     *
     * @param mixed               $criteria Criteria or Files object containing data that is used to create the INSERT statement.
     * @param ConnectionInterface $con the ConnectionInterface connection to use
     * @return mixed           The new primary key.
     * @throws PropelException Any exceptions caught during processing will be
     *                         rethrown wrapped into a PropelException.
     */
    public static function doInsert($criteria, ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(FilesTableMap::DATABASE_NAME);
        }

        if ($criteria instanceof Criteria) {
            $criteria = clone $criteria; // rename for clarity
        } else {
            $criteria = $criteria->buildCriteria(); // build Criteria from Files object
        }

        if ($criteria->containsKey(FilesTableMap::COL_ID) && $criteria->keyContainsValue(FilesTableMap::COL_ID) ) {
            throw new PropelException('Cannot insert a value for auto-increment primary key ('.FilesTableMap::COL_ID.')');
        }


        // Set the correct dbName
        $query = FilesQuery::create()->mergeWith($criteria);

        // use transaction because $criteria could contain info
        // for more than one table (I guess, conceivably)
        return $con->transaction(function () use ($con, $query) {
            return $query->doInsert($con);
        });
    }

} // FilesTableMap
// This is the static code needed to register the TableMap for this table with the main Propel class.
//
FilesTableMap::buildTableMap();
