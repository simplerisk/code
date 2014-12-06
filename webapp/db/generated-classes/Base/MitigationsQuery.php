<?php

namespace Base;

use \Mitigations as ChildMitigations;
use \MitigationsQuery as ChildMitigationsQuery;
use \Exception;
use \PDO;
use Map\MitigationsTableMap;
use Propel\Runtime\Propel;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\Collection\ObjectCollection;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;

/**
 * Base class that represents a query for the 'mitigations' table.
 *
 *
 *
 * @method     ChildMitigationsQuery orderById($order = Criteria::ASC) Order by the id column
 * @method     ChildMitigationsQuery orderByRiskId($order = Criteria::ASC) Order by the risk_id column
 * @method     ChildMitigationsQuery orderBySubmissionDate($order = Criteria::ASC) Order by the submission_date column
 * @method     ChildMitigationsQuery orderByLastUpdate($order = Criteria::ASC) Order by the last_update column
 * @method     ChildMitigationsQuery orderByPlanningStrategy($order = Criteria::ASC) Order by the planning_strategy column
 * @method     ChildMitigationsQuery orderByMitigationEffort($order = Criteria::ASC) Order by the mitigation_effort column
 * @method     ChildMitigationsQuery orderByCurrentSolution($order = Criteria::ASC) Order by the current_solution column
 * @method     ChildMitigationsQuery orderBySecurityRequirements($order = Criteria::ASC) Order by the security_requirements column
 * @method     ChildMitigationsQuery orderBySecurityRecommendations($order = Criteria::ASC) Order by the security_recommendations column
 * @method     ChildMitigationsQuery orderBySubmittedBy($order = Criteria::ASC) Order by the submitted_by column
 *
 * @method     ChildMitigationsQuery groupById() Group by the id column
 * @method     ChildMitigationsQuery groupByRiskId() Group by the risk_id column
 * @method     ChildMitigationsQuery groupBySubmissionDate() Group by the submission_date column
 * @method     ChildMitigationsQuery groupByLastUpdate() Group by the last_update column
 * @method     ChildMitigationsQuery groupByPlanningStrategy() Group by the planning_strategy column
 * @method     ChildMitigationsQuery groupByMitigationEffort() Group by the mitigation_effort column
 * @method     ChildMitigationsQuery groupByCurrentSolution() Group by the current_solution column
 * @method     ChildMitigationsQuery groupBySecurityRequirements() Group by the security_requirements column
 * @method     ChildMitigationsQuery groupBySecurityRecommendations() Group by the security_recommendations column
 * @method     ChildMitigationsQuery groupBySubmittedBy() Group by the submitted_by column
 *
 * @method     ChildMitigationsQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildMitigationsQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildMitigationsQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildMitigations findOne(ConnectionInterface $con = null) Return the first ChildMitigations matching the query
 * @method     ChildMitigations findOneOrCreate(ConnectionInterface $con = null) Return the first ChildMitigations matching the query, or a new ChildMitigations object populated from the query conditions when no match is found
 *
 * @method     ChildMitigations findOneById(int $id) Return the first ChildMitigations filtered by the id column
 * @method     ChildMitigations findOneByRiskId(int $risk_id) Return the first ChildMitigations filtered by the risk_id column
 * @method     ChildMitigations findOneBySubmissionDate(string $submission_date) Return the first ChildMitigations filtered by the submission_date column
 * @method     ChildMitigations findOneByLastUpdate(string $last_update) Return the first ChildMitigations filtered by the last_update column
 * @method     ChildMitigations findOneByPlanningStrategy(int $planning_strategy) Return the first ChildMitigations filtered by the planning_strategy column
 * @method     ChildMitigations findOneByMitigationEffort(int $mitigation_effort) Return the first ChildMitigations filtered by the mitigation_effort column
 * @method     ChildMitigations findOneByCurrentSolution(string $current_solution) Return the first ChildMitigations filtered by the current_solution column
 * @method     ChildMitigations findOneBySecurityRequirements(string $security_requirements) Return the first ChildMitigations filtered by the security_requirements column
 * @method     ChildMitigations findOneBySecurityRecommendations(string $security_recommendations) Return the first ChildMitigations filtered by the security_recommendations column
 * @method     ChildMitigations findOneBySubmittedBy(int $submitted_by) Return the first ChildMitigations filtered by the submitted_by column
 *
 * @method     ChildMitigations[]|ObjectCollection find(ConnectionInterface $con = null) Return ChildMitigations objects based on current ModelCriteria
 * @method     ChildMitigations[]|ObjectCollection findById(int $id) Return ChildMitigations objects filtered by the id column
 * @method     ChildMitigations[]|ObjectCollection findByRiskId(int $risk_id) Return ChildMitigations objects filtered by the risk_id column
 * @method     ChildMitigations[]|ObjectCollection findBySubmissionDate(string $submission_date) Return ChildMitigations objects filtered by the submission_date column
 * @method     ChildMitigations[]|ObjectCollection findByLastUpdate(string $last_update) Return ChildMitigations objects filtered by the last_update column
 * @method     ChildMitigations[]|ObjectCollection findByPlanningStrategy(int $planning_strategy) Return ChildMitigations objects filtered by the planning_strategy column
 * @method     ChildMitigations[]|ObjectCollection findByMitigationEffort(int $mitigation_effort) Return ChildMitigations objects filtered by the mitigation_effort column
 * @method     ChildMitigations[]|ObjectCollection findByCurrentSolution(string $current_solution) Return ChildMitigations objects filtered by the current_solution column
 * @method     ChildMitigations[]|ObjectCollection findBySecurityRequirements(string $security_requirements) Return ChildMitigations objects filtered by the security_requirements column
 * @method     ChildMitigations[]|ObjectCollection findBySecurityRecommendations(string $security_recommendations) Return ChildMitigations objects filtered by the security_recommendations column
 * @method     ChildMitigations[]|ObjectCollection findBySubmittedBy(int $submitted_by) Return ChildMitigations objects filtered by the submitted_by column
 * @method     ChildMitigations[]|\Propel\Runtime\Util\PropelModelPager paginate($page = 1, $maxPerPage = 10, ConnectionInterface $con = null) Issue a SELECT query based on the current ModelCriteria and uses a page and a maximum number of results per page to compute an offset and a limit
 *
 */
abstract class MitigationsQuery extends ModelCriteria
{

    /**
     * Initializes internal state of \Base\MitigationsQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'lessrisk', $modelName = '\\Mitigations', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildMitigationsQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildMitigationsQuery
     */
    public static function create($modelAlias = null, Criteria $criteria = null)
    {
        if ($criteria instanceof ChildMitigationsQuery) {
            return $criteria;
        }
        $query = new ChildMitigationsQuery();
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
     * @return ChildMitigations|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, ConnectionInterface $con = null)
    {
        if ($key === null) {
            return null;
        }
        if ((null !== ($obj = MitigationsTableMap::getInstanceFromPool((string) $key))) && !$this->formatter) {
            // the object is already in the instance pool
            return $obj;
        }
        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(MitigationsTableMap::DATABASE_NAME);
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
     * @return ChildMitigations A model object, or null if the key is not found
     */
    protected function findPkSimple($key, ConnectionInterface $con)
    {
        $sql = 'SELECT id, risk_id, submission_date, last_update, planning_strategy, mitigation_effort, current_solution, security_requirements, security_recommendations, submitted_by FROM mitigations WHERE id = :p0';
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
            /** @var ChildMitigations $obj */
            $obj = new ChildMitigations();
            $obj->hydrate($row);
            MitigationsTableMap::addInstanceToPool($obj, (string) $key);
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
     * @return ChildMitigations|array|mixed the result, formatted by the current formatter
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
     * @return $this|ChildMitigationsQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {

        return $this->addUsingAlias(MitigationsTableMap::COL_ID, $key, Criteria::EQUAL);
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return $this|ChildMitigationsQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {

        return $this->addUsingAlias(MitigationsTableMap::COL_ID, $keys, Criteria::IN);
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
     * @return $this|ChildMitigationsQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id)) {
            $useMinMax = false;
            if (isset($id['min'])) {
                $this->addUsingAlias(MitigationsTableMap::COL_ID, $id['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($id['max'])) {
                $this->addUsingAlias(MitigationsTableMap::COL_ID, $id['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(MitigationsTableMap::COL_ID, $id, $comparison);
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
     * @return $this|ChildMitigationsQuery The current query, for fluid interface
     */
    public function filterByRiskId($riskId = null, $comparison = null)
    {
        if (is_array($riskId)) {
            $useMinMax = false;
            if (isset($riskId['min'])) {
                $this->addUsingAlias(MitigationsTableMap::COL_RISK_ID, $riskId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($riskId['max'])) {
                $this->addUsingAlias(MitigationsTableMap::COL_RISK_ID, $riskId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(MitigationsTableMap::COL_RISK_ID, $riskId, $comparison);
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
     * @return $this|ChildMitigationsQuery The current query, for fluid interface
     */
    public function filterBySubmissionDate($submissionDate = null, $comparison = null)
    {
        if (is_array($submissionDate)) {
            $useMinMax = false;
            if (isset($submissionDate['min'])) {
                $this->addUsingAlias(MitigationsTableMap::COL_SUBMISSION_DATE, $submissionDate['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($submissionDate['max'])) {
                $this->addUsingAlias(MitigationsTableMap::COL_SUBMISSION_DATE, $submissionDate['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(MitigationsTableMap::COL_SUBMISSION_DATE, $submissionDate, $comparison);
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
     * @return $this|ChildMitigationsQuery The current query, for fluid interface
     */
    public function filterByLastUpdate($lastUpdate = null, $comparison = null)
    {
        if (is_array($lastUpdate)) {
            $useMinMax = false;
            if (isset($lastUpdate['min'])) {
                $this->addUsingAlias(MitigationsTableMap::COL_LAST_UPDATE, $lastUpdate['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($lastUpdate['max'])) {
                $this->addUsingAlias(MitigationsTableMap::COL_LAST_UPDATE, $lastUpdate['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(MitigationsTableMap::COL_LAST_UPDATE, $lastUpdate, $comparison);
    }

    /**
     * Filter the query on the planning_strategy column
     *
     * Example usage:
     * <code>
     * $query->filterByPlanningStrategy(1234); // WHERE planning_strategy = 1234
     * $query->filterByPlanningStrategy(array(12, 34)); // WHERE planning_strategy IN (12, 34)
     * $query->filterByPlanningStrategy(array('min' => 12)); // WHERE planning_strategy > 12
     * </code>
     *
     * @param     mixed $planningStrategy The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildMitigationsQuery The current query, for fluid interface
     */
    public function filterByPlanningStrategy($planningStrategy = null, $comparison = null)
    {
        if (is_array($planningStrategy)) {
            $useMinMax = false;
            if (isset($planningStrategy['min'])) {
                $this->addUsingAlias(MitigationsTableMap::COL_PLANNING_STRATEGY, $planningStrategy['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($planningStrategy['max'])) {
                $this->addUsingAlias(MitigationsTableMap::COL_PLANNING_STRATEGY, $planningStrategy['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(MitigationsTableMap::COL_PLANNING_STRATEGY, $planningStrategy, $comparison);
    }

    /**
     * Filter the query on the mitigation_effort column
     *
     * Example usage:
     * <code>
     * $query->filterByMitigationEffort(1234); // WHERE mitigation_effort = 1234
     * $query->filterByMitigationEffort(array(12, 34)); // WHERE mitigation_effort IN (12, 34)
     * $query->filterByMitigationEffort(array('min' => 12)); // WHERE mitigation_effort > 12
     * </code>
     *
     * @param     mixed $mitigationEffort The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildMitigationsQuery The current query, for fluid interface
     */
    public function filterByMitigationEffort($mitigationEffort = null, $comparison = null)
    {
        if (is_array($mitigationEffort)) {
            $useMinMax = false;
            if (isset($mitigationEffort['min'])) {
                $this->addUsingAlias(MitigationsTableMap::COL_MITIGATION_EFFORT, $mitigationEffort['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($mitigationEffort['max'])) {
                $this->addUsingAlias(MitigationsTableMap::COL_MITIGATION_EFFORT, $mitigationEffort['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(MitigationsTableMap::COL_MITIGATION_EFFORT, $mitigationEffort, $comparison);
    }

    /**
     * Filter the query on the current_solution column
     *
     * Example usage:
     * <code>
     * $query->filterByCurrentSolution('fooValue');   // WHERE current_solution = 'fooValue'
     * $query->filterByCurrentSolution('%fooValue%'); // WHERE current_solution LIKE '%fooValue%'
     * </code>
     *
     * @param     string $currentSolution The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildMitigationsQuery The current query, for fluid interface
     */
    public function filterByCurrentSolution($currentSolution = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($currentSolution)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $currentSolution)) {
                $currentSolution = str_replace('*', '%', $currentSolution);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(MitigationsTableMap::COL_CURRENT_SOLUTION, $currentSolution, $comparison);
    }

    /**
     * Filter the query on the security_requirements column
     *
     * Example usage:
     * <code>
     * $query->filterBySecurityRequirements('fooValue');   // WHERE security_requirements = 'fooValue'
     * $query->filterBySecurityRequirements('%fooValue%'); // WHERE security_requirements LIKE '%fooValue%'
     * </code>
     *
     * @param     string $securityRequirements The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildMitigationsQuery The current query, for fluid interface
     */
    public function filterBySecurityRequirements($securityRequirements = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($securityRequirements)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $securityRequirements)) {
                $securityRequirements = str_replace('*', '%', $securityRequirements);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(MitigationsTableMap::COL_SECURITY_REQUIREMENTS, $securityRequirements, $comparison);
    }

    /**
     * Filter the query on the security_recommendations column
     *
     * Example usage:
     * <code>
     * $query->filterBySecurityRecommendations('fooValue');   // WHERE security_recommendations = 'fooValue'
     * $query->filterBySecurityRecommendations('%fooValue%'); // WHERE security_recommendations LIKE '%fooValue%'
     * </code>
     *
     * @param     string $securityRecommendations The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildMitigationsQuery The current query, for fluid interface
     */
    public function filterBySecurityRecommendations($securityRecommendations = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($securityRecommendations)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $securityRecommendations)) {
                $securityRecommendations = str_replace('*', '%', $securityRecommendations);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(MitigationsTableMap::COL_SECURITY_RECOMMENDATIONS, $securityRecommendations, $comparison);
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
     * @return $this|ChildMitigationsQuery The current query, for fluid interface
     */
    public function filterBySubmittedBy($submittedBy = null, $comparison = null)
    {
        if (is_array($submittedBy)) {
            $useMinMax = false;
            if (isset($submittedBy['min'])) {
                $this->addUsingAlias(MitigationsTableMap::COL_SUBMITTED_BY, $submittedBy['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($submittedBy['max'])) {
                $this->addUsingAlias(MitigationsTableMap::COL_SUBMITTED_BY, $submittedBy['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(MitigationsTableMap::COL_SUBMITTED_BY, $submittedBy, $comparison);
    }

    /**
     * Exclude object from result
     *
     * @param   ChildMitigations $mitigations Object to remove from the list of results
     *
     * @return $this|ChildMitigationsQuery The current query, for fluid interface
     */
    public function prune($mitigations = null)
    {
        if ($mitigations) {
            $this->addUsingAlias(MitigationsTableMap::COL_ID, $mitigations->getId(), Criteria::NOT_EQUAL);
        }

        return $this;
    }

    /**
     * Deletes all rows from the mitigations table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(MitigationsTableMap::DATABASE_NAME);
        }

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con) {
            $affectedRows = 0; // initialize var to track total num of affected rows
            $affectedRows += parent::doDeleteAll($con);
            // Because this db requires some delete cascade/set null emulation, we have to
            // clear the cached instance *after* the emulation has happened (since
            // instances get re-added by the select statement contained therein).
            MitigationsTableMap::clearInstancePool();
            MitigationsTableMap::clearRelatedInstancePool();

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
            $con = Propel::getServiceContainer()->getWriteConnection(MitigationsTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(MitigationsTableMap::DATABASE_NAME);

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con, $criteria) {
            $affectedRows = 0; // initialize var to track total num of affected rows

            MitigationsTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            MitigationsTableMap::clearRelatedInstancePool();

            return $affectedRows;
        });
    }

} // MitigationsQuery
