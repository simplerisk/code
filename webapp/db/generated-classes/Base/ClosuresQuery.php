<?php

namespace Base;

use \Closures as ChildClosures;
use \ClosuresQuery as ChildClosuresQuery;
use \Exception;
use \PDO;
use Map\ClosuresTableMap;
use Propel\Runtime\Propel;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\Collection\ObjectCollection;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;

/**
 * Base class that represents a query for the 'closures' table.
 *
 *
 *
 * @method     ChildClosuresQuery orderById($order = Criteria::ASC) Order by the id column
 * @method     ChildClosuresQuery orderByRiskId($order = Criteria::ASC) Order by the risk_id column
 * @method     ChildClosuresQuery orderByUserId($order = Criteria::ASC) Order by the user_id column
 * @method     ChildClosuresQuery orderByClosureDate($order = Criteria::ASC) Order by the closure_date column
 * @method     ChildClosuresQuery orderByCloseReason($order = Criteria::ASC) Order by the close_reason column
 * @method     ChildClosuresQuery orderByNote($order = Criteria::ASC) Order by the note column
 *
 * @method     ChildClosuresQuery groupById() Group by the id column
 * @method     ChildClosuresQuery groupByRiskId() Group by the risk_id column
 * @method     ChildClosuresQuery groupByUserId() Group by the user_id column
 * @method     ChildClosuresQuery groupByClosureDate() Group by the closure_date column
 * @method     ChildClosuresQuery groupByCloseReason() Group by the close_reason column
 * @method     ChildClosuresQuery groupByNote() Group by the note column
 *
 * @method     ChildClosuresQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildClosuresQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildClosuresQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildClosures findOne(ConnectionInterface $con = null) Return the first ChildClosures matching the query
 * @method     ChildClosures findOneOrCreate(ConnectionInterface $con = null) Return the first ChildClosures matching the query, or a new ChildClosures object populated from the query conditions when no match is found
 *
 * @method     ChildClosures findOneById(int $id) Return the first ChildClosures filtered by the id column
 * @method     ChildClosures findOneByRiskId(int $risk_id) Return the first ChildClosures filtered by the risk_id column
 * @method     ChildClosures findOneByUserId(int $user_id) Return the first ChildClosures filtered by the user_id column
 * @method     ChildClosures findOneByClosureDate(string $closure_date) Return the first ChildClosures filtered by the closure_date column
 * @method     ChildClosures findOneByCloseReason(int $close_reason) Return the first ChildClosures filtered by the close_reason column
 * @method     ChildClosures findOneByNote(string $note) Return the first ChildClosures filtered by the note column
 *
 * @method     ChildClosures[]|ObjectCollection find(ConnectionInterface $con = null) Return ChildClosures objects based on current ModelCriteria
 * @method     ChildClosures[]|ObjectCollection findById(int $id) Return ChildClosures objects filtered by the id column
 * @method     ChildClosures[]|ObjectCollection findByRiskId(int $risk_id) Return ChildClosures objects filtered by the risk_id column
 * @method     ChildClosures[]|ObjectCollection findByUserId(int $user_id) Return ChildClosures objects filtered by the user_id column
 * @method     ChildClosures[]|ObjectCollection findByClosureDate(string $closure_date) Return ChildClosures objects filtered by the closure_date column
 * @method     ChildClosures[]|ObjectCollection findByCloseReason(int $close_reason) Return ChildClosures objects filtered by the close_reason column
 * @method     ChildClosures[]|ObjectCollection findByNote(string $note) Return ChildClosures objects filtered by the note column
 * @method     ChildClosures[]|\Propel\Runtime\Util\PropelModelPager paginate($page = 1, $maxPerPage = 10, ConnectionInterface $con = null) Issue a SELECT query based on the current ModelCriteria and uses a page and a maximum number of results per page to compute an offset and a limit
 *
 */
abstract class ClosuresQuery extends ModelCriteria
{

    /**
     * Initializes internal state of \Base\ClosuresQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'lessrisk', $modelName = '\\Closures', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildClosuresQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildClosuresQuery
     */
    public static function create($modelAlias = null, Criteria $criteria = null)
    {
        if ($criteria instanceof ChildClosuresQuery) {
            return $criteria;
        }
        $query = new ChildClosuresQuery();
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
     * @return ChildClosures|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, ConnectionInterface $con = null)
    {
        if ($key === null) {
            return null;
        }
        if ((null !== ($obj = ClosuresTableMap::getInstanceFromPool((string) $key))) && !$this->formatter) {
            // the object is already in the instance pool
            return $obj;
        }
        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(ClosuresTableMap::DATABASE_NAME);
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
     * @return ChildClosures A model object, or null if the key is not found
     */
    protected function findPkSimple($key, ConnectionInterface $con)
    {
        $sql = 'SELECT id, risk_id, user_id, closure_date, close_reason, note FROM closures WHERE id = :p0';
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
            /** @var ChildClosures $obj */
            $obj = new ChildClosures();
            $obj->hydrate($row);
            ClosuresTableMap::addInstanceToPool($obj, (string) $key);
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
     * @return ChildClosures|array|mixed the result, formatted by the current formatter
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
     * @return $this|ChildClosuresQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {

        return $this->addUsingAlias(ClosuresTableMap::COL_ID, $key, Criteria::EQUAL);
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return $this|ChildClosuresQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {

        return $this->addUsingAlias(ClosuresTableMap::COL_ID, $keys, Criteria::IN);
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
     * @return $this|ChildClosuresQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id)) {
            $useMinMax = false;
            if (isset($id['min'])) {
                $this->addUsingAlias(ClosuresTableMap::COL_ID, $id['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($id['max'])) {
                $this->addUsingAlias(ClosuresTableMap::COL_ID, $id['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ClosuresTableMap::COL_ID, $id, $comparison);
    }

    /**
     * Filter the query on the risk_id column
     *
     * Example usage:
     * <code>
     * $query->filterByRiskId(1234); // WHERE risk_id = 1234
     * $query->filterByRiskId(array(12, 34)); // WHERE risk_id IN (12, 34)
     * $query->filterByRiskId(array('min' => 12)); // WHERE risk_id > 12
     * </code>
     *
     * @param     mixed $riskId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildClosuresQuery The current query, for fluid interface
     */
    public function filterByRiskId($riskId = null, $comparison = null)
    {
        if (is_array($riskId)) {
            $useMinMax = false;
            if (isset($riskId['min'])) {
                $this->addUsingAlias(ClosuresTableMap::COL_RISK_ID, $riskId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($riskId['max'])) {
                $this->addUsingAlias(ClosuresTableMap::COL_RISK_ID, $riskId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ClosuresTableMap::COL_RISK_ID, $riskId, $comparison);
    }

    /**
     * Filter the query on the user_id column
     *
     * Example usage:
     * <code>
     * $query->filterByUserId(1234); // WHERE user_id = 1234
     * $query->filterByUserId(array(12, 34)); // WHERE user_id IN (12, 34)
     * $query->filterByUserId(array('min' => 12)); // WHERE user_id > 12
     * </code>
     *
     * @param     mixed $userId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildClosuresQuery The current query, for fluid interface
     */
    public function filterByUserId($userId = null, $comparison = null)
    {
        if (is_array($userId)) {
            $useMinMax = false;
            if (isset($userId['min'])) {
                $this->addUsingAlias(ClosuresTableMap::COL_USER_ID, $userId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($userId['max'])) {
                $this->addUsingAlias(ClosuresTableMap::COL_USER_ID, $userId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ClosuresTableMap::COL_USER_ID, $userId, $comparison);
    }

    /**
     * Filter the query on the closure_date column
     *
     * Example usage:
     * <code>
     * $query->filterByClosureDate('2011-03-14'); // WHERE closure_date = '2011-03-14'
     * $query->filterByClosureDate('now'); // WHERE closure_date = '2011-03-14'
     * $query->filterByClosureDate(array('max' => 'yesterday')); // WHERE closure_date > '2011-03-13'
     * </code>
     *
     * @param     mixed $closureDate The value to use as filter.
     *              Values can be integers (unix timestamps), DateTime objects, or strings.
     *              Empty strings are treated as NULL.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildClosuresQuery The current query, for fluid interface
     */
    public function filterByClosureDate($closureDate = null, $comparison = null)
    {
        if (is_array($closureDate)) {
            $useMinMax = false;
            if (isset($closureDate['min'])) {
                $this->addUsingAlias(ClosuresTableMap::COL_CLOSURE_DATE, $closureDate['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($closureDate['max'])) {
                $this->addUsingAlias(ClosuresTableMap::COL_CLOSURE_DATE, $closureDate['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ClosuresTableMap::COL_CLOSURE_DATE, $closureDate, $comparison);
    }

    /**
     * Filter the query on the close_reason column
     *
     * Example usage:
     * <code>
     * $query->filterByCloseReason(1234); // WHERE close_reason = 1234
     * $query->filterByCloseReason(array(12, 34)); // WHERE close_reason IN (12, 34)
     * $query->filterByCloseReason(array('min' => 12)); // WHERE close_reason > 12
     * </code>
     *
     * @param     mixed $closeReason The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildClosuresQuery The current query, for fluid interface
     */
    public function filterByCloseReason($closeReason = null, $comparison = null)
    {
        if (is_array($closeReason)) {
            $useMinMax = false;
            if (isset($closeReason['min'])) {
                $this->addUsingAlias(ClosuresTableMap::COL_CLOSE_REASON, $closeReason['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($closeReason['max'])) {
                $this->addUsingAlias(ClosuresTableMap::COL_CLOSE_REASON, $closeReason['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ClosuresTableMap::COL_CLOSE_REASON, $closeReason, $comparison);
    }

    /**
     * Filter the query on the note column
     *
     * Example usage:
     * <code>
     * $query->filterByNote('fooValue');   // WHERE note = 'fooValue'
     * $query->filterByNote('%fooValue%'); // WHERE note LIKE '%fooValue%'
     * </code>
     *
     * @param     string $note The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildClosuresQuery The current query, for fluid interface
     */
    public function filterByNote($note = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($note)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $note)) {
                $note = str_replace('*', '%', $note);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(ClosuresTableMap::COL_NOTE, $note, $comparison);
    }

    /**
     * Exclude object from result
     *
     * @param   ChildClosures $closures Object to remove from the list of results
     *
     * @return $this|ChildClosuresQuery The current query, for fluid interface
     */
    public function prune($closures = null)
    {
        if ($closures) {
            $this->addUsingAlias(ClosuresTableMap::COL_ID, $closures->getId(), Criteria::NOT_EQUAL);
        }

        return $this;
    }

    /**
     * Deletes all rows from the closures table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(ClosuresTableMap::DATABASE_NAME);
        }

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con) {
            $affectedRows = 0; // initialize var to track total num of affected rows
            $affectedRows += parent::doDeleteAll($con);
            // Because this db requires some delete cascade/set null emulation, we have to
            // clear the cached instance *after* the emulation has happened (since
            // instances get re-added by the select statement contained therein).
            ClosuresTableMap::clearInstancePool();
            ClosuresTableMap::clearRelatedInstancePool();

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
            $con = Propel::getServiceContainer()->getWriteConnection(ClosuresTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(ClosuresTableMap::DATABASE_NAME);

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con, $criteria) {
            $affectedRows = 0; // initialize var to track total num of affected rows

            ClosuresTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            ClosuresTableMap::clearRelatedInstancePool();

            return $affectedRows;
        });
    }

} // ClosuresQuery
