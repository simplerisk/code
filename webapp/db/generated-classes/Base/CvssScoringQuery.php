<?php

namespace Base;

use \CvssScoring as ChildCvssScoring;
use \CvssScoringQuery as ChildCvssScoringQuery;
use \Exception;
use \PDO;
use Map\CvssScoringTableMap;
use Propel\Runtime\Propel;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\Collection\ObjectCollection;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;

/**
 * Base class that represents a query for the 'CVSS_scoring' table.
 *
 *
 *
 * @method     ChildCvssScoringQuery orderById($order = Criteria::ASC) Order by the id column
 * @method     ChildCvssScoringQuery orderByMetricName($order = Criteria::ASC) Order by the metric_name column
 * @method     ChildCvssScoringQuery orderByAbrvMetricName($order = Criteria::ASC) Order by the abrv_metric_name column
 * @method     ChildCvssScoringQuery orderByMetricValue($order = Criteria::ASC) Order by the metric_value column
 * @method     ChildCvssScoringQuery orderByAbrvMetricValue($order = Criteria::ASC) Order by the abrv_metric_value column
 * @method     ChildCvssScoringQuery orderByNumericValue($order = Criteria::ASC) Order by the numeric_value column
 *
 * @method     ChildCvssScoringQuery groupById() Group by the id column
 * @method     ChildCvssScoringQuery groupByMetricName() Group by the metric_name column
 * @method     ChildCvssScoringQuery groupByAbrvMetricName() Group by the abrv_metric_name column
 * @method     ChildCvssScoringQuery groupByMetricValue() Group by the metric_value column
 * @method     ChildCvssScoringQuery groupByAbrvMetricValue() Group by the abrv_metric_value column
 * @method     ChildCvssScoringQuery groupByNumericValue() Group by the numeric_value column
 *
 * @method     ChildCvssScoringQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildCvssScoringQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildCvssScoringQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildCvssScoring findOne(ConnectionInterface $con = null) Return the first ChildCvssScoring matching the query
 * @method     ChildCvssScoring findOneOrCreate(ConnectionInterface $con = null) Return the first ChildCvssScoring matching the query, or a new ChildCvssScoring object populated from the query conditions when no match is found
 *
 * @method     ChildCvssScoring findOneById(int $id) Return the first ChildCvssScoring filtered by the id column
 * @method     ChildCvssScoring findOneByMetricName(string $metric_name) Return the first ChildCvssScoring filtered by the metric_name column
 * @method     ChildCvssScoring findOneByAbrvMetricName(string $abrv_metric_name) Return the first ChildCvssScoring filtered by the abrv_metric_name column
 * @method     ChildCvssScoring findOneByMetricValue(string $metric_value) Return the first ChildCvssScoring filtered by the metric_value column
 * @method     ChildCvssScoring findOneByAbrvMetricValue(string $abrv_metric_value) Return the first ChildCvssScoring filtered by the abrv_metric_value column
 * @method     ChildCvssScoring findOneByNumericValue(double $numeric_value) Return the first ChildCvssScoring filtered by the numeric_value column
 *
 * @method     ChildCvssScoring[]|ObjectCollection find(ConnectionInterface $con = null) Return ChildCvssScoring objects based on current ModelCriteria
 * @method     ChildCvssScoring[]|ObjectCollection findById(int $id) Return ChildCvssScoring objects filtered by the id column
 * @method     ChildCvssScoring[]|ObjectCollection findByMetricName(string $metric_name) Return ChildCvssScoring objects filtered by the metric_name column
 * @method     ChildCvssScoring[]|ObjectCollection findByAbrvMetricName(string $abrv_metric_name) Return ChildCvssScoring objects filtered by the abrv_metric_name column
 * @method     ChildCvssScoring[]|ObjectCollection findByMetricValue(string $metric_value) Return ChildCvssScoring objects filtered by the metric_value column
 * @method     ChildCvssScoring[]|ObjectCollection findByAbrvMetricValue(string $abrv_metric_value) Return ChildCvssScoring objects filtered by the abrv_metric_value column
 * @method     ChildCvssScoring[]|ObjectCollection findByNumericValue(double $numeric_value) Return ChildCvssScoring objects filtered by the numeric_value column
 * @method     ChildCvssScoring[]|\Propel\Runtime\Util\PropelModelPager paginate($page = 1, $maxPerPage = 10, ConnectionInterface $con = null) Issue a SELECT query based on the current ModelCriteria and uses a page and a maximum number of results per page to compute an offset and a limit
 *
 */
abstract class CvssScoringQuery extends ModelCriteria
{

    /**
     * Initializes internal state of \Base\CvssScoringQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'lessrisk', $modelName = '\\CvssScoring', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildCvssScoringQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildCvssScoringQuery
     */
    public static function create($modelAlias = null, Criteria $criteria = null)
    {
        if ($criteria instanceof ChildCvssScoringQuery) {
            return $criteria;
        }
        $query = new ChildCvssScoringQuery();
        if (null !== $modelAlias) {
            $query->setModelAlias($modelAlias);
        }
        if ($criteria instanceof Criteria) {
            $query->mergeWith($criteria);
        }

        return $query;
    }

    /**
     * Find object by primary key.
     * Propel uses the instance pool to skip the database if the object exists.
     * Go fast if the query is untouched.
     *
     * <code>
     * $obj  = $c->findPk(12, $con);
     * </code>
     *
     * @param mixed $key Primary key to use for the query
     * @param ConnectionInterface $con an optional connection object
     *
     * @return ChildCvssScoring|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, ConnectionInterface $con = null)
    {
        if ($key === null) {
            return null;
        }
        if ((null !== ($obj = CvssScoringTableMap::getInstanceFromPool((string) $key))) && !$this->formatter) {
            // the object is already in the instance pool
            return $obj;
        }
        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(CvssScoringTableMap::DATABASE_NAME);
        }
        $this->basePreSelect($con);
        if ($this->formatter || $this->modelAlias || $this->with || $this->select
         || $this->selectColumns || $this->asColumns || $this->selectModifiers
         || $this->map || $this->having || $this->joins) {
            return $this->findPkComplex($key, $con);
        } else {
            return $this->findPkSimple($key, $con);
        }
    }

    /**
     * Find object by primary key using raw SQL to go fast.
     * Bypass doSelect() and the object formatter by using generated code.
     *
     * @param     mixed $key Primary key to use for the query
     * @param     ConnectionInterface $con A connection object
     *
     * @throws \Propel\Runtime\Exception\PropelException
     *
     * @return ChildCvssScoring A model object, or null if the key is not found
     */
    protected function findPkSimple($key, ConnectionInterface $con)
    {
        $sql = 'SELECT id, metric_name, abrv_metric_name, metric_value, abrv_metric_value, numeric_value FROM CVSS_scoring WHERE id = :p0';
        try {
            $stmt = $con->prepare($sql);
            $stmt->bindValue(':p0', $key, PDO::PARAM_INT);
            $stmt->execute();
        } catch (Exception $e) {
            Propel::log($e->getMessage(), Propel::LOG_ERR);
            throw new PropelException(sprintf('Unable to execute SELECT statement [%s]', $sql), 0, $e);
        }
        $obj = null;
        if ($row = $stmt->fetch(\PDO::FETCH_NUM)) {
            /** @var ChildCvssScoring $obj */
            $obj = new ChildCvssScoring();
            $obj->hydrate($row);
            CvssScoringTableMap::addInstanceToPool($obj, (string) $key);
        }
        $stmt->closeCursor();

        return $obj;
    }

    /**
     * Find object by primary key.
     *
     * @param     mixed $key Primary key to use for the query
     * @param     ConnectionInterface $con A connection object
     *
     * @return ChildCvssScoring|array|mixed the result, formatted by the current formatter
     */
    protected function findPkComplex($key, ConnectionInterface $con)
    {
        // As the query uses a PK condition, no limit(1) is necessary.
        $criteria = $this->isKeepQuery() ? clone $this : $this;
        $dataFetcher = $criteria
            ->filterByPrimaryKey($key)
            ->doSelect($con);

        return $criteria->getFormatter()->init($criteria)->formatOne($dataFetcher);
    }

    /**
     * Find objects by primary key
     * <code>
     * $objs = $c->findPks(array(12, 56, 832), $con);
     * </code>
     * @param     array $keys Primary keys to use for the query
     * @param     ConnectionInterface $con an optional connection object
     *
     * @return ObjectCollection|array|mixed the list of results, formatted by the current formatter
     */
    public function findPks($keys, ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getReadConnection($this->getDbName());
        }
        $this->basePreSelect($con);
        $criteria = $this->isKeepQuery() ? clone $this : $this;
        $dataFetcher = $criteria
            ->filterByPrimaryKeys($keys)
            ->doSelect($con);

        return $criteria->getFormatter()->init($criteria)->format($dataFetcher);
    }

    /**
     * Filter the query by primary key
     *
     * @param     mixed $key Primary key to use for the query
     *
     * @return $this|ChildCvssScoringQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {

        return $this->addUsingAlias(CvssScoringTableMap::COL_ID, $key, Criteria::EQUAL);
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return $this|ChildCvssScoringQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {

        return $this->addUsingAlias(CvssScoringTableMap::COL_ID, $keys, Criteria::IN);
    }

    /**
     * Filter the query on the id column
     *
     * Example usage:
     * <code>
     * $query->filterById(1234); // WHERE id = 1234
     * $query->filterById(array(12, 34)); // WHERE id IN (12, 34)
     * $query->filterById(array('min' => 12)); // WHERE id > 12
     * </code>
     *
     * @param     mixed $id The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildCvssScoringQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id)) {
            $useMinMax = false;
            if (isset($id['min'])) {
                $this->addUsingAlias(CvssScoringTableMap::COL_ID, $id['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($id['max'])) {
                $this->addUsingAlias(CvssScoringTableMap::COL_ID, $id['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CvssScoringTableMap::COL_ID, $id, $comparison);
    }

    /**
     * Filter the query on the metric_name column
     *
     * Example usage:
     * <code>
     * $query->filterByMetricName('fooValue');   // WHERE metric_name = 'fooValue'
     * $query->filterByMetricName('%fooValue%'); // WHERE metric_name LIKE '%fooValue%'
     * </code>
     *
     * @param     string $metricName The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildCvssScoringQuery The current query, for fluid interface
     */
    public function filterByMetricName($metricName = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($metricName)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $metricName)) {
                $metricName = str_replace('*', '%', $metricName);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(CvssScoringTableMap::COL_METRIC_NAME, $metricName, $comparison);
    }

    /**
     * Filter the query on the abrv_metric_name column
     *
     * Example usage:
     * <code>
     * $query->filterByAbrvMetricName('fooValue');   // WHERE abrv_metric_name = 'fooValue'
     * $query->filterByAbrvMetricName('%fooValue%'); // WHERE abrv_metric_name LIKE '%fooValue%'
     * </code>
     *
     * @param     string $abrvMetricName The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildCvssScoringQuery The current query, for fluid interface
     */
    public function filterByAbrvMetricName($abrvMetricName = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($abrvMetricName)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $abrvMetricName)) {
                $abrvMetricName = str_replace('*', '%', $abrvMetricName);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(CvssScoringTableMap::COL_ABRV_METRIC_NAME, $abrvMetricName, $comparison);
    }

    /**
     * Filter the query on the metric_value column
     *
     * Example usage:
     * <code>
     * $query->filterByMetricValue('fooValue');   // WHERE metric_value = 'fooValue'
     * $query->filterByMetricValue('%fooValue%'); // WHERE metric_value LIKE '%fooValue%'
     * </code>
     *
     * @param     string $metricValue The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildCvssScoringQuery The current query, for fluid interface
     */
    public function filterByMetricValue($metricValue = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($metricValue)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $metricValue)) {
                $metricValue = str_replace('*', '%', $metricValue);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(CvssScoringTableMap::COL_METRIC_VALUE, $metricValue, $comparison);
    }

    /**
     * Filter the query on the abrv_metric_value column
     *
     * Example usage:
     * <code>
     * $query->filterByAbrvMetricValue('fooValue');   // WHERE abrv_metric_value = 'fooValue'
     * $query->filterByAbrvMetricValue('%fooValue%'); // WHERE abrv_metric_value LIKE '%fooValue%'
     * </code>
     *
     * @param     string $abrvMetricValue The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildCvssScoringQuery The current query, for fluid interface
     */
    public function filterByAbrvMetricValue($abrvMetricValue = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($abrvMetricValue)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $abrvMetricValue)) {
                $abrvMetricValue = str_replace('*', '%', $abrvMetricValue);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(CvssScoringTableMap::COL_ABRV_METRIC_VALUE, $abrvMetricValue, $comparison);
    }

    /**
     * Filter the query on the numeric_value column
     *
     * Example usage:
     * <code>
     * $query->filterByNumericValue(1234); // WHERE numeric_value = 1234
     * $query->filterByNumericValue(array(12, 34)); // WHERE numeric_value IN (12, 34)
     * $query->filterByNumericValue(array('min' => 12)); // WHERE numeric_value > 12
     * </code>
     *
     * @param     mixed $numericValue The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildCvssScoringQuery The current query, for fluid interface
     */
    public function filterByNumericValue($numericValue = null, $comparison = null)
    {
        if (is_array($numericValue)) {
            $useMinMax = false;
            if (isset($numericValue['min'])) {
                $this->addUsingAlias(CvssScoringTableMap::COL_NUMERIC_VALUE, $numericValue['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($numericValue['max'])) {
                $this->addUsingAlias(CvssScoringTableMap::COL_NUMERIC_VALUE, $numericValue['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CvssScoringTableMap::COL_NUMERIC_VALUE, $numericValue, $comparison);
    }

    /**
     * Exclude object from result
     *
     * @param   ChildCvssScoring $cvssScoring Object to remove from the list of results
     *
     * @return $this|ChildCvssScoringQuery The current query, for fluid interface
     */
    public function prune($cvssScoring = null)
    {
        if ($cvssScoring) {
            $this->addUsingAlias(CvssScoringTableMap::COL_ID, $cvssScoring->getId(), Criteria::NOT_EQUAL);
        }

        return $this;
    }

    /**
     * Deletes all rows from the CVSS_scoring table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(CvssScoringTableMap::DATABASE_NAME);
        }

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con) {
            $affectedRows = 0; // initialize var to track total num of affected rows
            $affectedRows += parent::doDeleteAll($con);
            // Because this db requires some delete cascade/set null emulation, we have to
            // clear the cached instance *after* the emulation has happened (since
            // instances get re-added by the select statement contained therein).
            CvssScoringTableMap::clearInstancePool();
            CvssScoringTableMap::clearRelatedInstancePool();

            return $affectedRows;
        });
    }

    /**
     * Performs a DELETE on the database based on the current ModelCriteria
     *
     * @param ConnectionInterface $con the connection to use
     * @return int             The number of affected rows (if supported by underlying database driver).  This includes CASCADE-related rows
     *                         if supported by native driver or if emulated using Propel.
     * @throws PropelException Any exceptions caught during processing will be
     *                         rethrown wrapped into a PropelException.
     */
    public function delete(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(CvssScoringTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(CvssScoringTableMap::DATABASE_NAME);

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con, $criteria) {
            $affectedRows = 0; // initialize var to track total num of affected rows

            CvssScoringTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            CvssScoringTableMap::clearRelatedInstancePool();

            return $affectedRows;
        });
    }

} // CvssScoringQuery
