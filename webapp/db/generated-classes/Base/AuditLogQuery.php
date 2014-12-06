<?php

namespace Base;

use \AuditLog as ChildAuditLog;
use \AuditLogQuery as ChildAuditLogQuery;
use \Exception;
use Map\AuditLogTableMap;
use Propel\Runtime\Propel;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\Collection\ObjectCollection;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\LogicException;
use Propel\Runtime\Exception\PropelException;

/**
 * Base class that represents a query for the 'audit_log' table.
 *
 *
 *
 * @method     ChildAuditLogQuery orderByTimestamp($order = Criteria::ASC) Order by the timestamp column
 * @method     ChildAuditLogQuery orderByRiskId($order = Criteria::ASC) Order by the risk_id column
 * @method     ChildAuditLogQuery orderByUserId($order = Criteria::ASC) Order by the user_id column
 * @method     ChildAuditLogQuery orderByMessage($order = Criteria::ASC) Order by the message column
 *
 * @method     ChildAuditLogQuery groupByTimestamp() Group by the timestamp column
 * @method     ChildAuditLogQuery groupByRiskId() Group by the risk_id column
 * @method     ChildAuditLogQuery groupByUserId() Group by the user_id column
 * @method     ChildAuditLogQuery groupByMessage() Group by the message column
 *
 * @method     ChildAuditLogQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildAuditLogQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildAuditLogQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildAuditLog findOne(ConnectionInterface $con = null) Return the first ChildAuditLog matching the query
 * @method     ChildAuditLog findOneOrCreate(ConnectionInterface $con = null) Return the first ChildAuditLog matching the query, or a new ChildAuditLog object populated from the query conditions when no match is found
 *
 * @method     ChildAuditLog findOneByTimestamp(string $timestamp) Return the first ChildAuditLog filtered by the timestamp column
 * @method     ChildAuditLog findOneByRiskId(int $risk_id) Return the first ChildAuditLog filtered by the risk_id column
 * @method     ChildAuditLog findOneByUserId(int $user_id) Return the first ChildAuditLog filtered by the user_id column
 * @method     ChildAuditLog findOneByMessage(string $message) Return the first ChildAuditLog filtered by the message column
 *
 * @method     ChildAuditLog[]|ObjectCollection find(ConnectionInterface $con = null) Return ChildAuditLog objects based on current ModelCriteria
 * @method     ChildAuditLog[]|ObjectCollection findByTimestamp(string $timestamp) Return ChildAuditLog objects filtered by the timestamp column
 * @method     ChildAuditLog[]|ObjectCollection findByRiskId(int $risk_id) Return ChildAuditLog objects filtered by the risk_id column
 * @method     ChildAuditLog[]|ObjectCollection findByUserId(int $user_id) Return ChildAuditLog objects filtered by the user_id column
 * @method     ChildAuditLog[]|ObjectCollection findByMessage(string $message) Return ChildAuditLog objects filtered by the message column
 * @method     ChildAuditLog[]|\Propel\Runtime\Util\PropelModelPager paginate($page = 1, $maxPerPage = 10, ConnectionInterface $con = null) Issue a SELECT query based on the current ModelCriteria and uses a page and a maximum number of results per page to compute an offset and a limit
 *
 */
abstract class AuditLogQuery extends ModelCriteria
{

    /**
     * Initializes internal state of \Base\AuditLogQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'lessrisk', $modelName = '\\AuditLog', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildAuditLogQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildAuditLogQuery
     */
    public static function create($modelAlias = null, Criteria $criteria = null)
    {
        if ($criteria instanceof ChildAuditLogQuery) {
            return $criteria;
        }
        $query = new ChildAuditLogQuery();
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
     * @return ChildAuditLog|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, ConnectionInterface $con = null)
    {
        throw new LogicException('The AuditLog object has no primary key');
    }

    /**
     * Find objects by primary key
     * <code>
     * $objs = $c->findPks(array(array(12, 56), array(832, 123), array(123, 456)), $con);
     * </code>
     * @param     array $keys Primary keys to use for the query
     * @param     ConnectionInterface $con an optional connection object
     *
     * @return ObjectCollection|array|mixed the list of results, formatted by the current formatter
     */
    public function findPks($keys, ConnectionInterface $con = null)
    {
        throw new LogicException('The AuditLog object has no primary key');
    }

    /**
     * Filter the query by primary key
     *
     * @param     mixed $key Primary key to use for the query
     *
     * @return $this|ChildAuditLogQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {
        throw new LogicException('The AuditLog object has no primary key');
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return $this|ChildAuditLogQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {
        throw new LogicException('The AuditLog object has no primary key');
    }

    /**
     * Filter the query on the timestamp column
     *
     * Example usage:
     * <code>
     * $query->filterByTimestamp('2011-03-14'); // WHERE timestamp = '2011-03-14'
     * $query->filterByTimestamp('now'); // WHERE timestamp = '2011-03-14'
     * $query->filterByTimestamp(array('max' => 'yesterday')); // WHERE timestamp > '2011-03-13'
     * </code>
     *
     * @param     mixed $timestamp The value to use as filter.
     *              Values can be integers (unix timestamps), DateTime objects, or strings.
     *              Empty strings are treated as NULL.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildAuditLogQuery The current query, for fluid interface
     */
    public function filterByTimestamp($timestamp = null, $comparison = null)
    {
        if (is_array($timestamp)) {
            $useMinMax = false;
            if (isset($timestamp['min'])) {
                $this->addUsingAlias(AuditLogTableMap::COL_TIMESTAMP, $timestamp['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($timestamp['max'])) {
                $this->addUsingAlias(AuditLogTableMap::COL_TIMESTAMP, $timestamp['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(AuditLogTableMap::COL_TIMESTAMP, $timestamp, $comparison);
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
     * @return $this|ChildAuditLogQuery The current query, for fluid interface
     */
    public function filterByRiskId($riskId = null, $comparison = null)
    {
        if (is_array($riskId)) {
            $useMinMax = false;
            if (isset($riskId['min'])) {
                $this->addUsingAlias(AuditLogTableMap::COL_RISK_ID, $riskId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($riskId['max'])) {
                $this->addUsingAlias(AuditLogTableMap::COL_RISK_ID, $riskId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(AuditLogTableMap::COL_RISK_ID, $riskId, $comparison);
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
     * @return $this|ChildAuditLogQuery The current query, for fluid interface
     */
    public function filterByUserId($userId = null, $comparison = null)
    {
        if (is_array($userId)) {
            $useMinMax = false;
            if (isset($userId['min'])) {
                $this->addUsingAlias(AuditLogTableMap::COL_USER_ID, $userId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($userId['max'])) {
                $this->addUsingAlias(AuditLogTableMap::COL_USER_ID, $userId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(AuditLogTableMap::COL_USER_ID, $userId, $comparison);
    }

    /**
     * Filter the query on the message column
     *
     * Example usage:
     * <code>
     * $query->filterByMessage('fooValue');   // WHERE message = 'fooValue'
     * $query->filterByMessage('%fooValue%'); // WHERE message LIKE '%fooValue%'
     * </code>
     *
     * @param     string $message The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildAuditLogQuery The current query, for fluid interface
     */
    public function filterByMessage($message = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($message)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $message)) {
                $message = str_replace('*', '%', $message);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(AuditLogTableMap::COL_MESSAGE, $message, $comparison);
    }

    /**
     * Exclude object from result
     *
     * @param   ChildAuditLog $auditLog Object to remove from the list of results
     *
     * @return $this|ChildAuditLogQuery The current query, for fluid interface
     */
    public function prune($auditLog = null)
    {
        if ($auditLog) {
            throw new LogicException('AuditLog object has no primary key');

        }

        return $this;
    }

    /**
     * Deletes all rows from the audit_log table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(AuditLogTableMap::DATABASE_NAME);
        }

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con) {
            $affectedRows = 0; // initialize var to track total num of affected rows
            $affectedRows += parent::doDeleteAll($con);
            // Because this db requires some delete cascade/set null emulation, we have to
            // clear the cached instance *after* the emulation has happened (since
            // instances get re-added by the select statement contained therein).
            AuditLogTableMap::clearInstancePool();
            AuditLogTableMap::clearRelatedInstancePool();

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
            $con = Propel::getServiceContainer()->getWriteConnection(AuditLogTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(AuditLogTableMap::DATABASE_NAME);

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con, $criteria) {
            $affectedRows = 0; // initialize var to track total num of affected rows

            AuditLogTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            AuditLogTableMap::clearRelatedInstancePool();

            return $affectedRows;
        });
    }

} // AuditLogQuery
