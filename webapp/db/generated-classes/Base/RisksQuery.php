<?php

namespace Base;

use \Risks as ChildRisks;
use \RisksQuery as ChildRisksQuery;
use \Exception;
use \PDO;
use Map\RisksTableMap;
use Propel\Runtime\Propel;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\Collection\ObjectCollection;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;

/**
 * Base class that represents a query for the 'risks' table.
 *
 *
 *
 * @method     ChildRisksQuery orderById($order = Criteria::ASC) Order by the id column
 * @method     ChildRisksQuery orderByStatus($order = Criteria::ASC) Order by the status column
 * @method     ChildRisksQuery orderBySubject($order = Criteria::ASC) Order by the subject column
 * @method     ChildRisksQuery orderByReferenceId($order = Criteria::ASC) Order by the reference_id column
 * @method     ChildRisksQuery orderByRegulation($order = Criteria::ASC) Order by the regulation column
 * @method     ChildRisksQuery orderByControlNumber($order = Criteria::ASC) Order by the control_number column
 * @method     ChildRisksQuery orderByLocation($order = Criteria::ASC) Order by the location column
 * @method     ChildRisksQuery orderByCategory($order = Criteria::ASC) Order by the category column
 * @method     ChildRisksQuery orderByTeam($order = Criteria::ASC) Order by the team column
 * @method     ChildRisksQuery orderByTechnology($order = Criteria::ASC) Order by the technology column
 * @method     ChildRisksQuery orderByOwner($order = Criteria::ASC) Order by the owner column
 * @method     ChildRisksQuery orderByManager($order = Criteria::ASC) Order by the manager column
 * @method     ChildRisksQuery orderByAssessment($order = Criteria::ASC) Order by the assessment column
 * @method     ChildRisksQuery orderByNotes($order = Criteria::ASC) Order by the notes column
 * @method     ChildRisksQuery orderBySubmissionDate($order = Criteria::ASC) Order by the submission_date column
 * @method     ChildRisksQuery orderByLastUpdate($order = Criteria::ASC) Order by the last_update column
 * @method     ChildRisksQuery orderByReviewDate($order = Criteria::ASC) Order by the review_date column
 * @method     ChildRisksQuery orderByMitigationId($order = Criteria::ASC) Order by the mitigation_id column
 * @method     ChildRisksQuery orderByMgmtReview($order = Criteria::ASC) Order by the mgmt_review column
 * @method     ChildRisksQuery orderByProjectId($order = Criteria::ASC) Order by the project_id column
 * @method     ChildRisksQuery orderByCloseId($order = Criteria::ASC) Order by the close_id column
 * @method     ChildRisksQuery orderBySubmittedBy($order = Criteria::ASC) Order by the submitted_by column
 * @method     ChildRisksQuery orderByParentId($order = Criteria::ASC) Order by the parent_id column
 *
 * @method     ChildRisksQuery groupById() Group by the id column
 * @method     ChildRisksQuery groupByStatus() Group by the status column
 * @method     ChildRisksQuery groupBySubject() Group by the subject column
 * @method     ChildRisksQuery groupByReferenceId() Group by the reference_id column
 * @method     ChildRisksQuery groupByRegulation() Group by the regulation column
 * @method     ChildRisksQuery groupByControlNumber() Group by the control_number column
 * @method     ChildRisksQuery groupByLocation() Group by the location column
 * @method     ChildRisksQuery groupByCategory() Group by the category column
 * @method     ChildRisksQuery groupByTeam() Group by the team column
 * @method     ChildRisksQuery groupByTechnology() Group by the technology column
 * @method     ChildRisksQuery groupByOwner() Group by the owner column
 * @method     ChildRisksQuery groupByManager() Group by the manager column
 * @method     ChildRisksQuery groupByAssessment() Group by the assessment column
 * @method     ChildRisksQuery groupByNotes() Group by the notes column
 * @method     ChildRisksQuery groupBySubmissionDate() Group by the submission_date column
 * @method     ChildRisksQuery groupByLastUpdate() Group by the last_update column
 * @method     ChildRisksQuery groupByReviewDate() Group by the review_date column
 * @method     ChildRisksQuery groupByMitigationId() Group by the mitigation_id column
 * @method     ChildRisksQuery groupByMgmtReview() Group by the mgmt_review column
 * @method     ChildRisksQuery groupByProjectId() Group by the project_id column
 * @method     ChildRisksQuery groupByCloseId() Group by the close_id column
 * @method     ChildRisksQuery groupBySubmittedBy() Group by the submitted_by column
 * @method     ChildRisksQuery groupByParentId() Group by the parent_id column
 *
 * @method     ChildRisksQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildRisksQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildRisksQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildRisks findOne(ConnectionInterface $con = null) Return the first ChildRisks matching the query
 * @method     ChildRisks findOneOrCreate(ConnectionInterface $con = null) Return the first ChildRisks matching the query, or a new ChildRisks object populated from the query conditions when no match is found
 *
 * @method     ChildRisks findOneById(int $id) Return the first ChildRisks filtered by the id column
 * @method     ChildRisks findOneByStatus(string $status) Return the first ChildRisks filtered by the status column
 * @method     ChildRisks findOneBySubject(string $subject) Return the first ChildRisks filtered by the subject column
 * @method     ChildRisks findOneByReferenceId(string $reference_id) Return the first ChildRisks filtered by the reference_id column
 * @method     ChildRisks findOneByRegulation(int $regulation) Return the first ChildRisks filtered by the regulation column
 * @method     ChildRisks findOneByControlNumber(string $control_number) Return the first ChildRisks filtered by the control_number column
 * @method     ChildRisks findOneByLocation(int $location) Return the first ChildRisks filtered by the location column
 * @method     ChildRisks findOneByCategory(int $category) Return the first ChildRisks filtered by the category column
 * @method     ChildRisks findOneByTeam(int $team) Return the first ChildRisks filtered by the team column
 * @method     ChildRisks findOneByTechnology(int $technology) Return the first ChildRisks filtered by the technology column
 * @method     ChildRisks findOneByOwner(int $owner) Return the first ChildRisks filtered by the owner column
 * @method     ChildRisks findOneByManager(int $manager) Return the first ChildRisks filtered by the manager column
 * @method     ChildRisks findOneByAssessment(string $assessment) Return the first ChildRisks filtered by the assessment column
 * @method     ChildRisks findOneByNotes(string $notes) Return the first ChildRisks filtered by the notes column
 * @method     ChildRisks findOneBySubmissionDate(string $submission_date) Return the first ChildRisks filtered by the submission_date column
 * @method     ChildRisks findOneByLastUpdate(string $last_update) Return the first ChildRisks filtered by the last_update column
 * @method     ChildRisks findOneByReviewDate(string $review_date) Return the first ChildRisks filtered by the review_date column
 * @method     ChildRisks findOneByMitigationId(int $mitigation_id) Return the first ChildRisks filtered by the mitigation_id column
 * @method     ChildRisks findOneByMgmtReview(int $mgmt_review) Return the first ChildRisks filtered by the mgmt_review column
 * @method     ChildRisks findOneByProjectId(int $project_id) Return the first ChildRisks filtered by the project_id column
 * @method     ChildRisks findOneByCloseId(int $close_id) Return the first ChildRisks filtered by the close_id column
 * @method     ChildRisks findOneBySubmittedBy(int $submitted_by) Return the first ChildRisks filtered by the submitted_by column
 * @method     ChildRisks findOneByParentId(int $parent_id) Return the first ChildRisks filtered by the parent_id column
 *
 * @method     ChildRisks[]|ObjectCollection find(ConnectionInterface $con = null) Return ChildRisks objects based on current ModelCriteria
 * @method     ChildRisks[]|ObjectCollection findById(int $id) Return ChildRisks objects filtered by the id column
 * @method     ChildRisks[]|ObjectCollection findByStatus(string $status) Return ChildRisks objects filtered by the status column
 * @method     ChildRisks[]|ObjectCollection findBySubject(string $subject) Return ChildRisks objects filtered by the subject column
 * @method     ChildRisks[]|ObjectCollection findByReferenceId(string $reference_id) Return ChildRisks objects filtered by the reference_id column
 * @method     ChildRisks[]|ObjectCollection findByRegulation(int $regulation) Return ChildRisks objects filtered by the regulation column
 * @method     ChildRisks[]|ObjectCollection findByControlNumber(string $control_number) Return ChildRisks objects filtered by the control_number column
 * @method     ChildRisks[]|ObjectCollection findByLocation(int $location) Return ChildRisks objects filtered by the location column
 * @method     ChildRisks[]|ObjectCollection findByCategory(int $category) Return ChildRisks objects filtered by the category column
 * @method     ChildRisks[]|ObjectCollection findByTeam(int $team) Return ChildRisks objects filtered by the team column
 * @method     ChildRisks[]|ObjectCollection findByTechnology(int $technology) Return ChildRisks objects filtered by the technology column
 * @method     ChildRisks[]|ObjectCollection findByOwner(int $owner) Return ChildRisks objects filtered by the owner column
 * @method     ChildRisks[]|ObjectCollection findByManager(int $manager) Return ChildRisks objects filtered by the manager column
 * @method     ChildRisks[]|ObjectCollection findByAssessment(string $assessment) Return ChildRisks objects filtered by the assessment column
 * @method     ChildRisks[]|ObjectCollection findByNotes(string $notes) Return ChildRisks objects filtered by the notes column
 * @method     ChildRisks[]|ObjectCollection findBySubmissionDate(string $submission_date) Return ChildRisks objects filtered by the submission_date column
 * @method     ChildRisks[]|ObjectCollection findByLastUpdate(string $last_update) Return ChildRisks objects filtered by the last_update column
 * @method     ChildRisks[]|ObjectCollection findByReviewDate(string $review_date) Return ChildRisks objects filtered by the review_date column
 * @method     ChildRisks[]|ObjectCollection findByMitigationId(int $mitigation_id) Return ChildRisks objects filtered by the mitigation_id column
 * @method     ChildRisks[]|ObjectCollection findByMgmtReview(int $mgmt_review) Return ChildRisks objects filtered by the mgmt_review column
 * @method     ChildRisks[]|ObjectCollection findByProjectId(int $project_id) Return ChildRisks objects filtered by the project_id column
 * @method     ChildRisks[]|ObjectCollection findByCloseId(int $close_id) Return ChildRisks objects filtered by the close_id column
 * @method     ChildRisks[]|ObjectCollection findBySubmittedBy(int $submitted_by) Return ChildRisks objects filtered by the submitted_by column
 * @method     ChildRisks[]|ObjectCollection findByParentId(int $parent_id) Return ChildRisks objects filtered by the parent_id column
 * @method     ChildRisks[]|\Propel\Runtime\Util\PropelModelPager paginate($page = 1, $maxPerPage = 10, ConnectionInterface $con = null) Issue a SELECT query based on the current ModelCriteria and uses a page and a maximum number of results per page to compute an offset and a limit
 *
 */
abstract class RisksQuery extends ModelCriteria
{

    /**
     * Initializes internal state of \Base\RisksQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'lessrisk', $modelName = '\\Risks', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildRisksQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildRisksQuery
     */
    public static function create($modelAlias = null, Criteria $criteria = null)
    {
        if ($criteria instanceof ChildRisksQuery) {
            return $criteria;
        }
        $query = new ChildRisksQuery();
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
     * @return ChildRisks|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, ConnectionInterface $con = null)
    {
        if ($key === null) {
            return null;
        }
        if ((null !== ($obj = RisksTableMap::getInstanceFromPool((string) $key))) && !$this->formatter) {
            // the object is already in the instance pool
            return $obj;
        }
        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(RisksTableMap::DATABASE_NAME);
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
     * @return ChildRisks A model object, or null if the key is not found
     */
    protected function findPkSimple($key, ConnectionInterface $con)
    {
        $sql = 'SELECT id, status, subject, reference_id, regulation, control_number, location, category, team, technology, owner, manager, assessment, notes, submission_date, last_update, review_date, mitigation_id, mgmt_review, project_id, close_id, submitted_by, parent_id FROM risks WHERE id = :p0';
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
            /** @var ChildRisks $obj */
            $obj = new ChildRisks();
            $obj->hydrate($row);
            RisksTableMap::addInstanceToPool($obj, (string) $key);
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
     * @return ChildRisks|array|mixed the result, formatted by the current formatter
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
     * @return $this|ChildRisksQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {

        return $this->addUsingAlias(RisksTableMap::COL_ID, $key, Criteria::EQUAL);
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return $this|ChildRisksQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {

        return $this->addUsingAlias(RisksTableMap::COL_ID, $keys, Criteria::IN);
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
     * @return $this|ChildRisksQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id)) {
            $useMinMax = false;
            if (isset($id['min'])) {
                $this->addUsingAlias(RisksTableMap::COL_ID, $id['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($id['max'])) {
                $this->addUsingAlias(RisksTableMap::COL_ID, $id['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(RisksTableMap::COL_ID, $id, $comparison);
    }

    /**
     * Filter the query on the status column
     *
     * Example usage:
     * <code>
     * $query->filterByStatus('fooValue');   // WHERE status = 'fooValue'
     * $query->filterByStatus('%fooValue%'); // WHERE status LIKE '%fooValue%'
     * </code>
     *
     * @param     string $status The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildRisksQuery The current query, for fluid interface
     */
    public function filterByStatus($status = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($status)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $status)) {
                $status = str_replace('*', '%', $status);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(RisksTableMap::COL_STATUS, $status, $comparison);
    }

    /**
     * Filter the query on the subject column
     *
     * Example usage:
     * <code>
     * $query->filterBySubject('fooValue');   // WHERE subject = 'fooValue'
     * $query->filterBySubject('%fooValue%'); // WHERE subject LIKE '%fooValue%'
     * </code>
     *
     * @param     string $subject The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildRisksQuery The current query, for fluid interface
     */
    public function filterBySubject($subject = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($subject)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $subject)) {
                $subject = str_replace('*', '%', $subject);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(RisksTableMap::COL_SUBJECT, $subject, $comparison);
    }

    /**
     * Filter the query on the reference_id column
     *
     * Example usage:
     * <code>
     * $query->filterByReferenceId('fooValue');   // WHERE reference_id = 'fooValue'
     * $query->filterByReferenceId('%fooValue%'); // WHERE reference_id LIKE '%fooValue%'
     * </code>
     *
     * @param     string $referenceId The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildRisksQuery The current query, for fluid interface
     */
    public function filterByReferenceId($referenceId = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($referenceId)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $referenceId)) {
                $referenceId = str_replace('*', '%', $referenceId);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(RisksTableMap::COL_REFERENCE_ID, $referenceId, $comparison);
    }

    /**
     * Filter the query on the regulation column
     *
     * Example usage:
     * <code>
     * $query->filterByRegulation(1234); // WHERE regulation = 1234
     * $query->filterByRegulation(array(12, 34)); // WHERE regulation IN (12, 34)
     * $query->filterByRegulation(array('min' => 12)); // WHERE regulation > 12
     * </code>
     *
     * @param     mixed $regulation The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildRisksQuery The current query, for fluid interface
     */
    public function filterByRegulation($regulation = null, $comparison = null)
    {
        if (is_array($regulation)) {
            $useMinMax = false;
            if (isset($regulation['min'])) {
                $this->addUsingAlias(RisksTableMap::COL_REGULATION, $regulation['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($regulation['max'])) {
                $this->addUsingAlias(RisksTableMap::COL_REGULATION, $regulation['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(RisksTableMap::COL_REGULATION, $regulation, $comparison);
    }

    /**
     * Filter the query on the control_number column
     *
     * Example usage:
     * <code>
     * $query->filterByControlNumber('fooValue');   // WHERE control_number = 'fooValue'
     * $query->filterByControlNumber('%fooValue%'); // WHERE control_number LIKE '%fooValue%'
     * </code>
     *
     * @param     string $controlNumber The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildRisksQuery The current query, for fluid interface
     */
    public function filterByControlNumber($controlNumber = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($controlNumber)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $controlNumber)) {
                $controlNumber = str_replace('*', '%', $controlNumber);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(RisksTableMap::COL_CONTROL_NUMBER, $controlNumber, $comparison);
    }

    /**
     * Filter the query on the location column
     *
     * Example usage:
     * <code>
     * $query->filterByLocation(1234); // WHERE location = 1234
     * $query->filterByLocation(array(12, 34)); // WHERE location IN (12, 34)
     * $query->filterByLocation(array('min' => 12)); // WHERE location > 12
     * </code>
     *
     * @param     mixed $location The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildRisksQuery The current query, for fluid interface
     */
    public function filterByLocation($location = null, $comparison = null)
    {
        if (is_array($location)) {
            $useMinMax = false;
            if (isset($location['min'])) {
                $this->addUsingAlias(RisksTableMap::COL_LOCATION, $location['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($location['max'])) {
                $this->addUsingAlias(RisksTableMap::COL_LOCATION, $location['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(RisksTableMap::COL_LOCATION, $location, $comparison);
    }

    /**
     * Filter the query on the category column
     *
     * Example usage:
     * <code>
     * $query->filterByCategory(1234); // WHERE category = 1234
     * $query->filterByCategory(array(12, 34)); // WHERE category IN (12, 34)
     * $query->filterByCategory(array('min' => 12)); // WHERE category > 12
     * </code>
     *
     * @param     mixed $category The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildRisksQuery The current query, for fluid interface
     */
    public function filterByCategory($category = null, $comparison = null)
    {
        if (is_array($category)) {
            $useMinMax = false;
            if (isset($category['min'])) {
                $this->addUsingAlias(RisksTableMap::COL_CATEGORY, $category['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($category['max'])) {
                $this->addUsingAlias(RisksTableMap::COL_CATEGORY, $category['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(RisksTableMap::COL_CATEGORY, $category, $comparison);
    }

    /**
     * Filter the query on the team column
     *
     * Example usage:
     * <code>
     * $query->filterByTeam(1234); // WHERE team = 1234
     * $query->filterByTeam(array(12, 34)); // WHERE team IN (12, 34)
     * $query->filterByTeam(array('min' => 12)); // WHERE team > 12
     * </code>
     *
     * @param     mixed $team The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildRisksQuery The current query, for fluid interface
     */
    public function filterByTeam($team = null, $comparison = null)
    {
        if (is_array($team)) {
            $useMinMax = false;
            if (isset($team['min'])) {
                $this->addUsingAlias(RisksTableMap::COL_TEAM, $team['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($team['max'])) {
                $this->addUsingAlias(RisksTableMap::COL_TEAM, $team['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(RisksTableMap::COL_TEAM, $team, $comparison);
    }

    /**
     * Filter the query on the technology column
     *
     * Example usage:
     * <code>
     * $query->filterByTechnology(1234); // WHERE technology = 1234
     * $query->filterByTechnology(array(12, 34)); // WHERE technology IN (12, 34)
     * $query->filterByTechnology(array('min' => 12)); // WHERE technology > 12
     * </code>
     *
     * @param     mixed $technology The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildRisksQuery The current query, for fluid interface
     */
    public function filterByTechnology($technology = null, $comparison = null)
    {
        if (is_array($technology)) {
            $useMinMax = false;
            if (isset($technology['min'])) {
                $this->addUsingAlias(RisksTableMap::COL_TECHNOLOGY, $technology['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($technology['max'])) {
                $this->addUsingAlias(RisksTableMap::COL_TECHNOLOGY, $technology['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(RisksTableMap::COL_TECHNOLOGY, $technology, $comparison);
    }

    /**
     * Filter the query on the owner column
     *
     * Example usage:
     * <code>
     * $query->filterByOwner(1234); // WHERE owner = 1234
     * $query->filterByOwner(array(12, 34)); // WHERE owner IN (12, 34)
     * $query->filterByOwner(array('min' => 12)); // WHERE owner > 12
     * </code>
     *
     * @param     mixed $owner The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildRisksQuery The current query, for fluid interface
     */
    public function filterByOwner($owner = null, $comparison = null)
    {
        if (is_array($owner)) {
            $useMinMax = false;
            if (isset($owner['min'])) {
                $this->addUsingAlias(RisksTableMap::COL_OWNER, $owner['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($owner['max'])) {
                $this->addUsingAlias(RisksTableMap::COL_OWNER, $owner['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(RisksTableMap::COL_OWNER, $owner, $comparison);
    }

    /**
     * Filter the query on the manager column
     *
     * Example usage:
     * <code>
     * $query->filterByManager(1234); // WHERE manager = 1234
     * $query->filterByManager(array(12, 34)); // WHERE manager IN (12, 34)
     * $query->filterByManager(array('min' => 12)); // WHERE manager > 12
     * </code>
     *
     * @param     mixed $manager The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildRisksQuery The current query, for fluid interface
     */
    public function filterByManager($manager = null, $comparison = null)
    {
        if (is_array($manager)) {
            $useMinMax = false;
            if (isset($manager['min'])) {
                $this->addUsingAlias(RisksTableMap::COL_MANAGER, $manager['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($manager['max'])) {
                $this->addUsingAlias(RisksTableMap::COL_MANAGER, $manager['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(RisksTableMap::COL_MANAGER, $manager, $comparison);
    }

    /**
     * Filter the query on the assessment column
     *
     * Example usage:
     * <code>
     * $query->filterByAssessment('fooValue');   // WHERE assessment = 'fooValue'
     * $query->filterByAssessment('%fooValue%'); // WHERE assessment LIKE '%fooValue%'
     * </code>
     *
     * @param     string $assessment The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildRisksQuery The current query, for fluid interface
     */
    public function filterByAssessment($assessment = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($assessment)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $assessment)) {
                $assessment = str_replace('*', '%', $assessment);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(RisksTableMap::COL_ASSESSMENT, $assessment, $comparison);
    }

    /**
     * Filter the query on the notes column
     *
     * Example usage:
     * <code>
     * $query->filterByNotes('fooValue');   // WHERE notes = 'fooValue'
     * $query->filterByNotes('%fooValue%'); // WHERE notes LIKE '%fooValue%'
     * </code>
     *
     * @param     string $notes The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildRisksQuery The current query, for fluid interface
     */
    public function filterByNotes($notes = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($notes)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $notes)) {
                $notes = str_replace('*', '%', $notes);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(RisksTableMap::COL_NOTES, $notes, $comparison);
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
     * @return $this|ChildRisksQuery The current query, for fluid interface
     */
    public function filterBySubmissionDate($submissionDate = null, $comparison = null)
    {
        if (is_array($submissionDate)) {
            $useMinMax = false;
            if (isset($submissionDate['min'])) {
                $this->addUsingAlias(RisksTableMap::COL_SUBMISSION_DATE, $submissionDate['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($submissionDate['max'])) {
                $this->addUsingAlias(RisksTableMap::COL_SUBMISSION_DATE, $submissionDate['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(RisksTableMap::COL_SUBMISSION_DATE, $submissionDate, $comparison);
    }

    /**
     * Filter the query on the last_update column
     *
     * Example usage:
     * <code>
     * $query->filterByLastUpdate('2011-03-14'); // WHERE last_update = '2011-03-14'
     * $query->filterByLastUpdate('now'); // WHERE last_update = '2011-03-14'
     * $query->filterByLastUpdate(array('max' => 'yesterday')); // WHERE last_update > '2011-03-13'
     * </code>
     *
     * @param     mixed $lastUpdate The value to use as filter.
     *              Values can be integers (unix timestamps), DateTime objects, or strings.
     *              Empty strings are treated as NULL.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildRisksQuery The current query, for fluid interface
     */
    public function filterByLastUpdate($lastUpdate = null, $comparison = null)
    {
        if (is_array($lastUpdate)) {
            $useMinMax = false;
            if (isset($lastUpdate['min'])) {
                $this->addUsingAlias(RisksTableMap::COL_LAST_UPDATE, $lastUpdate['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($lastUpdate['max'])) {
                $this->addUsingAlias(RisksTableMap::COL_LAST_UPDATE, $lastUpdate['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(RisksTableMap::COL_LAST_UPDATE, $lastUpdate, $comparison);
    }

    /**
     * Filter the query on the review_date column
     *
     * Example usage:
     * <code>
     * $query->filterByReviewDate('2011-03-14'); // WHERE review_date = '2011-03-14'
     * $query->filterByReviewDate('now'); // WHERE review_date = '2011-03-14'
     * $query->filterByReviewDate(array('max' => 'yesterday')); // WHERE review_date > '2011-03-13'
     * </code>
     *
     * @param     mixed $reviewDate The value to use as filter.
     *              Values can be integers (unix timestamps), DateTime objects, or strings.
     *              Empty strings are treated as NULL.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildRisksQuery The current query, for fluid interface
     */
    public function filterByReviewDate($reviewDate = null, $comparison = null)
    {
        if (is_array($reviewDate)) {
            $useMinMax = false;
            if (isset($reviewDate['min'])) {
                $this->addUsingAlias(RisksTableMap::COL_REVIEW_DATE, $reviewDate['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($reviewDate['max'])) {
                $this->addUsingAlias(RisksTableMap::COL_REVIEW_DATE, $reviewDate['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(RisksTableMap::COL_REVIEW_DATE, $reviewDate, $comparison);
    }

    /**
     * Filter the query on the mitigation_id column
     *
     * Example usage:
     * <code>
     * $query->filterByMitigationId(1234); // WHERE mitigation_id = 1234
     * $query->filterByMitigationId(array(12, 34)); // WHERE mitigation_id IN (12, 34)
     * $query->filterByMitigationId(array('min' => 12)); // WHERE mitigation_id > 12
     * </code>
     *
     * @param     mixed $mitigationId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildRisksQuery The current query, for fluid interface
     */
    public function filterByMitigationId($mitigationId = null, $comparison = null)
    {
        if (is_array($mitigationId)) {
            $useMinMax = false;
            if (isset($mitigationId['min'])) {
                $this->addUsingAlias(RisksTableMap::COL_MITIGATION_ID, $mitigationId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($mitigationId['max'])) {
                $this->addUsingAlias(RisksTableMap::COL_MITIGATION_ID, $mitigationId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(RisksTableMap::COL_MITIGATION_ID, $mitigationId, $comparison);
    }

    /**
     * Filter the query on the mgmt_review column
     *
     * Example usage:
     * <code>
     * $query->filterByMgmtReview(1234); // WHERE mgmt_review = 1234
     * $query->filterByMgmtReview(array(12, 34)); // WHERE mgmt_review IN (12, 34)
     * $query->filterByMgmtReview(array('min' => 12)); // WHERE mgmt_review > 12
     * </code>
     *
     * @param     mixed $mgmtReview The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildRisksQuery The current query, for fluid interface
     */
    public function filterByMgmtReview($mgmtReview = null, $comparison = null)
    {
        if (is_array($mgmtReview)) {
            $useMinMax = false;
            if (isset($mgmtReview['min'])) {
                $this->addUsingAlias(RisksTableMap::COL_MGMT_REVIEW, $mgmtReview['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($mgmtReview['max'])) {
                $this->addUsingAlias(RisksTableMap::COL_MGMT_REVIEW, $mgmtReview['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(RisksTableMap::COL_MGMT_REVIEW, $mgmtReview, $comparison);
    }

    /**
     * Filter the query on the project_id column
     *
     * Example usage:
     * <code>
     * $query->filterByProjectId(1234); // WHERE project_id = 1234
     * $query->filterByProjectId(array(12, 34)); // WHERE project_id IN (12, 34)
     * $query->filterByProjectId(array('min' => 12)); // WHERE project_id > 12
     * </code>
     *
     * @param     mixed $projectId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildRisksQuery The current query, for fluid interface
     */
    public function filterByProjectId($projectId = null, $comparison = null)
    {
        if (is_array($projectId)) {
            $useMinMax = false;
            if (isset($projectId['min'])) {
                $this->addUsingAlias(RisksTableMap::COL_PROJECT_ID, $projectId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($projectId['max'])) {
                $this->addUsingAlias(RisksTableMap::COL_PROJECT_ID, $projectId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(RisksTableMap::COL_PROJECT_ID, $projectId, $comparison);
    }

    /**
     * Filter the query on the close_id column
     *
     * Example usage:
     * <code>
     * $query->filterByCloseId(1234); // WHERE close_id = 1234
     * $query->filterByCloseId(array(12, 34)); // WHERE close_id IN (12, 34)
     * $query->filterByCloseId(array('min' => 12)); // WHERE close_id > 12
     * </code>
     *
     * @param     mixed $closeId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildRisksQuery The current query, for fluid interface
     */
    public function filterByCloseId($closeId = null, $comparison = null)
    {
        if (is_array($closeId)) {
            $useMinMax = false;
            if (isset($closeId['min'])) {
                $this->addUsingAlias(RisksTableMap::COL_CLOSE_ID, $closeId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($closeId['max'])) {
                $this->addUsingAlias(RisksTableMap::COL_CLOSE_ID, $closeId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(RisksTableMap::COL_CLOSE_ID, $closeId, $comparison);
    }

    /**
     * Filter the query on the submitted_by column
     *
     * Example usage:
     * <code>
     * $query->filterBySubmittedBy(1234); // WHERE submitted_by = 1234
     * $query->filterBySubmittedBy(array(12, 34)); // WHERE submitted_by IN (12, 34)
     * $query->filterBySubmittedBy(array('min' => 12)); // WHERE submitted_by > 12
     * </code>
     *
     * @param     mixed $submittedBy The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildRisksQuery The current query, for fluid interface
     */
    public function filterBySubmittedBy($submittedBy = null, $comparison = null)
    {
        if (is_array($submittedBy)) {
            $useMinMax = false;
            if (isset($submittedBy['min'])) {
                $this->addUsingAlias(RisksTableMap::COL_SUBMITTED_BY, $submittedBy['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($submittedBy['max'])) {
                $this->addUsingAlias(RisksTableMap::COL_SUBMITTED_BY, $submittedBy['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(RisksTableMap::COL_SUBMITTED_BY, $submittedBy, $comparison);
    }

    /**
     * Filter the query on the parent_id column
     *
     * Example usage:
     * <code>
     * $query->filterByParentId(1234); // WHERE parent_id = 1234
     * $query->filterByParentId(array(12, 34)); // WHERE parent_id IN (12, 34)
     * $query->filterByParentId(array('min' => 12)); // WHERE parent_id > 12
     * </code>
     *
     * @param     mixed $parentId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildRisksQuery The current query, for fluid interface
     */
    public function filterByParentId($parentId = null, $comparison = null)
    {
        if (is_array($parentId)) {
            $useMinMax = false;
            if (isset($parentId['min'])) {
                $this->addUsingAlias(RisksTableMap::COL_PARENT_ID, $parentId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($parentId['max'])) {
                $this->addUsingAlias(RisksTableMap::COL_PARENT_ID, $parentId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(RisksTableMap::COL_PARENT_ID, $parentId, $comparison);
    }

    /**
     * Exclude object from result
     *
     * @param   ChildRisks $risks Object to remove from the list of results
     *
     * @return $this|ChildRisksQuery The current query, for fluid interface
     */
    public function prune($risks = null)
    {
        if ($risks) {
            $this->addUsingAlias(RisksTableMap::COL_ID, $risks->getId(), Criteria::NOT_EQUAL);
        }

        return $this;
    }

    /**
     * Deletes all rows from the risks table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(RisksTableMap::DATABASE_NAME);
        }

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con) {
            $affectedRows = 0; // initialize var to track total num of affected rows
            $affectedRows += parent::doDeleteAll($con);
            // Because this db requires some delete cascade/set null emulation, we have to
            // clear the cached instance *after* the emulation has happened (since
            // instances get re-added by the select statement contained therein).
            RisksTableMap::clearInstancePool();
            RisksTableMap::clearRelatedInstancePool();

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
            $con = Propel::getServiceContainer()->getWriteConnection(RisksTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(RisksTableMap::DATABASE_NAME);

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con, $criteria) {
            $affectedRows = 0; // initialize var to track total num of affected rows

            RisksTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            RisksTableMap::clearRelatedInstancePool();

            return $affectedRows;
        });
    }

} // RisksQuery
