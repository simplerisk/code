<?php

namespace Map;

use \Risks;
use \RisksQuery;
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
 * This class defines the structure of the 'risks' table.
 *
 *
 *
 * This map class is used by Propel to do runtime db structure discovery.
 * For example, the createSelectSql() method checks the type of a given column used in an
 * ORDER BY clause to know whether it needs to apply SQL to make the ORDER BY case-insensitive
 * (i.e. if it's a text column type).
 *
 */
class RisksTableMap extends TableMap
{
    use InstancePoolTrait;
    use TableMapTrait;

    /**
     * The (dot-path) name of this class
     */
    const CLASS_NAME = '.Map.RisksTableMap';

    /**
     * The default database name for this class
     */
    const DATABASE_NAME = 'lessrisk';

    /**
     * The table name for this class
     */
    const TABLE_NAME = 'risks';

    /**
     * The related Propel class for this table
     */
    const OM_CLASS = '\\Risks';

    /**
     * A class that can be returned by this tableMap
     */
    const CLASS_DEFAULT = 'Risks';

    /**
     * The total number of columns
     */
    const NUM_COLUMNS = 23;

    /**
     * The number of lazy-loaded columns
     */
    const NUM_LAZY_LOAD_COLUMNS = 0;

    /**
     * The number of columns to hydrate (NUM_COLUMNS - NUM_LAZY_LOAD_COLUMNS)
     */
    const NUM_HYDRATE_COLUMNS = 23;

    /**
     * the column name for the id field
     */
    const COL_ID = 'risks.id';

    /**
     * the column name for the status field
     */
    const COL_STATUS = 'risks.status';

    /**
     * the column name for the subject field
     */
    const COL_SUBJECT = 'risks.subject';

    /**
     * the column name for the reference_id field
     */
    const COL_REFERENCE_ID = 'risks.reference_id';

    /**
     * the column name for the regulation field
     */
    const COL_REGULATION = 'risks.regulation';

    /**
     * the column name for the control_number field
     */
    const COL_CONTROL_NUMBER = 'risks.control_number';

    /**
     * the column name for the location field
     */
    const COL_LOCATION = 'risks.location';

    /**
     * the column name for the category field
     */
    const COL_CATEGORY = 'risks.category';

    /**
     * the column name for the team field
     */
    const COL_TEAM = 'risks.team';

    /**
     * the column name for the technology field
     */
    const COL_TECHNOLOGY = 'risks.technology';

    /**
     * the column name for the owner field
     */
    const COL_OWNER = 'risks.owner';

    /**
     * the column name for the manager field
     */
    const COL_MANAGER = 'risks.manager';

    /**
     * the column name for the assessment field
     */
    const COL_ASSESSMENT = 'risks.assessment';

    /**
     * the column name for the notes field
     */
    const COL_NOTES = 'risks.notes';

    /**
     * the column name for the submission_date field
     */
    const COL_SUBMISSION_DATE = 'risks.submission_date';

    /**
     * the column name for the last_update field
     */
    const COL_LAST_UPDATE = 'risks.last_update';

    /**
     * the column name for the review_date field
     */
    const COL_REVIEW_DATE = 'risks.review_date';

    /**
     * the column name for the mitigation_id field
     */
    const COL_MITIGATION_ID = 'risks.mitigation_id';

    /**
     * the column name for the mgmt_review field
     */
    const COL_MGMT_REVIEW = 'risks.mgmt_review';

    /**
     * the column name for the project_id field
     */
    const COL_PROJECT_ID = 'risks.project_id';

    /**
     * the column name for the close_id field
     */
    const COL_CLOSE_ID = 'risks.close_id';

    /**
     * the column name for the submitted_by field
     */
    const COL_SUBMITTED_BY = 'risks.submitted_by';

    /**
     * the column name for the parent_id field
     */
    const COL_PARENT_ID = 'risks.parent_id';

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
        self::TYPE_PHPNAME       => array('Id', 'Status', 'Subject', 'ReferenceId', 'Regulation', 'ControlNumber', 'Location', 'Category', 'Team', 'Technology', 'Owner', 'Manager', 'Assessment', 'Notes', 'SubmissionDate', 'LastUpdate', 'ReviewDate', 'MitigationId', 'MgmtReview', 'ProjectId', 'CloseId', 'SubmittedBy', 'ParentId', ),
        self::TYPE_CAMELNAME     => array('id', 'status', 'subject', 'referenceId', 'regulation', 'controlNumber', 'location', 'category', 'team', 'technology', 'owner', 'manager', 'assessment', 'notes', 'submissionDate', 'lastUpdate', 'reviewDate', 'mitigationId', 'mgmtReview', 'projectId', 'closeId', 'submittedBy', 'parentId', ),
        self::TYPE_COLNAME       => array(RisksTableMap::COL_ID, RisksTableMap::COL_STATUS, RisksTableMap::COL_SUBJECT, RisksTableMap::COL_REFERENCE_ID, RisksTableMap::COL_REGULATION, RisksTableMap::COL_CONTROL_NUMBER, RisksTableMap::COL_LOCATION, RisksTableMap::COL_CATEGORY, RisksTableMap::COL_TEAM, RisksTableMap::COL_TECHNOLOGY, RisksTableMap::COL_OWNER, RisksTableMap::COL_MANAGER, RisksTableMap::COL_ASSESSMENT, RisksTableMap::COL_NOTES, RisksTableMap::COL_SUBMISSION_DATE, RisksTableMap::COL_LAST_UPDATE, RisksTableMap::COL_REVIEW_DATE, RisksTableMap::COL_MITIGATION_ID, RisksTableMap::COL_MGMT_REVIEW, RisksTableMap::COL_PROJECT_ID, RisksTableMap::COL_CLOSE_ID, RisksTableMap::COL_SUBMITTED_BY, RisksTableMap::COL_PARENT_ID, ),
        self::TYPE_FIELDNAME     => array('id', 'status', 'subject', 'reference_id', 'regulation', 'control_number', 'location', 'category', 'team', 'technology', 'owner', 'manager', 'assessment', 'notes', 'submission_date', 'last_update', 'review_date', 'mitigation_id', 'mgmt_review', 'project_id', 'close_id', 'submitted_by', 'parent_id', ),
        self::TYPE_NUM           => array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, )
    );

    /**
     * holds an array of keys for quick access to the fieldnames array
     *
     * first dimension keys are the type constants
     * e.g. self::$fieldKeys[self::TYPE_PHPNAME]['Id'] = 0
     */
    protected static $fieldKeys = array (
        self::TYPE_PHPNAME       => array('Id' => 0, 'Status' => 1, 'Subject' => 2, 'ReferenceId' => 3, 'Regulation' => 4, 'ControlNumber' => 5, 'Location' => 6, 'Category' => 7, 'Team' => 8, 'Technology' => 9, 'Owner' => 10, 'Manager' => 11, 'Assessment' => 12, 'Notes' => 13, 'SubmissionDate' => 14, 'LastUpdate' => 15, 'ReviewDate' => 16, 'MitigationId' => 17, 'MgmtReview' => 18, 'ProjectId' => 19, 'CloseId' => 20, 'SubmittedBy' => 21, 'ParentId' => 22, ),
        self::TYPE_CAMELNAME     => array('id' => 0, 'status' => 1, 'subject' => 2, 'referenceId' => 3, 'regulation' => 4, 'controlNumber' => 5, 'location' => 6, 'category' => 7, 'team' => 8, 'technology' => 9, 'owner' => 10, 'manager' => 11, 'assessment' => 12, 'notes' => 13, 'submissionDate' => 14, 'lastUpdate' => 15, 'reviewDate' => 16, 'mitigationId' => 17, 'mgmtReview' => 18, 'projectId' => 19, 'closeId' => 20, 'submittedBy' => 21, 'parentId' => 22, ),
        self::TYPE_COLNAME       => array(RisksTableMap::COL_ID => 0, RisksTableMap::COL_STATUS => 1, RisksTableMap::COL_SUBJECT => 2, RisksTableMap::COL_REFERENCE_ID => 3, RisksTableMap::COL_REGULATION => 4, RisksTableMap::COL_CONTROL_NUMBER => 5, RisksTableMap::COL_LOCATION => 6, RisksTableMap::COL_CATEGORY => 7, RisksTableMap::COL_TEAM => 8, RisksTableMap::COL_TECHNOLOGY => 9, RisksTableMap::COL_OWNER => 10, RisksTableMap::COL_MANAGER => 11, RisksTableMap::COL_ASSESSMENT => 12, RisksTableMap::COL_NOTES => 13, RisksTableMap::COL_SUBMISSION_DATE => 14, RisksTableMap::COL_LAST_UPDATE => 15, RisksTableMap::COL_REVIEW_DATE => 16, RisksTableMap::COL_MITIGATION_ID => 17, RisksTableMap::COL_MGMT_REVIEW => 18, RisksTableMap::COL_PROJECT_ID => 19, RisksTableMap::COL_CLOSE_ID => 20, RisksTableMap::COL_SUBMITTED_BY => 21, RisksTableMap::COL_PARENT_ID => 22, ),
        self::TYPE_FIELDNAME     => array('id' => 0, 'status' => 1, 'subject' => 2, 'reference_id' => 3, 'regulation' => 4, 'control_number' => 5, 'location' => 6, 'category' => 7, 'team' => 8, 'technology' => 9, 'owner' => 10, 'manager' => 11, 'assessment' => 12, 'notes' => 13, 'submission_date' => 14, 'last_update' => 15, 'review_date' => 16, 'mitigation_id' => 17, 'mgmt_review' => 18, 'project_id' => 19, 'close_id' => 20, 'submitted_by' => 21, 'parent_id' => 22, ),
        self::TYPE_NUM           => array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, )
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
        $this->setName('risks');
        $this->setPhpName('Risks');
        $this->setIdentifierQuoting(false);
        $this->setClassName('\\Risks');
        $this->setPackage('');
        $this->setUseIdGenerator(true);
        // columns
        $this->addPrimaryKey('id', 'Id', 'INTEGER', true, null, null);
        $this->addColumn('status', 'Status', 'VARCHAR', true, 20, null);
        $this->addColumn('subject', 'Subject', 'VARCHAR', true, 100, null);
        $this->addColumn('reference_id', 'ReferenceId', 'VARCHAR', true, 20, '');
        $this->addColumn('regulation', 'Regulation', 'INTEGER', false, null, null);
        $this->addColumn('control_number', 'ControlNumber', 'VARCHAR', false, 20, null);
        $this->addColumn('location', 'Location', 'INTEGER', true, null, null);
        $this->addColumn('category', 'Category', 'INTEGER', true, null, null);
        $this->addColumn('team', 'Team', 'INTEGER', true, null, null);
        $this->addColumn('technology', 'Technology', 'INTEGER', true, null, null);
        $this->addColumn('owner', 'Owner', 'INTEGER', true, null, null);
        $this->addColumn('manager', 'Manager', 'INTEGER', true, null, null);
        $this->addColumn('assessment', 'Assessment', 'CLOB', true, null, null);
        $this->addColumn('notes', 'Notes', 'CLOB', true, null, null);
        $this->addColumn('submission_date', 'SubmissionDate', 'TIMESTAMP', true, null, 'CURRENT_TIMESTAMP');
        $this->addColumn('last_update', 'LastUpdate', 'TIMESTAMP', true, null, '0000-00-00 00:00:00');
        $this->addColumn('review_date', 'ReviewDate', 'TIMESTAMP', true, null, '0000-00-00 00:00:00');
        $this->addColumn('mitigation_id', 'MitigationId', 'INTEGER', true, null, null);
        $this->addColumn('mgmt_review', 'MgmtReview', 'INTEGER', true, null, null);
        $this->addColumn('project_id', 'ProjectId', 'INTEGER', true, null, 0);
        $this->addColumn('close_id', 'CloseId', 'INTEGER', true, null, null);
        $this->addColumn('submitted_by', 'SubmittedBy', 'INTEGER', true, null, 1);
        $this->addColumn('parent_id', 'ParentId', 'INTEGER', false, null, null);
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
        return $withPrefix ? RisksTableMap::CLASS_DEFAULT : RisksTableMap::OM_CLASS;
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
     * @return array           (Risks object, last column rank)
     */
    public static function populateObject($row, $offset = 0, $indexType = TableMap::TYPE_NUM)
    {
        $key = RisksTableMap::getPrimaryKeyHashFromRow($row, $offset, $indexType);
        if (null !== ($obj = RisksTableMap::getInstanceFromPool($key))) {
            // We no longer rehydrate the object, since this can cause data loss.
            // See http://www.propelorm.org/ticket/509
            // $obj->hydrate($row, $offset, true); // rehydrate
            $col = $offset + RisksTableMap::NUM_HYDRATE_COLUMNS;
        } else {
            $cls = RisksTableMap::OM_CLASS;
            /** @var Risks $obj */
            $obj = new $cls();
            $col = $obj->hydrate($row, $offset, false, $indexType);
            RisksTableMap::addInstanceToPool($obj, $key);
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
            $key = RisksTableMap::getPrimaryKeyHashFromRow($row, 0, $dataFetcher->getIndexType());
            if (null !== ($obj = RisksTableMap::getInstanceFromPool($key))) {
                // We no longer rehydrate the object, since this can cause data loss.
                // See http://www.propelorm.org/ticket/509
                // $obj->hydrate($row, 0, true); // rehydrate
                $results[] = $obj;
            } else {
                /** @var Risks $obj */
                $obj = new $cls();
                $obj->hydrate($row);
                $results[] = $obj;
                RisksTableMap::addInstanceToPool($obj, $key);
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
            $criteria->addSelectColumn(RisksTableMap::COL_ID);
            $criteria->addSelectColumn(RisksTableMap::COL_STATUS);
            $criteria->addSelectColumn(RisksTableMap::COL_SUBJECT);
            $criteria->addSelectColumn(RisksTableMap::COL_REFERENCE_ID);
            $criteria->addSelectColumn(RisksTableMap::COL_REGULATION);
            $criteria->addSelectColumn(RisksTableMap::COL_CONTROL_NUMBER);
            $criteria->addSelectColumn(RisksTableMap::COL_LOCATION);
            $criteria->addSelectColumn(RisksTableMap::COL_CATEGORY);
            $criteria->addSelectColumn(RisksTableMap::COL_TEAM);
            $criteria->addSelectColumn(RisksTableMap::COL_TECHNOLOGY);
            $criteria->addSelectColumn(RisksTableMap::COL_OWNER);
            $criteria->addSelectColumn(RisksTableMap::COL_MANAGER);
            $criteria->addSelectColumn(RisksTableMap::COL_ASSESSMENT);
            $criteria->addSelectColumn(RisksTableMap::COL_NOTES);
            $criteria->addSelectColumn(RisksTableMap::COL_SUBMISSION_DATE);
            $criteria->addSelectColumn(RisksTableMap::COL_LAST_UPDATE);
            $criteria->addSelectColumn(RisksTableMap::COL_REVIEW_DATE);
            $criteria->addSelectColumn(RisksTableMap::COL_MITIGATION_ID);
            $criteria->addSelectColumn(RisksTableMap::COL_MGMT_REVIEW);
            $criteria->addSelectColumn(RisksTableMap::COL_PROJECT_ID);
            $criteria->addSelectColumn(RisksTableMap::COL_CLOSE_ID);
            $criteria->addSelectColumn(RisksTableMap::COL_SUBMITTED_BY);
            $criteria->addSelectColumn(RisksTableMap::COL_PARENT_ID);
        } else {
            $criteria->addSelectColumn($alias . '.id');
            $criteria->addSelectColumn($alias . '.status');
            $criteria->addSelectColumn($alias . '.subject');
            $criteria->addSelectColumn($alias . '.reference_id');
            $criteria->addSelectColumn($alias . '.regulation');
            $criteria->addSelectColumn($alias . '.control_number');
            $criteria->addSelectColumn($alias . '.location');
            $criteria->addSelectColumn($alias . '.category');
            $criteria->addSelectColumn($alias . '.team');
            $criteria->addSelectColumn($alias . '.technology');
            $criteria->addSelectColumn($alias . '.owner');
            $criteria->addSelectColumn($alias . '.manager');
            $criteria->addSelectColumn($alias . '.assessment');
            $criteria->addSelectColumn($alias . '.notes');
            $criteria->addSelectColumn($alias . '.submission_date');
            $criteria->addSelectColumn($alias . '.last_update');
            $criteria->addSelectColumn($alias . '.review_date');
            $criteria->addSelectColumn($alias . '.mitigation_id');
            $criteria->addSelectColumn($alias . '.mgmt_review');
            $criteria->addSelectColumn($alias . '.project_id');
            $criteria->addSelectColumn($alias . '.close_id');
            $criteria->addSelectColumn($alias . '.submitted_by');
            $criteria->addSelectColumn($alias . '.parent_id');
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
        return Propel::getServiceContainer()->getDatabaseMap(RisksTableMap::DATABASE_NAME)->getTable(RisksTableMap::TABLE_NAME);
    }

    /**
     * Add a TableMap instance to the database for this tableMap class.
     */
    public static function buildTableMap()
    {
        $dbMap = Propel::getServiceContainer()->getDatabaseMap(RisksTableMap::DATABASE_NAME);
        if (!$dbMap->hasTable(RisksTableMap::TABLE_NAME)) {
            $dbMap->addTableObject(new RisksTableMap());
        }
    }

    /**
     * Performs a DELETE on the database, given a Risks or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or Risks object or primary key or array of primary keys
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
            $con = Propel::getServiceContainer()->getWriteConnection(RisksTableMap::DATABASE_NAME);
        }

        if ($values instanceof Criteria) {
            // rename for clarity
            $criteria = $values;
        } elseif ($values instanceof \Risks) { // it's a model object
            // create criteria based on pk values
            $criteria = $values->buildPkeyCriteria();
        } else { // it's a primary key, or an array of pks
            $criteria = new Criteria(RisksTableMap::DATABASE_NAME);
            $criteria->add(RisksTableMap::COL_ID, (array) $values, Criteria::IN);
        }

        $query = RisksQuery::create()->mergeWith($criteria);

        if ($values instanceof Criteria) {
            RisksTableMap::clearInstancePool();
        } elseif (!is_object($values)) { // it's a primary key, or an array of pks
            foreach ((array) $values as $singleval) {
                RisksTableMap::removeInstanceFromPool($singleval);
            }
        }

        return $query->delete($con);
    }

    /**
     * Deletes all rows from the risks table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public static function doDeleteAll(ConnectionInterface $con = null)
    {
        return RisksQuery::create()->doDeleteAll($con);
    }

    /**
     * Performs an INSERT on the database, given a Risks or Criteria object.
     *
     * @param mixed               $criteria Criteria or Risks object containing data that is used to create the INSERT statement.
     * @param ConnectionInterface $con the ConnectionInterface connection to use
     * @return mixed           The new primary key.
     * @throws PropelException Any exceptions caught during processing will be
     *                         rethrown wrapped into a PropelException.
     */
    public static function doInsert($criteria, ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(RisksTableMap::DATABASE_NAME);
        }

        if ($criteria instanceof Criteria) {
            $criteria = clone $criteria; // rename for clarity
        } else {
            $criteria = $criteria->buildCriteria(); // build Criteria from Risks object
        }

        if ($criteria->containsKey(RisksTableMap::COL_ID) && $criteria->keyContainsValue(RisksTableMap::COL_ID) ) {
            throw new PropelException('Cannot insert a value for auto-increment primary key ('.RisksTableMap::COL_ID.')');
        }


        // Set the correct dbName
        $query = RisksQuery::create()->mergeWith($criteria);

        // use transaction because $criteria could contain info
        // for more than one table (I guess, conceivably)
        return $con->transaction(function () use ($con, $query) {
            return $query->doInsert($con);
        });
    }

} // RisksTableMap
// This is the static code needed to register the TableMap for this table with the main Propel class.
//
RisksTableMap::buildTableMap();
