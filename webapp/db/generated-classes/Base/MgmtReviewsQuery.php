<?php

namespace Base;

use \MgmtReviews as ChildMgmtReviews;
use \MgmtReviewsQuery as ChildMgmtReviewsQuery;
use \Exception;
use \PDO;
use Map\MgmtReviewsTableMap;
use Propel\Runtime\Propel;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\Collection\ObjectCollection;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;

/**
 * Base class that represents a query for the 'mgmt_reviews' table.
 *
 *
 *
 * @method     ChildMgmtReviewsQuery orderById($order = Criteria::ASC) Order by the id column
 * @method     ChildMgmtReviewsQuery orderByRiskId($order = Criteria::ASC) Order by the risk_id column
 * @method     ChildMgmtReviewsQuery orderBySubmissionDate($order = Criteria::ASC) Order by the submission_date column
 * @method     ChildMgmtReviewsQuery orderByReview($order = Criteria::ASC) Order by the review column
 * @method     ChildMgmtReviewsQuery orderByReviewer($order = Criteria::ASC) Order by the reviewer column
 * @method     ChildMgmtReviewsQuery orderByNextStep($order = Criteria::ASC) Order by the next_step column
 * @method     ChildMgmtReviewsQuery orderByComments($order = Criteria::ASC) Order by the comments column
 * @method     ChildMgmtReviewsQuery orderByNextReview($order = Criteria::ASC) Order by the next_review column
 *
 * @method     ChildMgmtReviewsQuery groupById() Group by the id column
 * @method     ChildMgmtReviewsQuery groupByRiskId() Group by the risk_id column
 * @method     ChildMgmtReviewsQuery groupBySubmissionDate() Group by the submission_date column
 * @method     ChildMgmtReviewsQuery groupByReview() Group by the review column
 * @method     ChildMgmtReviewsQuery groupByReviewer() Group by the reviewer column
 * @method     ChildMgmtReviewsQuery groupByNextStep() Group by the next_step column
 * @method     ChildMgmtReviewsQuery groupByComments() Group by the comments column
 * @method     ChildMgmtReviewsQuery groupByNextReview() Group by the next_review column
 *
 * @method     ChildMgmtReviewsQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildMgmtReviewsQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildMgmtReviewsQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildMgmtReviews findOne(ConnectionInterface $con = null) Return the first ChildMgmtReviews matching the query
 * @method     ChildMgmtReviews findOneOrCreate(ConnectionInterface $con = null) Return the first ChildMgmtReviews matching the query, or a new ChildMgmtReviews object populated from the query conditions when no match is found
 *
 * @method     ChildMgmtReviews findOneById(int $id) Return the first ChildMgmtReviews filtered by the id column
 * @method     ChildMgmtReviews findOneByRiskId(int $risk_id) Return the first ChildMgmtReviews filtered by the risk_id column
 * @method     ChildMgmtReviews findOneBySubmissionDate(string $submission_date) Return the first ChildMgmtReviews filtered by the submission_date column
 * @method     ChildMgmtReviews findOneByReview(int $review) Return the first ChildMgmtReviews filtered by the review column
 * @method     ChildMgmtReviews findOneByReviewer(int $reviewer) Return the first ChildMgmtReviews filtered by the reviewer column
 * @method     ChildMgmtReviews findOneByNextStep(int $next_step) Return the first ChildMgmtReviews filtered by the next_step column
 * @method     ChildMgmtReviews findOneByComments(string $comments) Return the first ChildMgmtReviews filtered by the comments column
 * @method     ChildMgmtReviews findOneByNextReview(string $next_review) Return the first ChildMgmtReviews filtered by the next_review column
 *
 * @method     ChildMgmtReviews[]|ObjectCollection find(ConnectionInterface $con = null) Return ChildMgmtReviews objects based on current ModelCriteria
 * @method     ChildMgmtReviews[]|ObjectCollection findById(int $id) Return ChildMgmtReviews objects filtered by the id column
 * @method     ChildMgmtReviews[]|ObjectCollection findByRiskId(int $risk_id) Return ChildMgmtReviews objects filtered by the risk_id column
 * @method     ChildMgmtReviews[]|ObjectCollection findBySubmissionDate(string $submission_date) Return ChildMgmtReviews objects filtered by the submission_date column
 * @method     ChildMgmtReviews[]|ObjectCollection findByReview(int $review) Return ChildMgmtReviews objects filtered by the review column
 * @method     ChildMgmtReviews[]|ObjectCollection findByReviewer(int $reviewer) Return ChildMgmtReviews objects filtered by the reviewer column
 * @method     ChildMgmtReviews[]|ObjectCollection findByNextStep(int $next_step) Return ChildMgmtReviews objects filtered by the next_step column
 * @method     ChildMgmtReviews[]|ObjectCollection findByComments(string $comments) Return ChildMgmtReviews objects filtered by the comments column
 * @method     ChildMgmtReviews[]|ObjectCollection findByNextReview(string $next_review) Return ChildMgmtReviews objects filtered by the next_review column
 * @method     ChildMgmtReviews[]|\Propel\Runtime\Util\PropelModelPager paginate($page = 1, $maxPerPage = 10, ConnectionInterface $con = null) Issue a SELECT query based on the current ModelCriteria and uses a page and a maximum number of results per page to compute an offset and a limit
 *
 */
abstract class MgmtReviewsQuery extends ModelCriteria
{

    /**
     * Initializes internal state of \Base\MgmtReviewsQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'lessrisk', $modelName = '\\MgmtReviews', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildMgmtReviewsQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildMgmtReviewsQuery
     */
    public static function create($modelAlias = null, Criteria $criteria = null)
    {
        if ($criteria instanceof ChildMgmtReviewsQuery) {
            return $criteria;
        }
        $query = new ChildMgmtReviewsQuery();
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
     * @return ChildMgmtReviews|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, ConnectionInterface $con = null)
    {
        if ($key === null) {
            return null;
        }
        if ((null !== ($obj = MgmtReviewsTableMap::getInstanceFromPool((string) $key))) && !$this->formatter) {
            // the object is already in the instance pool
            return $obj;
        }
        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(MgmtReviewsTableMap::DATABASE_NAME);
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
     * @return ChildMgmtReviews A model object, or null if the key is not found
     */
    protected function findPkSimple($key, ConnectionInterface $con)
    {
        $sql = 'SELECT id, risk_id, submission_date, review, reviewer, next_step, comments, next_review FROM mgmt_reviews WHERE id = :p0';
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
            /** @var ChildMgmtReviews $obj */
            $obj = new ChildMgmtReviews();
            $obj->hydrate($row);
            MgmtReviewsTableMap::addInstanceToPool($obj, (string) $key);
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
     * @return ChildMgmtReviews|array|mixed the result, formatted by the current formatter
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
     * @return $this|ChildMgmtReviewsQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {

        return $this->addUsingAlias(MgmtReviewsTableMap::COL_ID, $key, Criteria::EQUAL);
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return $this|ChildMgmtReviewsQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {

        return $this->addUsingAlias(MgmtReviewsTableMap::COL_ID, $keys, Criteria::IN);
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
     * @return $this|ChildMgmtReviewsQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id)) {
            $useMinMax = false;
            if (isset($id['min'])) {
                $this->addUsingAlias(MgmtReviewsTableMap::COL_ID, $id['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($id['max'])) {
                $this->addUsingAlias(MgmtReviewsTableMap::COL_ID, $id['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(MgmtReviewsTableMap::COL_ID, $id, $comparison);
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
     * @return $this|ChildMgmtReviewsQuery The current query, for fluid interface
     */
    public function filterByRiskId($riskId = null, $comparison = null)
    {
        if (is_array($riskId)) {
            $useMinMax = false;
            if (isset($riskId['min'])) {
                $this->addUsingAlias(MgmtReviewsTableMap::COL_RISK_ID, $riskId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($riskId['max'])) {
                $this->addUsingAlias(MgmtReviewsTableMap::COL_RISK_ID, $riskId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(MgmtReviewsTableMap::COL_RISK_ID, $riskId, $comparison);
    }

    /**
     * Filter the query on the submission_date column
     *
     * Example usage:
     * <code>
     * $query->filterBySubmissionDate('2011-03-14'); // WHERE submission_date = '2011-03-14'
     * $query->filterBySubmissionDate('now'); // WHERE submission_date = '2011-03-14'
     * $query->filterBySubmissionDate(array('max' => 'yesterday')); // WHERE submission_date > '2011-03-13'
     * </code>
     *
     * @param     mixed $submissionDate The value to use as filter.
     *              Values can be integers (unix timestamps), DateTime objects, or strings.
     *              Empty strings are treated as NULL.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildMgmtReviewsQuery The current query, for fluid interface
     */
    public function filterBySubmissionDate($submissionDate = null, $comparison = null)
    {
        if (is_array($submissionDate)) {
            $useMinMax = false;
            if (isset($submissionDate['min'])) {
                $this->addUsingAlias(MgmtReviewsTableMap::COL_SUBMISSION_DATE, $submissionDate['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($submissionDate['max'])) {
                $this->addUsingAlias(MgmtReviewsTableMap::COL_SUBMISSION_DATE, $submissionDate['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(MgmtReviewsTableMap::COL_SUBMISSION_DATE, $submissionDate, $comparison);
    }

    /**
     * Filter the query on the review column
     *
     * Example usage:
     * <code>
     * $query->filterByReview(1234); // WHERE review = 1234
     * $query->filterByReview(array(12, 34)); // WHERE review IN (12, 34)
     * $query->filterByReview(array('min' => 12)); // WHERE review > 12
     * </code>
     *
     * @param     mixed $review The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildMgmtReviewsQuery The current query, for fluid interface
     */
    public function filterByReview($review = null, $comparison = null)
    {
        if (is_array($review)) {
            $useMinMax = false;
            if (isset($review['min'])) {
                $this->addUsingAlias(MgmtReviewsTableMap::COL_REVIEW, $review['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($review['max'])) {
                $this->addUsingAlias(MgmtReviewsTableMap::COL_REVIEW, $review['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(MgmtReviewsTableMap::COL_REVIEW, $review, $comparison);
    }

    /**
     * Filter the query on the reviewer column
     *
     * Example usage:
     * <code>
     * $query->filterByReviewer(1234); // WHERE reviewer = 1234
     * $query->filterByReviewer(array(12, 34)); // WHERE reviewer IN (12, 34)
     * $query->filterByReviewer(array('min' => 12)); // WHERE reviewer > 12
     * </code>
     *
     * @param     mixed $reviewer The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildMgmtReviewsQuery The current query, for fluid interface
     */
    public function filterByReviewer($reviewer = null, $comparison = null)
    {
        if (is_array($reviewer)) {
            $useMinMax = false;
            if (isset($reviewer['min'])) {
                $this->addUsingAlias(MgmtReviewsTableMap::COL_REVIEWER, $reviewer['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($reviewer['max'])) {
                $this->addUsingAlias(MgmtReviewsTableMap::COL_REVIEWER, $reviewer['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(MgmtReviewsTableMap::COL_REVIEWER, $reviewer, $comparison);
    }

    /**
     * Filter the query on the next_step column
     *
     * Example usage:
     * <code>
     * $query->filterByNextStep(1234); // WHERE next_step = 1234
     * $query->filterByNextStep(array(12, 34)); // WHERE next_step IN (12, 34)
     * $query->filterByNextStep(array('min' => 12)); // WHERE next_step > 12
     * </code>
     *
     * @param     mixed $nextStep The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildMgmtReviewsQuery The current query, for fluid interface
     */
    public function filterByNextStep($nextStep = null, $comparison = null)
    {
        if (is_array($nextStep)) {
            $useMinMax = false;
            if (isset($nextStep['min'])) {
                $this->addUsingAlias(MgmtReviewsTableMap::COL_NEXT_STEP, $nextStep['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($nextStep['max'])) {
                $this->addUsingAlias(MgmtReviewsTableMap::COL_NEXT_STEP, $nextStep['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(MgmtReviewsTableMap::COL_NEXT_STEP, $nextStep, $comparison);
    }

    /**
     * Filter the query on the comments column
     *
     * Example usage:
     * <code>
     * $query->filterByComments('fooValue');   // WHERE comments = 'fooValue'
     * $query->filterByComments('%fooValue%'); // WHERE comments LIKE '%fooValue%'
     * </code>
     *
     * @param     string $comments The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildMgmtReviewsQuery The current query, for fluid interface
     */
    public function filterByComments($comments = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($comments)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $comments)) {
                $comments = str_replace('*', '%', $comments);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(MgmtReviewsTableMap::COL_COMMENTS, $comments, $comparison);
    }

    /**
     * Filter the query on the next_review column
     *
     * Example usage:
     * <code>
     * $query->filterByNextReview('fooValue');   // WHERE next_review = 'fooValue'
     * $query->filterByNextReview('%fooValue%'); // WHERE next_review LIKE '%fooValue%'
     * </code>
     *
     * @param     string $nextReview The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildMgmtReviewsQuery The current query, for fluid interface
     */
    public function filterByNextReview($nextReview = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($nextReview)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $nextReview)) {
                $nextReview = str_replace('*', '%', $nextReview);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(MgmtReviewsTableMap::COL_NEXT_REVIEW, $nextReview, $comparison);
    }

    /**
     * Exclude object from result
     *
     * @param   ChildMgmtReviews $mgmtReviews Object to remove from the list of results
     *
     * @return $this|ChildMgmtReviewsQuery The current query, for fluid interface
     */
    public function prune($mgmtReviews = null)
    {
        if ($mgmtReviews) {
            $this->addUsingAlias(MgmtReviewsTableMap::COL_ID, $mgmtReviews->getId(), Criteria::NOT_EQUAL);
        }

        return $this;
    }

    /**
     * Deletes all rows from the mgmt_reviews table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(MgmtReviewsTableMap::DATABASE_NAME);
        }

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con) {
            $affectedRows = 0; // initialize var to track total num of affected rows
            $affectedRows += parent::doDeleteAll($con);
            // Because this db requires some delete cascade/set null emulation, we have to
            // clear the cached instance *after* the emulation has happened (since
            // instances get re-added by the select statement contained therein).
            MgmtReviewsTableMap::clearInstancePool();
            MgmtReviewsTableMap::clearRelatedInstancePool();

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
            $con = Propel::getServiceContainer()->getWriteConnection(MgmtReviewsTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(MgmtReviewsTableMap::DATABASE_NAME);

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con, $criteria) {
            $affectedRows = 0; // initialize var to track total num of affected rows

            MgmtReviewsTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            MgmtReviewsTableMap::clearRelatedInstancePool();

            return $affectedRows;
        });
    }

} // MgmtReviewsQuery
