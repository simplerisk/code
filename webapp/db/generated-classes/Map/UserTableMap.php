<?php

namespace Map;

use \User;
use \UserQuery;
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
 * This class defines the structure of the 'user' table.
 *
 *
 *
 * This map class is used by Propel to do runtime db structure discovery.
 * For example, the createSelectSql() method checks the type of a given column used in an
 * ORDER BY clause to know whether it needs to apply SQL to make the ORDER BY case-insensitive
 * (i.e. if it's a text column type).
 *
 */
class UserTableMap extends TableMap
{
    use InstancePoolTrait;
    use TableMapTrait;

    /**
     * The (dot-path) name of this class
     */
    const CLASS_NAME = '.Map.UserTableMap';

    /**
     * The default database name for this class
     */
    const DATABASE_NAME = 'lessrisk';

    /**
     * The table name for this class
     */
    const TABLE_NAME = 'user';

    /**
     * The related Propel class for this table
     */
    const OM_CLASS = '\\User';

    /**
     * A class that can be returned by this tableMap
     */
    const CLASS_DEFAULT = 'User';

    /**
     * The total number of columns
     */
    const NUM_COLUMNS = 20;

    /**
     * The number of lazy-loaded columns
     */
    const NUM_LAZY_LOAD_COLUMNS = 0;

    /**
     * The number of columns to hydrate (NUM_COLUMNS - NUM_LAZY_LOAD_COLUMNS)
     */
    const NUM_HYDRATE_COLUMNS = 20;

    /**
     * the column name for the value field
     */
    const COL_VALUE = 'user.value';

    /**
     * the column name for the enabled field
     */
    const COL_ENABLED = 'user.enabled';

    /**
     * the column name for the type field
     */
    const COL_TYPE = 'user.type';

    /**
     * the column name for the username field
     */
    const COL_USERNAME = 'user.username';

    /**
     * the column name for the name field
     */
    const COL_NAME = 'user.name';

    /**
     * the column name for the email field
     */
    const COL_EMAIL = 'user.email';

    /**
     * the column name for the salt field
     */
    const COL_SALT = 'user.salt';

    /**
     * the column name for the password field
     */
    const COL_PASSWORD = 'user.password';

    /**
     * the column name for the last_login field
     */
    const COL_LAST_LOGIN = 'user.last_login';

    /**
     * the column name for the teams field
     */
    const COL_TEAMS = 'user.teams';

    /**
     * the column name for the lang field
     */
    const COL_LANG = 'user.lang';

    /**
     * the column name for the admin field
     */
    const COL_ADMIN = 'user.admin';

    /**
     * the column name for the review_high field
     */
    const COL_REVIEW_HIGH = 'user.review_high';

    /**
     * the column name for the review_medium field
     */
    const COL_REVIEW_MEDIUM = 'user.review_medium';

    /**
     * the column name for the review_low field
     */
    const COL_REVIEW_LOW = 'user.review_low';

    /**
     * the column name for the submit_risks field
     */
    const COL_SUBMIT_RISKS = 'user.submit_risks';

    /**
     * the column name for the modify_risks field
     */
    const COL_MODIFY_RISKS = 'user.modify_risks';

    /**
     * the column name for the plan_mitigations field
     */
    const COL_PLAN_MITIGATIONS = 'user.plan_mitigations';

    /**
     * the column name for the close_risks field
     */
    const COL_CLOSE_RISKS = 'user.close_risks';

    /**
     * the column name for the multi_factor field
     */
    const COL_MULTI_FACTOR = 'user.multi_factor';

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
        self::TYPE_PHPNAME       => array('Value', 'Enabled', 'Type', 'Username', 'Name', 'Email', 'Salt', 'Password', 'LastLogin', 'Teams', 'Lang', 'Admin', 'ReviewHigh', 'ReviewMedium', 'ReviewLow', 'SubmitRisks', 'ModifyRisks', 'PlanMitigations', 'CloseRisks', 'MultiFactor', ),
        self::TYPE_CAMELNAME     => array('value', 'enabled', 'type', 'username', 'name', 'email', 'salt', 'password', 'lastLogin', 'teams', 'lang', 'admin', 'reviewHigh', 'reviewMedium', 'reviewLow', 'submitRisks', 'modifyRisks', 'planMitigations', 'closeRisks', 'multiFactor', ),
        self::TYPE_COLNAME       => array(UserTableMap::COL_VALUE, UserTableMap::COL_ENABLED, UserTableMap::COL_TYPE, UserTableMap::COL_USERNAME, UserTableMap::COL_NAME, UserTableMap::COL_EMAIL, UserTableMap::COL_SALT, UserTableMap::COL_PASSWORD, UserTableMap::COL_LAST_LOGIN, UserTableMap::COL_TEAMS, UserTableMap::COL_LANG, UserTableMap::COL_ADMIN, UserTableMap::COL_REVIEW_HIGH, UserTableMap::COL_REVIEW_MEDIUM, UserTableMap::COL_REVIEW_LOW, UserTableMap::COL_SUBMIT_RISKS, UserTableMap::COL_MODIFY_RISKS, UserTableMap::COL_PLAN_MITIGATIONS, UserTableMap::COL_CLOSE_RISKS, UserTableMap::COL_MULTI_FACTOR, ),
        self::TYPE_FIELDNAME     => array('value', 'enabled', 'type', 'username', 'name', 'email', 'salt', 'password', 'last_login', 'teams', 'lang', 'admin', 'review_high', 'review_medium', 'review_low', 'submit_risks', 'modify_risks', 'plan_mitigations', 'close_risks', 'multi_factor', ),
        self::TYPE_NUM           => array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, )
    );

    /**
     * holds an array of keys for quick access to the fieldnames array
     *
     * first dimension keys are the type constants
     * e.g. self::$fieldKeys[self::TYPE_PHPNAME]['Id'] = 0
     */
    protected static $fieldKeys = array (
        self::TYPE_PHPNAME       => array('Value' => 0, 'Enabled' => 1, 'Type' => 2, 'Username' => 3, 'Name' => 4, 'Email' => 5, 'Salt' => 6, 'Password' => 7, 'LastLogin' => 8, 'Teams' => 9, 'Lang' => 10, 'Admin' => 11, 'ReviewHigh' => 12, 'ReviewMedium' => 13, 'ReviewLow' => 14, 'SubmitRisks' => 15, 'ModifyRisks' => 16, 'PlanMitigations' => 17, 'CloseRisks' => 18, 'MultiFactor' => 19, ),
        self::TYPE_CAMELNAME     => array('value' => 0, 'enabled' => 1, 'type' => 2, 'username' => 3, 'name' => 4, 'email' => 5, 'salt' => 6, 'password' => 7, 'lastLogin' => 8, 'teams' => 9, 'lang' => 10, 'admin' => 11, 'reviewHigh' => 12, 'reviewMedium' => 13, 'reviewLow' => 14, 'submitRisks' => 15, 'modifyRisks' => 16, 'planMitigations' => 17, 'closeRisks' => 18, 'multiFactor' => 19, ),
        self::TYPE_COLNAME       => array(UserTableMap::COL_VALUE => 0, UserTableMap::COL_ENABLED => 1, UserTableMap::COL_TYPE => 2, UserTableMap::COL_USERNAME => 3, UserTableMap::COL_NAME => 4, UserTableMap::COL_EMAIL => 5, UserTableMap::COL_SALT => 6, UserTableMap::COL_PASSWORD => 7, UserTableMap::COL_LAST_LOGIN => 8, UserTableMap::COL_TEAMS => 9, UserTableMap::COL_LANG => 10, UserTableMap::COL_ADMIN => 11, UserTableMap::COL_REVIEW_HIGH => 12, UserTableMap::COL_REVIEW_MEDIUM => 13, UserTableMap::COL_REVIEW_LOW => 14, UserTableMap::COL_SUBMIT_RISKS => 15, UserTableMap::COL_MODIFY_RISKS => 16, UserTableMap::COL_PLAN_MITIGATIONS => 17, UserTableMap::COL_CLOSE_RISKS => 18, UserTableMap::COL_MULTI_FACTOR => 19, ),
        self::TYPE_FIELDNAME     => array('value' => 0, 'enabled' => 1, 'type' => 2, 'username' => 3, 'name' => 4, 'email' => 5, 'salt' => 6, 'password' => 7, 'last_login' => 8, 'teams' => 9, 'lang' => 10, 'admin' => 11, 'review_high' => 12, 'review_medium' => 13, 'review_low' => 14, 'submit_risks' => 15, 'modify_risks' => 16, 'plan_mitigations' => 17, 'close_risks' => 18, 'multi_factor' => 19, ),
        self::TYPE_NUM           => array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, )
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
        $this->setName('user');
        $this->setPhpName('User');
        $this->setIdentifierQuoting(false);
        $this->setClassName('\\User');
        $this->setPackage('');
        $this->setUseIdGenerator(true);
        // columns
        $this->addPrimaryKey('value', 'Value', 'INTEGER', true, null, null);
        $this->addColumn('enabled', 'Enabled', 'BOOLEAN', true, 1, true);
        $this->addColumn('type', 'Type', 'VARCHAR', true, 20, 'simplerisk');
        $this->addColumn('username', 'Username', 'VARCHAR', true, 20, null);
        $this->addColumn('name', 'Name', 'VARCHAR', true, 50, null);
        $this->addColumn('email', 'Email', 'VARCHAR', true, 200, null);
        $this->addColumn('salt', 'Salt', 'VARCHAR', false, 20, null);
        $this->addColumn('password', 'Password', 'VARCHAR', true, 60, null);
        $this->addColumn('last_login', 'LastLogin', 'TIMESTAMP', true, null, null);
        $this->addColumn('teams', 'Teams', 'VARCHAR', true, 200, 'none');
        $this->addColumn('lang', 'Lang', 'VARCHAR', false, 2, null);
        $this->addColumn('admin', 'Admin', 'BOOLEAN', true, 1, false);
        $this->addColumn('review_high', 'ReviewHigh', 'BOOLEAN', true, 1, false);
        $this->addColumn('review_medium', 'ReviewMedium', 'BOOLEAN', true, 1, false);
        $this->addColumn('review_low', 'ReviewLow', 'BOOLEAN', true, 1, false);
        $this->addColumn('submit_risks', 'SubmitRisks', 'BOOLEAN', true, 1, false);
        $this->addColumn('modify_risks', 'ModifyRisks', 'BOOLEAN', true, 1, false);
        $this->addColumn('plan_mitigations', 'PlanMitigations', 'BOOLEAN', true, 1, false);
        $this->addColumn('close_risks', 'CloseRisks', 'BOOLEAN', true, 1, true);
        $this->addColumn('multi_factor', 'MultiFactor', 'INTEGER', true, null, 1);
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
        if ($row[TableMap::TYPE_NUM == $indexType ? 0 + $offset : static::translateFieldName('Value', TableMap::TYPE_PHPNAME, $indexType)] === null) {
            return null;
        }

        return (string) $row[TableMap::TYPE_NUM == $indexType ? 0 + $offset : static::translateFieldName('Value', TableMap::TYPE_PHPNAME, $indexType)];
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
                : self::translateFieldName('Value', TableMap::TYPE_PHPNAME, $indexType)
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
        return $withPrefix ? UserTableMap::CLASS_DEFAULT : UserTableMap::OM_CLASS;
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
     * @return array           (User object, last column rank)
     */
    public static function populateObject($row, $offset = 0, $indexType = TableMap::TYPE_NUM)
    {
        $key = UserTableMap::getPrimaryKeyHashFromRow($row, $offset, $indexType);
        if (null !== ($obj = UserTableMap::getInstanceFromPool($key))) {
            // We no longer rehydrate the object, since this can cause data loss.
            // See http://www.propelorm.org/ticket/509
            // $obj->hydrate($row, $offset, true); // rehydrate
            $col = $offset + UserTableMap::NUM_HYDRATE_COLUMNS;
        } else {
            $cls = UserTableMap::OM_CLASS;
            /** @var User $obj */
            $obj = new $cls();
            $col = $obj->hydrate($row, $offset, false, $indexType);
            UserTableMap::addInstanceToPool($obj, $key);
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
            $key = UserTableMap::getPrimaryKeyHashFromRow($row, 0, $dataFetcher->getIndexType());
            if (null !== ($obj = UserTableMap::getInstanceFromPool($key))) {
                // We no longer rehydrate the object, since this can cause data loss.
                // See http://www.propelorm.org/ticket/509
                // $obj->hydrate($row, 0, true); // rehydrate
                $results[] = $obj;
            } else {
                /** @var User $obj */
                $obj = new $cls();
                $obj->hydrate($row);
                $results[] = $obj;
                UserTableMap::addInstanceToPool($obj, $key);
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
            $criteria->addSelectColumn(UserTableMap::COL_VALUE);
            $criteria->addSelectColumn(UserTableMap::COL_ENABLED);
            $criteria->addSelectColumn(UserTableMap::COL_TYPE);
            $criteria->addSelectColumn(UserTableMap::COL_USERNAME);
            $criteria->addSelectColumn(UserTableMap::COL_NAME);
            $criteria->addSelectColumn(UserTableMap::COL_EMAIL);
            $criteria->addSelectColumn(UserTableMap::COL_SALT);
            $criteria->addSelectColumn(UserTableMap::COL_PASSWORD);
            $criteria->addSelectColumn(UserTableMap::COL_LAST_LOGIN);
            $criteria->addSelectColumn(UserTableMap::COL_TEAMS);
            $criteria->addSelectColumn(UserTableMap::COL_LANG);
            $criteria->addSelectColumn(UserTableMap::COL_ADMIN);
            $criteria->addSelectColumn(UserTableMap::COL_REVIEW_HIGH);
            $criteria->addSelectColumn(UserTableMap::COL_REVIEW_MEDIUM);
            $criteria->addSelectColumn(UserTableMap::COL_REVIEW_LOW);
            $criteria->addSelectColumn(UserTableMap::COL_SUBMIT_RISKS);
            $criteria->addSelectColumn(UserTableMap::COL_MODIFY_RISKS);
            $criteria->addSelectColumn(UserTableMap::COL_PLAN_MITIGATIONS);
            $criteria->addSelectColumn(UserTableMap::COL_CLOSE_RISKS);
            $criteria->addSelectColumn(UserTableMap::COL_MULTI_FACTOR);
        } else {
            $criteria->addSelectColumn($alias . '.value');
            $criteria->addSelectColumn($alias . '.enabled');
            $criteria->addSelectColumn($alias . '.type');
            $criteria->addSelectColumn($alias . '.username');
            $criteria->addSelectColumn($alias . '.name');
            $criteria->addSelectColumn($alias . '.email');
            $criteria->addSelectColumn($alias . '.salt');
            $criteria->addSelectColumn($alias . '.password');
            $criteria->addSelectColumn($alias . '.last_login');
            $criteria->addSelectColumn($alias . '.teams');
            $criteria->addSelectColumn($alias . '.lang');
            $criteria->addSelectColumn($alias . '.admin');
            $criteria->addSelectColumn($alias . '.review_high');
            $criteria->addSelectColumn($alias . '.review_medium');
            $criteria->addSelectColumn($alias . '.review_low');
            $criteria->addSelectColumn($alias . '.submit_risks');
            $criteria->addSelectColumn($alias . '.modify_risks');
            $criteria->addSelectColumn($alias . '.plan_mitigations');
            $criteria->addSelectColumn($alias . '.close_risks');
            $criteria->addSelectColumn($alias . '.multi_factor');
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
        return Propel::getServiceContainer()->getDatabaseMap(UserTableMap::DATABASE_NAME)->getTable(UserTableMap::TABLE_NAME);
    }

    /**
     * Add a TableMap instance to the database for this tableMap class.
     */
    public static function buildTableMap()
    {
        $dbMap = Propel::getServiceContainer()->getDatabaseMap(UserTableMap::DATABASE_NAME);
        if (!$dbMap->hasTable(UserTableMap::TABLE_NAME)) {
            $dbMap->addTableObject(new UserTableMap());
        }
    }

    /**
     * Performs a DELETE on the database, given a User or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or User object or primary key or array of primary keys
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
            $con = Propel::getServiceContainer()->getWriteConnection(UserTableMap::DATABASE_NAME);
        }

        if ($values instanceof Criteria) {
            // rename for clarity
            $criteria = $values;
        } elseif ($values instanceof \User) { // it's a model object
            // create criteria based on pk values
            $criteria = $values->buildPkeyCriteria();
        } else { // it's a primary key, or an array of pks
            $criteria = new Criteria(UserTableMap::DATABASE_NAME);
            $criteria->add(UserTableMap::COL_VALUE, (array) $values, Criteria::IN);
        }

        $query = UserQuery::create()->mergeWith($criteria);

        if ($values instanceof Criteria) {
            UserTableMap::clearInstancePool();
        } elseif (!is_object($values)) { // it's a primary key, or an array of pks
            foreach ((array) $values as $singleval) {
                UserTableMap::removeInstanceFromPool($singleval);
            }
        }

        return $query->delete($con);
    }

    /**
     * Deletes all rows from the user table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public static function doDeleteAll(ConnectionInterface $con = null)
    {
        return UserQuery::create()->doDeleteAll($con);
    }

    /**
     * Performs an INSERT on the database, given a User or Criteria object.
     *
     * @param mixed               $criteria Criteria or User object containing data that is used to create the INSERT statement.
     * @param ConnectionInterface $con the ConnectionInterface connection to use
     * @return mixed           The new primary key.
     * @throws PropelException Any exceptions caught during processing will be
     *                         rethrown wrapped into a PropelException.
     */
    public static function doInsert($criteria, ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(UserTableMap::DATABASE_NAME);
        }

        if ($criteria instanceof Criteria) {
            $criteria = clone $criteria; // rename for clarity
        } else {
            $criteria = $criteria->buildCriteria(); // build Criteria from User object
        }

        if ($criteria->containsKey(UserTableMap::COL_VALUE) && $criteria->keyContainsValue(UserTableMap::COL_VALUE) ) {
            throw new PropelException('Cannot insert a value for auto-increment primary key ('.UserTableMap::COL_VALUE.')');
        }


        // Set the correct dbName
        $query = UserQuery::create()->mergeWith($criteria);

        // use transaction because $criteria could contain info
        // for more than one table (I guess, conceivably)
        return $con->transaction(function () use ($con, $query) {
            return $query->doInsert($con);
        });
    }

} // UserTableMap
// This is the static code needed to register the TableMap for this table with the main Propel class.
//
UserTableMap::buildTableMap();
