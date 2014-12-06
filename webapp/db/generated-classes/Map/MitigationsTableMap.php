<?php

namespace Map;

use \Mitigations;
use \MitigationsQuery;
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
 * This class defines the structure of the 'mitigations' table.
 *
 *
 *
 * This map class is used by Propel to do runtime db structure discovery.
 * For example, the createSelectSql() method checks the type of a given column used in an
 * ORDER BY clause to know whether it needs to apply SQL to make the ORDER BY case-insensitive
 * (i.e. if it's a text column type).
 *
 */
class MitigationsTableMap extends TableMap
{
    use InstancePoolTrait;
    use TableMapTrait;

    /**
     * The (dot-path) name of this class
     */
    const CLASS_NAME = '.Map.MitigationsTableMap';

    /**
     * The default database name for this class
     */
    const DATABASE_NAME = 'lessrisk';

    /**
     * The table name for this class
     */
    const TABLE_NAME = 'mitigations';

    /**
     * The related Propel class for this table
     */
    const OM_CLASS = '\\Mitigations';

    /**
     * A class that can be returned by this tableMap
     */
    const CLASS_DEFAULT = 'Mitigations';

    /**
     * The total number of columns
     */
    const NUM_COLUMNS = 10;

    /**
     * The number of lazy-loaded columns
     */
    const NUM_LAZY_LOAD_COLUMNS = 0;

    /**
     * The number of columns to hydrate (NUM_COLUMNS - NUM_LAZY_LOAD_COLUMNS)
     */
    const NUM_HYDRATE_COLUMNS = 10;

    /**
     * the column name for the id field
     */
    const COL_ID = 'mitigations.id';

    /**
     * the column name for the risk_id field
     */
    const COL_RISK_ID = 'mitigations.risk_id';

    /**
     * the column name for the submission_date field
     */
    const COL_SUBMISSION_DATE = 'mitigations.submission_date';

    /**
     * the column name for the last_update field
     */
    const COL_LAST_UPDATE = 'mitigations.last_update';

    /**
     * the column name for the planning_strategy field
     */
    const COL_PLANNING_STRATEGY = 'mitigations.planning_strategy';

    /**
     * the column name for the mitigation_effort field
     */
    const COL_MITIGATION_EFFORT = 'mitigations.mitigation_effort';

    /**
     * the column name for the current_solution field
     */
    const COL_CURRENT_SOLUTION = 'mitigations.current_solution';

    /**
     * the column name for the security_requirements field
     */
    const COL_SECURITY_REQUIREMENTS = 'mitigations.security_requirements';

    /**
     * the column name for the security_recommendations field
     */
    const COL_SECURITY_RECOMMENDATIONS = 'mitigations.security_recommendations';

    /**
     * the column name for the submitted_by field
     */
    const COL_SUBMITTED_BY = 'mitigations.submitted_by';

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
        self::TYPE_PHPNAME       => array('Id', 'RiskId', 'SubmissionDate', 'LastUpdate', 'PlanningStrategy', 'MitigationEffort', 'CurrentSolution', 'SecurityRequirements', 'SecurityRecommendations', 'SubmittedBy', ),
        self::TYPE_CAMELNAME     => array('id', 'riskId', 'submissionDate', 'lastUpdate', 'planningStrategy', 'mitigationEffort', 'currentSolution', 'securityRequirements', 'securityRecommendations', 'submittedBy', ),
        self::TYPE_COLNAME       => array(MitigationsTableMap::COL_ID, MitigationsTableMap::COL_RISK_ID, MitigationsTableMap::COL_SUBMISSION_DATE, MitigationsTableMap::COL_LAST_UPDATE, MitigationsTableMap::COL_PLANNING_STRATEGY, MitigationsTableMap::COL_MITIGATION_EFFORT, MitigationsTableMap::COL_CURRENT_SOLUTION, MitigationsTableMap::COL_SECURITY_REQUIREMENTS, MitigationsTableMap::COL_SECURITY_RECOMMENDATIONS, MitigationsTableMap::COL_SUBMITTED_BY, ),
        self::TYPE_FIELDNAME     => array('id', 'risk_id', 'submission_date', 'last_update', 'planning_strategy', 'mitigation_effort', 'current_solution', 'security_requirements', 'security_recommendations', 'submitted_by', ),
        self::TYPE_NUM           => array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9, )
    );

    /**
     * holds an array of keys for quick access to the fieldnames array
     *
     * first dimension keys are the type constants
     * e.g. self::$fieldKeys[self::TYPE_PHPNAME]['Id'] = 0
     */
    protected static $fieldKeys = array (
        self::TYPE_PHPNAME       => array('Id' => 0, 'RiskId' => 1, 'SubmissionDate' => 2, 'LastUpdate' => 3, 'PlanningStrategy' => 4, 'MitigationEffort' => 5, 'CurrentSolution' => 6, 'SecurityRequirements' => 7, 'SecurityRecommendations' => 8, 'SubmittedBy' => 9, ),
        self::TYPE_CAMELNAME     => array('id' => 0, 'riskId' => 1, 'submissionDate' => 2, 'lastUpdate' => 3, 'planningStrategy' => 4, 'mitigationEffort' => 5, 'currentSolution' => 6, 'securityRequirements' => 7, 'securityRecommendations' => 8, 'submittedBy' => 9, ),
        self::TYPE_COLNAME       => array(MitigationsTableMap::COL_ID => 0, MitigationsTableMap::COL_RISK_ID => 1, MitigationsTableMap::COL_SUBMISSION_DATE => 2, MitigationsTableMap::COL_LAST_UPDATE => 3, MitigationsTableMap::COL_PLANNING_STRATEGY => 4, MitigationsTableMap::COL_MITIGATION_EFFORT => 5, MitigationsTableMap::COL_CURRENT_SOLUTION => 6, MitigationsTableMap::COL_SECURITY_REQUIREMENTS => 7, MitigationsTableMap::COL_SECURITY_RECOMMENDATIONS => 8, MitigationsTableMap::COL_SUBMITTED_BY => 9, ),
        self::TYPE_FIELDNAME     => array('id' => 0, 'risk_id' => 1, 'submission_date' => 2, 'last_update' => 3, 'planning_strategy' => 4, 'mitigation_effort' => 5, 'current_solution' => 6, 'security_requirements' => 7, 'security_recommendations' => 8, 'submitted_by' => 9, ),
        self::TYPE_NUM           => array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9, )
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
        $this->setName('mitigations');
        $this->setPhpName('Mitigations');
        $this->setIdentifierQuoting(false);
        $this->setClassName('\\Mitigations');
        $this->setPackage('');
        $this->setUseIdGenerator(true);
        // columns
        $this->addPrimaryKey('id', 'Id', 'INTEGER', true, null, null);
        $this->addColumn('risk_id', 'RiskId', 'INTEGER', true, null, null);
        $this->addColumn('submission_date', 'SubmissionDate', 'TIMESTAMP', true, null, 'CURRENT_TIMESTAMP');
        $this->addColumn('last_update', 'LastUpdate', 'TIMESTAMP', true, null, '0000-00-00 00:00:00');
        $this->addColumn('planning_strategy', 'PlanningStrategy', 'INTEGER', true, null, null);
        $this->addColumn('mitigation_effort', 'MitigationEffort', 'INTEGER', true, null, null);
        $this->addColumn('current_solution', 'CurrentSolution', 'LONGVARCHAR', true, null, null);
        $this->addColumn('security_requirements', 'SecurityRequirements', 'LONGVARCHAR', true, null, null);
        $this->addColumn('security_recommendations', 'SecurityRecommendations', 'LONGVARCHAR', true, null, null);
        $this->addColumn('submitted_by', 'SubmittedBy', 'INTEGER', true, null, 1);
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
        return $withPrefix ? MitigationsTableMap::CLASS_DEFAULT : MitigationsTableMap::OM_CLASS;
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
     * @return array           (Mitigations object, last column rank)
     */
    public static function populateObject($row, $offset = 0, $indexType = TableMap::TYPE_NUM)
    {
        $key = MitigationsTableMap::getPrimaryKeyHashFromRow($row, $offset, $indexType);
        if (null !== ($obj = MitigationsTableMap::getInstanceFromPool($key))) {
            // We no longer rehydrate the object, since this can cause data loss.
            // See http://www.propelorm.org/ticket/509
            // $obj->hydrate($row, $offset, true); // rehydrate
            $col = $offset + MitigationsTableMap::NUM_HYDRATE_COLUMNS;
        } else {
            $cls = MitigationsTableMap::OM_CLASS;
            /** @var Mitigations $obj */
            $obj = new $cls();
            $col = $obj->hydrate($row, $offset, false, $indexType);
            MitigationsTableMap::addInstanceToPool($obj, $key);
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
            $key = MitigationsTableMap::getPrimaryKeyHashFromRow($row, 0, $dataFetcher->getIndexType());
            if (null !== ($obj = MitigationsTableMap::getInstanceFromPool($key))) {
                // We no longer rehydrate the object, since this can cause data loss.
                // See http://www.propelorm.org/ticket/509
                // $obj->hydrate($row, 0, true); // rehydrate
                $results[] = $obj;
            } else {
                /** @var Mitigations $obj */
                $obj = new $cls();
                $obj->hydrate($row);
                $results[] = $obj;
                MitigationsTableMap::addInstanceToPool($obj, $key);
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
            $criteria->addSelectColumn(MitigationsTableMap::COL_ID);
            $criteria->addSelectColumn(MitigationsTableMap::COL_RISK_ID);
            $criteria->addSelectColumn(MitigationsTableMap::COL_SUBMISSION_DATE);
            $criteria->addSelectColumn(MitigationsTableMap::COL_LAST_UPDATE);
            $criteria->addSelectColumn(MitigationsTableMap::COL_PLANNING_STRATEGY);
            $criteria->addSelectColumn(MitigationsTableMap::COL_MITIGATION_EFFORT);
            $criteria->addSelectColumn(MitigationsTableMap::COL_CURRENT_SOLUTION);
            $criteria->addSelectColumn(MitigationsTableMap::COL_SECURITY_REQUIREMENTS);
            $criteria->addSelectColumn(MitigationsTableMap::COL_SECURITY_RECOMMENDATIONS);
            $criteria->addSelectColumn(MitigationsTableMap::COL_SUBMITTED_BY);
        } else {
            $criteria->addSelectColumn($alias . '.id');
            $criteria->addSelectColumn($alias . '.risk_id');
            $criteria->addSelectColumn($alias . '.submission_date');
            $criteria->addSelectColumn($alias . '.last_update');
            $criteria->addSelectColumn($alias . '.planning_strategy');
            $criteria->addSelectColumn($alias . '.mitigation_effort');
            $criteria->addSelectColumn($alias . '.current_solution');
            $criteria->addSelectColumn($alias . '.security_requirements');
            $criteria->addSelectColumn($alias . '.security_recommendations');
            $criteria->addSelectColumn($alias . '.submitted_by');
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
        return Propel::getServiceContainer()->getDatabaseMap(MitigationsTableMap::DATABASE_NAME)->getTable(MitigationsTableMap::TABLE_NAME);
    }

    /**
     * Add a TableMap instance to the database for this tableMap class.
     */
    public static function buildTableMap()
    {
        $dbMap = Propel::getServiceContainer()->getDatabaseMap(MitigationsTableMap::DATABASE_NAME);
        if (!$dbMap->hasTable(MitigationsTableMap::TABLE_NAME)) {
            $dbMap->addTableObject(new MitigationsTableMap());
        }
    }

    /**
     * Performs a DELETE on the database, given a Mitigations or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or Mitigations object or primary key or array of primary keys
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
            $con = Propel::getServiceContainer()->getWriteConnection(MitigationsTableMap::DATABASE_NAME);
        }

        if ($values instanceof Criteria) {
            // rename for clarity
            $criteria = $values;
        } elseif ($values instanceof \Mitigations) { // it's a model object
            // create criteria based on pk values
            $criteria = $values->buildPkeyCriteria();
        } else { // it's a primary key, or an array of pks
            $criteria = new Criteria(MitigationsTableMap::DATABASE_NAME);
            $criteria->add(MitigationsTableMap::COL_ID, (array) $values, Criteria::IN);
        }

        $query = MitigationsQuery::create()->mergeWith($criteria);

        if ($values instanceof Criteria) {
            MitigationsTableMap::clearInstancePool();
        } elseif (!is_object($values)) { // it's a primary key, or an array of pks
            foreach ((array) $values as $singleval) {
                MitigationsTableMap::removeInstanceFromPool($singleval);
            }
        }

        return $query->delete($con);
    }

    /**
     * Deletes all rows from the mitigations table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public static function doDeleteAll(ConnectionInterface $con = null)
    {
        return MitigationsQuery::create()->doDeleteAll($con);
    }

    /**
     * Performs an INSERT on the database, given a Mitigations or Criteria object.
     *
     * @param mixed               $criteria Criteria or Mitigations object containing data that is used to create the INSERT statement.
     * @param ConnectionInterface $con the ConnectionInterface connection to use
     * @return mixed           The new primary key.
     * @throws PropelException Any exceptions caught during processing will be
     *                         rethrown wrapped into a PropelException.
     */
    public static function doInsert($criteria, ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(MitigationsTableMap::DATABASE_NAME);
        }

        if ($criteria instanceof Criteria) {
            $criteria = clone $criteria; // rename for clarity
        } else {
            $criteria = $criteria->buildCriteria(); // build Criteria from Mitigations object
        }

        if ($criteria->containsKey(MitigationsTableMap::COL_ID) && $criteria->keyContainsValue(MitigationsTableMap::COL_ID) ) {
            throw new PropelException('Cannot insert a value for auto-increment primary key ('.MitigationsTableMap::COL_ID.')');
        }


        // Set the correct dbName
        $query = MitigationsQuery::create()->mergeWith($criteria);

        // use transaction because $criteria could contain info
        // for more than one table (I guess, conceivably)
        return $con->transaction(function () use ($con, $query) {
            return $query->doInsert($con);
        });
    }

} // MitigationsTableMap
// This is the static code needed to register the TableMap for this table with the main Propel class.
//
MitigationsTableMap::buildTableMap();
