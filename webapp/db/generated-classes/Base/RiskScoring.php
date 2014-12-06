<?php

namespace Base;

use \RiskScoringQuery as ChildRiskScoringQuery;
use \Exception;
use \PDO;
use Map\RiskScoringTableMap;
use Propel\Runtime\Propel;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Propel\Runtime\Collection\Collection;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\BadMethodCallException;
use Propel\Runtime\Exception\LogicException;
use Propel\Runtime\Exception\PropelException;
use Propel\Runtime\Map\TableMap;
use Propel\Runtime\Parser\AbstractParser;

/**
 * Base class that represents a row from the 'risk_scoring' table.
 *
 *
 *
* @package    propel.generator..Base
*/
abstract class RiskScoring implements ActiveRecordInterface
{
    /**
     * TableMap class name
     */
    const TABLE_MAP = '\\Map\\RiskScoringTableMap';


    /**
     * attribute to determine if this object has previously been saved.
     * @var boolean
     */
    protected $new = true;

    /**
     * attribute to determine whether this object has been deleted.
     * @var boolean
     */
    protected $deleted = false;

    /**
     * The columns that have been modified in current object.
     * Tracking modified columns allows us to only update modified columns.
     * @var array
     */
    protected $modifiedColumns = array();

    /**
     * The (virtual) columns that are added at runtime
     * The formatters can add supplementary columns based on a resultset
     * @var array
     */
    protected $virtualColumns = array();

    /**
     * The value for the id field.
     * @var        int
     */
    protected $id;

    /**
     * The value for the scoring_method field.
     * @var        int
     */
    protected $scoring_method;

    /**
     * The value for the calculated_risk field.
     * @var        double
     */
    protected $calculated_risk;

    /**
     * The value for the classic_likelihood field.
     * Note: this column has a database default value of: 5
     * @var        double
     */
    protected $classic_likelihood;

    /**
     * The value for the classic_impact field.
     * Note: this column has a database default value of: 5
     * @var        double
     */
    protected $classic_impact;

    /**
     * The value for the cvss_accessvector field.
     * Note: this column has a database default value of: 'N'
     * @var        string
     */
    protected $cvss_accessvector;

    /**
     * The value for the cvss_accesscomplexity field.
     * Note: this column has a database default value of: 'L'
     * @var        string
     */
    protected $cvss_accesscomplexity;

    /**
     * The value for the cvss_authentication field.
     * Note: this column has a database default value of: 'N'
     * @var        string
     */
    protected $cvss_authentication;

    /**
     * The value for the cvss_confimpact field.
     * Note: this column has a database default value of: 'C'
     * @var        string
     */
    protected $cvss_confimpact;

    /**
     * The value for the cvss_integimpact field.
     * Note: this column has a database default value of: 'C'
     * @var        string
     */
    protected $cvss_integimpact;

    /**
     * The value for the cvss_availimpact field.
     * Note: this column has a database default value of: 'C'
     * @var        string
     */
    protected $cvss_availimpact;

    /**
     * The value for the cvss_exploitability field.
     * Note: this column has a database default value of: 'ND'
     * @var        string
     */
    protected $cvss_exploitability;

    /**
     * The value for the cvss_remediationlevel field.
     * Note: this column has a database default value of: 'ND'
     * @var        string
     */
    protected $cvss_remediationlevel;

    /**
     * The value for the cvss_reportconfidence field.
     * Note: this column has a database default value of: 'ND'
     * @var        string
     */
    protected $cvss_reportconfidence;

    /**
     * The value for the cvss_collateraldamagepotential field.
     * Note: this column has a database default value of: 'ND'
     * @var        string
     */
    protected $cvss_collateraldamagepotential;

    /**
     * The value for the cvss_targetdistribution field.
     * Note: this column has a database default value of: 'ND'
     * @var        string
     */
    protected $cvss_targetdistribution;

    /**
     * The value for the cvss_confidentialityrequirement field.
     * Note: this column has a database default value of: 'ND'
     * @var        string
     */
    protected $cvss_confidentialityrequirement;

    /**
     * The value for the cvss_integrityrequirement field.
     * Note: this column has a database default value of: 'ND'
     * @var        string
     */
    protected $cvss_integrityrequirement;

    /**
     * The value for the cvss_availabilityrequirement field.
     * Note: this column has a database default value of: 'ND'
     * @var        string
     */
    protected $cvss_availabilityrequirement;

    /**
     * The value for the dread_damagepotential field.
     * Note: this column has a database default value of: 10
     * @var        int
     */
    protected $dread_damagepotential;

    /**
     * The value for the dread_reproducibility field.
     * Note: this column has a database default value of: 10
     * @var        int
     */
    protected $dread_reproducibility;

    /**
     * The value for the dread_exploitability field.
     * Note: this column has a database default value of: 10
     * @var        int
     */
    protected $dread_exploitability;

    /**
     * The value for the dread_affectedusers field.
     * Note: this column has a database default value of: 10
     * @var        int
     */
    protected $dread_affectedusers;

    /**
     * The value for the dread_discoverability field.
     * Note: this column has a database default value of: 10
     * @var        int
     */
    protected $dread_discoverability;

    /**
     * The value for the owasp_skilllevel field.
     * Note: this column has a database default value of: 10
     * @var        int
     */
    protected $owasp_skilllevel;

    /**
     * The value for the owasp_motive field.
     * Note: this column has a database default value of: 10
     * @var        int
     */
    protected $owasp_motive;

    /**
     * The value for the owasp_opportunity field.
     * Note: this column has a database default value of: 10
     * @var        int
     */
    protected $owasp_opportunity;

    /**
     * The value for the owasp_size field.
     * Note: this column has a database default value of: 10
     * @var        int
     */
    protected $owasp_size;

    /**
     * The value for the owasp_easeofdiscovery field.
     * Note: this column has a database default value of: 10
     * @var        int
     */
    protected $owasp_easeofdiscovery;

    /**
     * The value for the owasp_easeofexploit field.
     * Note: this column has a database default value of: 10
     * @var        int
     */
    protected $owasp_easeofexploit;

    /**
     * The value for the owasp_awareness field.
     * Note: this column has a database default value of: 10
     * @var        int
     */
    protected $owasp_awareness;

    /**
     * The value for the owasp_intrusiondetection field.
     * Note: this column has a database default value of: 10
     * @var        int
     */
    protected $owasp_intrusiondetection;

    /**
     * The value for the owasp_lossofconfidentiality field.
     * Note: this column has a database default value of: 10
     * @var        int
     */
    protected $owasp_lossofconfidentiality;

    /**
     * The value for the owasp_lossofintegrity field.
     * Note: this column has a database default value of: 10
     * @var        int
     */
    protected $owasp_lossofintegrity;

    /**
     * The value for the owasp_lossofavailability field.
     * Note: this column has a database default value of: 10
     * @var        int
     */
    protected $owasp_lossofavailability;

    /**
     * The value for the owasp_lossofaccountability field.
     * Note: this column has a database default value of: 10
     * @var        int
     */
    protected $owasp_lossofaccountability;

    /**
     * The value for the owasp_financialdamage field.
     * Note: this column has a database default value of: 10
     * @var        int
     */
    protected $owasp_financialdamage;

    /**
     * The value for the owasp_reputationdamage field.
     * Note: this column has a database default value of: 10
     * @var        int
     */
    protected $owasp_reputationdamage;

    /**
     * The value for the owasp_noncompliance field.
     * Note: this column has a database default value of: 10
     * @var        int
     */
    protected $owasp_noncompliance;

    /**
     * The value for the owasp_privacyviolation field.
     * Note: this column has a database default value of: 10
     * @var        int
     */
    protected $owasp_privacyviolation;

    /**
     * The value for the custom field.
     * Note: this column has a database default value of: 10
     * @var        double
     */
    protected $custom;

    /**
     * Flag to prevent endless save loop, if this object is referenced
     * by another object which falls in this transaction.
     *
     * @var boolean
     */
    protected $alreadyInSave = false;

    /**
     * Applies default values to this object.
     * This method should be called from the object's constructor (or
     * equivalent initialization method).
     * @see __construct()
     */
    public function applyDefaultValues()
    {
        $this->classic_likelihood = 5;
        $this->classic_impact = 5;
        $this->cvss_accessvector = 'N';
        $this->cvss_accesscomplexity = 'L';
        $this->cvss_authentication = 'N';
        $this->cvss_confimpact = 'C';
        $this->cvss_integimpact = 'C';
        $this->cvss_availimpact = 'C';
        $this->cvss_exploitability = 'ND';
        $this->cvss_remediationlevel = 'ND';
        $this->cvss_reportconfidence = 'ND';
        $this->cvss_collateraldamagepotential = 'ND';
        $this->cvss_targetdistribution = 'ND';
        $this->cvss_confidentialityrequirement = 'ND';
        $this->cvss_integrityrequirement = 'ND';
        $this->cvss_availabilityrequirement = 'ND';
        $this->dread_damagepotential = 10;
        $this->dread_reproducibility = 10;
        $this->dread_exploitability = 10;
        $this->dread_affectedusers = 10;
        $this->dread_discoverability = 10;
        $this->owasp_skilllevel = 10;
        $this->owasp_motive = 10;
        $this->owasp_opportunity = 10;
        $this->owasp_size = 10;
        $this->owasp_easeofdiscovery = 10;
        $this->owasp_easeofexploit = 10;
        $this->owasp_awareness = 10;
        $this->owasp_intrusiondetection = 10;
        $this->owasp_lossofconfidentiality = 10;
        $this->owasp_lossofintegrity = 10;
        $this->owasp_lossofavailability = 10;
        $this->owasp_lossofaccountability = 10;
        $this->owasp_financialdamage = 10;
        $this->owasp_reputationdamage = 10;
        $this->owasp_noncompliance = 10;
        $this->owasp_privacyviolation = 10;
        $this->custom = 10;
    }

    /**
     * Initializes internal state of Base\RiskScoring object.
     * @see applyDefaults()
     */
    public function __construct()
    {
        $this->applyDefaultValues();
    }

    /**
     * Returns whether the object has been modified.
     *
     * @return boolean True if the object has been modified.
     */
    public function isModified()
    {
        return !!$this->modifiedColumns;
    }

    /**
     * Has specified column been modified?
     *
     * @param  string  $col column fully qualified name (TableMap::TYPE_COLNAME), e.g. Book::AUTHOR_ID
     * @return boolean True if $col has been modified.
     */
    public function isColumnModified($col)
    {
        return $this->modifiedColumns && isset($this->modifiedColumns[$col]);
    }

    /**
     * Get the columns that have been modified in this object.
     * @return array A unique list of the modified column names for this object.
     */
    public function getModifiedColumns()
    {
        return $this->modifiedColumns ? array_keys($this->modifiedColumns) : [];
    }

    /**
     * Returns whether the object has ever been saved.  This will
     * be false, if the object was retrieved from storage or was created
     * and then saved.
     *
     * @return boolean true, if the object has never been persisted.
     */
    public function isNew()
    {
        return $this->new;
    }

    /**
     * Setter for the isNew attribute.  This method will be called
     * by Propel-generated children and objects.
     *
     * @param boolean $b the state of the object.
     */
    public function setNew($b)
    {
        $this->new = (boolean) $b;
    }

    /**
     * Whether this object has been deleted.
     * @return boolean The deleted state of this object.
     */
    public function isDeleted()
    {
        return $this->deleted;
    }

    /**
     * Specify whether this object has been deleted.
     * @param  boolean $b The deleted state of this object.
     * @return void
     */
    public function setDeleted($b)
    {
        $this->deleted = (boolean) $b;
    }

    /**
     * Sets the modified state for the object to be false.
     * @param  string $col If supplied, only the specified column is reset.
     * @return void
     */
    public function resetModified($col = null)
    {
        if (null !== $col) {
            if (isset($this->modifiedColumns[$col])) {
                unset($this->modifiedColumns[$col]);
            }
        } else {
            $this->modifiedColumns = array();
        }
    }

    /**
     * Compares this with another <code>RiskScoring</code> instance.  If
     * <code>obj</code> is an instance of <code>RiskScoring</code>, delegates to
     * <code>equals(RiskScoring)</code>.  Otherwise, returns <code>false</code>.
     *
     * @param  mixed   $obj The object to compare to.
     * @return boolean Whether equal to the object specified.
     */
    public function equals($obj)
    {
        if (!$obj instanceof static) {
            return false;
        }

        if ($this === $obj) {
            return true;
        }

        if (null === $this->getPrimaryKey() || null === $obj->getPrimaryKey()) {
            return false;
        }

        return $this->getPrimaryKey() === $obj->getPrimaryKey();
    }

    /**
     * Get the associative array of the virtual columns in this object
     *
     * @return array
     */
    public function getVirtualColumns()
    {
        return $this->virtualColumns;
    }

    /**
     * Checks the existence of a virtual column in this object
     *
     * @param  string  $name The virtual column name
     * @return boolean
     */
    public function hasVirtualColumn($name)
    {
        return array_key_exists($name, $this->virtualColumns);
    }

    /**
     * Get the value of a virtual column in this object
     *
     * @param  string $name The virtual column name
     * @return mixed
     *
     * @throws PropelException
     */
    public function getVirtualColumn($name)
    {
        if (!$this->hasVirtualColumn($name)) {
            throw new PropelException(sprintf('Cannot get value of inexistent virtual column %s.', $name));
        }

        return $this->virtualColumns[$name];
    }

    /**
     * Set the value of a virtual column in this object
     *
     * @param string $name  The virtual column name
     * @param mixed  $value The value to give to the virtual column
     *
     * @return $this|RiskScoring The current object, for fluid interface
     */
    public function setVirtualColumn($name, $value)
    {
        $this->virtualColumns[$name] = $value;

        return $this;
    }

    /**
     * Logs a message using Propel::log().
     *
     * @param  string  $msg
     * @param  int     $priority One of the Propel::LOG_* logging levels
     * @return boolean
     */
    protected function log($msg, $priority = Propel::LOG_INFO)
    {
        return Propel::log(get_class($this) . ': ' . $msg, $priority);
    }

    /**
     * Export the current object properties to a string, using a given parser format
     * <code>
     * $book = BookQuery::create()->findPk(9012);
     * echo $book->exportTo('JSON');
     *  => {"Id":9012,"Title":"Don Juan","ISBN":"0140422161","Price":12.99,"PublisherId":1234,"AuthorId":5678}');
     * </code>
     *
     * @param  mixed   $parser                 A AbstractParser instance, or a format name ('XML', 'YAML', 'JSON', 'CSV')
     * @param  boolean $includeLazyLoadColumns (optional) Whether to include lazy load(ed) columns. Defaults to TRUE.
     * @return string  The exported data
     */
    public function exportTo($parser, $includeLazyLoadColumns = true)
    {
        if (!$parser instanceof AbstractParser) {
            $parser = AbstractParser::getParser($parser);
        }

        return $parser->fromArray($this->toArray(TableMap::TYPE_PHPNAME, $includeLazyLoadColumns, array(), true));
    }

    /**
     * Clean up internal collections prior to serializing
     * Avoids recursive loops that turn into segmentation faults when serializing
     */
    public function __sleep()
    {
        $this->clearAllReferences();

        return array_keys(get_object_vars($this));
    }

    /**
     * Get the [id] column value.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Get the [scoring_method] column value.
     *
     * @return int
     */
    public function getScoringMethod()
    {
        return $this->scoring_method;
    }

    /**
     * Get the [calculated_risk] column value.
     *
     * @return double
     */
    public function getCalculatedRisk()
    {
        return $this->calculated_risk;
    }

    /**
     * Get the [classic_likelihood] column value.
     *
     * @return double
     */
    public function getClassicLikelihood()
    {
        return $this->classic_likelihood;
    }

    /**
     * Get the [classic_impact] column value.
     *
     * @return double
     */
    public function getClassicImpact()
    {
        return $this->classic_impact;
    }

    /**
     * Get the [cvss_accessvector] column value.
     *
     * @return string
     */
    public function getCvssAccessvector()
    {
        return $this->cvss_accessvector;
    }

    /**
     * Get the [cvss_accesscomplexity] column value.
     *
     * @return string
     */
    public function getCvssAccesscomplexity()
    {
        return $this->cvss_accesscomplexity;
    }

    /**
     * Get the [cvss_authentication] column value.
     *
     * @return string
     */
    public function getCvssAuthentication()
    {
        return $this->cvss_authentication;
    }

    /**
     * Get the [cvss_confimpact] column value.
     *
     * @return string
     */
    public function getCvssConfimpact()
    {
        return $this->cvss_confimpact;
    }

    /**
     * Get the [cvss_integimpact] column value.
     *
     * @return string
     */
    public function getCvssIntegimpact()
    {
        return $this->cvss_integimpact;
    }

    /**
     * Get the [cvss_availimpact] column value.
     *
     * @return string
     */
    public function getCvssAvailimpact()
    {
        return $this->cvss_availimpact;
    }

    /**
     * Get the [cvss_exploitability] column value.
     *
     * @return string
     */
    public function getCvssExploitability()
    {
        return $this->cvss_exploitability;
    }

    /**
     * Get the [cvss_remediationlevel] column value.
     *
     * @return string
     */
    public function getCvssRemediationlevel()
    {
        return $this->cvss_remediationlevel;
    }

    /**
     * Get the [cvss_reportconfidence] column value.
     *
     * @return string
     */
    public function getCvssReportconfidence()
    {
        return $this->cvss_reportconfidence;
    }

    /**
     * Get the [cvss_collateraldamagepotential] column value.
     *
     * @return string
     */
    public function getCvssCollateraldamagepotential()
    {
        return $this->cvss_collateraldamagepotential;
    }

    /**
     * Get the [cvss_targetdistribution] column value.
     *
     * @return string
     */
    public function getCvssTargetdistribution()
    {
        return $this->cvss_targetdistribution;
    }

    /**
     * Get the [cvss_confidentialityrequirement] column value.
     *
     * @return string
     */
    public function getCvssConfidentialityrequirement()
    {
        return $this->cvss_confidentialityrequirement;
    }

    /**
     * Get the [cvss_integrityrequirement] column value.
     *
     * @return string
     */
    public function getCvssIntegrityrequirement()
    {
        return $this->cvss_integrityrequirement;
    }

    /**
     * Get the [cvss_availabilityrequirement] column value.
     *
     * @return string
     */
    public function getCvssAvailabilityrequirement()
    {
        return $this->cvss_availabilityrequirement;
    }

    /**
     * Get the [dread_damagepotential] column value.
     *
     * @return int
     */
    public function getDreadDamagepotential()
    {
        return $this->dread_damagepotential;
    }

    /**
     * Get the [dread_reproducibility] column value.
     *
     * @return int
     */
    public function getDreadReproducibility()
    {
        return $this->dread_reproducibility;
    }

    /**
     * Get the [dread_exploitability] column value.
     *
     * @return int
     */
    public function getDreadExploitability()
    {
        return $this->dread_exploitability;
    }

    /**
     * Get the [dread_affectedusers] column value.
     *
     * @return int
     */
    public function getDreadAffectedusers()
    {
        return $this->dread_affectedusers;
    }

    /**
     * Get the [dread_discoverability] column value.
     *
     * @return int
     */
    public function getDreadDiscoverability()
    {
        return $this->dread_discoverability;
    }

    /**
     * Get the [owasp_skilllevel] column value.
     *
     * @return int
     */
    public function getOwaspSkilllevel()
    {
        return $this->owasp_skilllevel;
    }

    /**
     * Get the [owasp_motive] column value.
     *
     * @return int
     */
    public function getOwaspMotive()
    {
        return $this->owasp_motive;
    }

    /**
     * Get the [owasp_opportunity] column value.
     *
     * @return int
     */
    public function getOwaspOpportunity()
    {
        return $this->owasp_opportunity;
    }

    /**
     * Get the [owasp_size] column value.
     *
     * @return int
     */
    public function getOwaspSize()
    {
        return $this->owasp_size;
    }

    /**
     * Get the [owasp_easeofdiscovery] column value.
     *
     * @return int
     */
    public function getOwaspEaseofdiscovery()
    {
        return $this->owasp_easeofdiscovery;
    }

    /**
     * Get the [owasp_easeofexploit] column value.
     *
     * @return int
     */
    public function getOwaspEaseofexploit()
    {
        return $this->owasp_easeofexploit;
    }

    /**
     * Get the [owasp_awareness] column value.
     *
     * @return int
     */
    public function getOwaspAwareness()
    {
        return $this->owasp_awareness;
    }

    /**
     * Get the [owasp_intrusiondetection] column value.
     *
     * @return int
     */
    public function getOwaspIntrusiondetection()
    {
        return $this->owasp_intrusiondetection;
    }

    /**
     * Get the [owasp_lossofconfidentiality] column value.
     *
     * @return int
     */
    public function getOwaspLossofconfidentiality()
    {
        return $this->owasp_lossofconfidentiality;
    }

    /**
     * Get the [owasp_lossofintegrity] column value.
     *
     * @return int
     */
    public function getOwaspLossofintegrity()
    {
        return $this->owasp_lossofintegrity;
    }

    /**
     * Get the [owasp_lossofavailability] column value.
     *
     * @return int
     */
    public function getOwaspLossofavailability()
    {
        return $this->owasp_lossofavailability;
    }

    /**
     * Get the [owasp_lossofaccountability] column value.
     *
     * @return int
     */
    public function getOwaspLossofaccountability()
    {
        return $this->owasp_lossofaccountability;
    }

    /**
     * Get the [owasp_financialdamage] column value.
     *
     * @return int
     */
    public function getOwaspFinancialdamage()
    {
        return $this->owasp_financialdamage;
    }

    /**
     * Get the [owasp_reputationdamage] column value.
     *
     * @return int
     */
    public function getOwaspReputationdamage()
    {
        return $this->owasp_reputationdamage;
    }

    /**
     * Get the [owasp_noncompliance] column value.
     *
     * @return int
     */
    public function getOwaspNoncompliance()
    {
        return $this->owasp_noncompliance;
    }

    /**
     * Get the [owasp_privacyviolation] column value.
     *
     * @return int
     */
    public function getOwaspPrivacyviolation()
    {
        return $this->owasp_privacyviolation;
    }

    /**
     * Get the [custom] column value.
     *
     * @return double
     */
    public function getCustom()
    {
        return $this->custom;
    }

    /**
     * Set the value of [id] column.
     *
     * @param  int $v new value
     * @return $this|\RiskScoring The current object (for fluent API support)
     */
    public function setId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->id !== $v) {
            $this->id = $v;
            $this->modifiedColumns[RiskScoringTableMap::COL_ID] = true;
        }

        return $this;
    } // setId()

    /**
     * Set the value of [scoring_method] column.
     *
     * @param  int $v new value
     * @return $this|\RiskScoring The current object (for fluent API support)
     */
    public function setScoringMethod($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->scoring_method !== $v) {
            $this->scoring_method = $v;
            $this->modifiedColumns[RiskScoringTableMap::COL_SCORING_METHOD] = true;
        }

        return $this;
    } // setScoringMethod()

    /**
     * Set the value of [calculated_risk] column.
     *
     * @param  double $v new value
     * @return $this|\RiskScoring The current object (for fluent API support)
     */
    public function setCalculatedRisk($v)
    {
        if ($v !== null) {
            $v = (double) $v;
        }

        if ($this->calculated_risk !== $v) {
            $this->calculated_risk = $v;
            $this->modifiedColumns[RiskScoringTableMap::COL_CALCULATED_RISK] = true;
        }

        return $this;
    } // setCalculatedRisk()

    /**
     * Set the value of [classic_likelihood] column.
     *
     * @param  double $v new value
     * @return $this|\RiskScoring The current object (for fluent API support)
     */
    public function setClassicLikelihood($v)
    {
        if ($v !== null) {
            $v = (double) $v;
        }

        if ($this->classic_likelihood !== $v) {
            $this->classic_likelihood = $v;
            $this->modifiedColumns[RiskScoringTableMap::COL_CLASSIC_LIKELIHOOD] = true;
        }

        return $this;
    } // setClassicLikelihood()

    /**
     * Set the value of [classic_impact] column.
     *
     * @param  double $v new value
     * @return $this|\RiskScoring The current object (for fluent API support)
     */
    public function setClassicImpact($v)
    {
        if ($v !== null) {
            $v = (double) $v;
        }

        if ($this->classic_impact !== $v) {
            $this->classic_impact = $v;
            $this->modifiedColumns[RiskScoringTableMap::COL_CLASSIC_IMPACT] = true;
        }

        return $this;
    } // setClassicImpact()

    /**
     * Set the value of [cvss_accessvector] column.
     *
     * @param  string $v new value
     * @return $this|\RiskScoring The current object (for fluent API support)
     */
    public function setCvssAccessvector($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->cvss_accessvector !== $v) {
            $this->cvss_accessvector = $v;
            $this->modifiedColumns[RiskScoringTableMap::COL_CVSS_ACCESSVECTOR] = true;
        }

        return $this;
    } // setCvssAccessvector()

    /**
     * Set the value of [cvss_accesscomplexity] column.
     *
     * @param  string $v new value
     * @return $this|\RiskScoring The current object (for fluent API support)
     */
    public function setCvssAccesscomplexity($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->cvss_accesscomplexity !== $v) {
            $this->cvss_accesscomplexity = $v;
            $this->modifiedColumns[RiskScoringTableMap::COL_CVSS_ACCESSCOMPLEXITY] = true;
        }

        return $this;
    } // setCvssAccesscomplexity()

    /**
     * Set the value of [cvss_authentication] column.
     *
     * @param  string $v new value
     * @return $this|\RiskScoring The current object (for fluent API support)
     */
    public function setCvssAuthentication($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->cvss_authentication !== $v) {
            $this->cvss_authentication = $v;
            $this->modifiedColumns[RiskScoringTableMap::COL_CVSS_AUTHENTICATION] = true;
        }

        return $this;
    } // setCvssAuthentication()

    /**
     * Set the value of [cvss_confimpact] column.
     *
     * @param  string $v new value
     * @return $this|\RiskScoring The current object (for fluent API support)
     */
    public function setCvssConfimpact($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->cvss_confimpact !== $v) {
            $this->cvss_confimpact = $v;
            $this->modifiedColumns[RiskScoringTableMap::COL_CVSS_CONFIMPACT] = true;
        }

        return $this;
    } // setCvssConfimpact()

    /**
     * Set the value of [cvss_integimpact] column.
     *
     * @param  string $v new value
     * @return $this|\RiskScoring The current object (for fluent API support)
     */
    public function setCvssIntegimpact($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->cvss_integimpact !== $v) {
            $this->cvss_integimpact = $v;
            $this->modifiedColumns[RiskScoringTableMap::COL_CVSS_INTEGIMPACT] = true;
        }

        return $this;
    } // setCvssIntegimpact()

    /**
     * Set the value of [cvss_availimpact] column.
     *
     * @param  string $v new value
     * @return $this|\RiskScoring The current object (for fluent API support)
     */
    public function setCvssAvailimpact($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->cvss_availimpact !== $v) {
            $this->cvss_availimpact = $v;
            $this->modifiedColumns[RiskScoringTableMap::COL_CVSS_AVAILIMPACT] = true;
        }

        return $this;
    } // setCvssAvailimpact()

    /**
     * Set the value of [cvss_exploitability] column.
     *
     * @param  string $v new value
     * @return $this|\RiskScoring The current object (for fluent API support)
     */
    public function setCvssExploitability($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->cvss_exploitability !== $v) {
            $this->cvss_exploitability = $v;
            $this->modifiedColumns[RiskScoringTableMap::COL_CVSS_EXPLOITABILITY] = true;
        }

        return $this;
    } // setCvssExploitability()

    /**
     * Set the value of [cvss_remediationlevel] column.
     *
     * @param  string $v new value
     * @return $this|\RiskScoring The current object (for fluent API support)
     */
    public function setCvssRemediationlevel($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->cvss_remediationlevel !== $v) {
            $this->cvss_remediationlevel = $v;
            $this->modifiedColumns[RiskScoringTableMap::COL_CVSS_REMEDIATIONLEVEL] = true;
        }

        return $this;
    } // setCvssRemediationlevel()

    /**
     * Set the value of [cvss_reportconfidence] column.
     *
     * @param  string $v new value
     * @return $this|\RiskScoring The current object (for fluent API support)
     */
    public function setCvssReportconfidence($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->cvss_reportconfidence !== $v) {
            $this->cvss_reportconfidence = $v;
            $this->modifiedColumns[RiskScoringTableMap::COL_CVSS_REPORTCONFIDENCE] = true;
        }

        return $this;
    } // setCvssReportconfidence()

    /**
     * Set the value of [cvss_collateraldamagepotential] column.
     *
     * @param  string $v new value
     * @return $this|\RiskScoring The current object (for fluent API support)
     */
    public function setCvssCollateraldamagepotential($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->cvss_collateraldamagepotential !== $v) {
            $this->cvss_collateraldamagepotential = $v;
            $this->modifiedColumns[RiskScoringTableMap::COL_CVSS_COLLATERALDAMAGEPOTENTIAL] = true;
        }

        return $this;
    } // setCvssCollateraldamagepotential()

    /**
     * Set the value of [cvss_targetdistribution] column.
     *
     * @param  string $v new value
     * @return $this|\RiskScoring The current object (for fluent API support)
     */
    public function setCvssTargetdistribution($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->cvss_targetdistribution !== $v) {
            $this->cvss_targetdistribution = $v;
            $this->modifiedColumns[RiskScoringTableMap::COL_CVSS_TARGETDISTRIBUTION] = true;
        }

        return $this;
    } // setCvssTargetdistribution()

    /**
     * Set the value of [cvss_confidentialityrequirement] column.
     *
     * @param  string $v new value
     * @return $this|\RiskScoring The current object (for fluent API support)
     */
    public function setCvssConfidentialityrequirement($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->cvss_confidentialityrequirement !== $v) {
            $this->cvss_confidentialityrequirement = $v;
            $this->modifiedColumns[RiskScoringTableMap::COL_CVSS_CONFIDENTIALITYREQUIREMENT] = true;
        }

        return $this;
    } // setCvssConfidentialityrequirement()

    /**
     * Set the value of [cvss_integrityrequirement] column.
     *
     * @param  string $v new value
     * @return $this|\RiskScoring The current object (for fluent API support)
     */
    public function setCvssIntegrityrequirement($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->cvss_integrityrequirement !== $v) {
            $this->cvss_integrityrequirement = $v;
            $this->modifiedColumns[RiskScoringTableMap::COL_CVSS_INTEGRITYREQUIREMENT] = true;
        }

        return $this;
    } // setCvssIntegrityrequirement()

    /**
     * Set the value of [cvss_availabilityrequirement] column.
     *
     * @param  string $v new value
     * @return $this|\RiskScoring The current object (for fluent API support)
     */
    public function setCvssAvailabilityrequirement($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->cvss_availabilityrequirement !== $v) {
            $this->cvss_availabilityrequirement = $v;
            $this->modifiedColumns[RiskScoringTableMap::COL_CVSS_AVAILABILITYREQUIREMENT] = true;
        }

        return $this;
    } // setCvssAvailabilityrequirement()

    /**
     * Set the value of [dread_damagepotential] column.
     *
     * @param  int $v new value
     * @return $this|\RiskScoring The current object (for fluent API support)
     */
    public function setDreadDamagepotential($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->dread_damagepotential !== $v) {
            $this->dread_damagepotential = $v;
            $this->modifiedColumns[RiskScoringTableMap::COL_DREAD_DAMAGEPOTENTIAL] = true;
        }

        return $this;
    } // setDreadDamagepotential()

    /**
     * Set the value of [dread_reproducibility] column.
     *
     * @param  int $v new value
     * @return $this|\RiskScoring The current object (for fluent API support)
     */
    public function setDreadReproducibility($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->dread_reproducibility !== $v) {
            $this->dread_reproducibility = $v;
            $this->modifiedColumns[RiskScoringTableMap::COL_DREAD_REPRODUCIBILITY] = true;
        }

        return $this;
    } // setDreadReproducibility()

    /**
     * Set the value of [dread_exploitability] column.
     *
     * @param  int $v new value
     * @return $this|\RiskScoring The current object (for fluent API support)
     */
    public function setDreadExploitability($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->dread_exploitability !== $v) {
            $this->dread_exploitability = $v;
            $this->modifiedColumns[RiskScoringTableMap::COL_DREAD_EXPLOITABILITY] = true;
        }

        return $this;
    } // setDreadExploitability()

    /**
     * Set the value of [dread_affectedusers] column.
     *
     * @param  int $v new value
     * @return $this|\RiskScoring The current object (for fluent API support)
     */
    public function setDreadAffectedusers($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->dread_affectedusers !== $v) {
            $this->dread_affectedusers = $v;
            $this->modifiedColumns[RiskScoringTableMap::COL_DREAD_AFFECTEDUSERS] = true;
        }

        return $this;
    } // setDreadAffectedusers()

    /**
     * Set the value of [dread_discoverability] column.
     *
     * @param  int $v new value
     * @return $this|\RiskScoring The current object (for fluent API support)
     */
    public function setDreadDiscoverability($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->dread_discoverability !== $v) {
            $this->dread_discoverability = $v;
            $this->modifiedColumns[RiskScoringTableMap::COL_DREAD_DISCOVERABILITY] = true;
        }

        return $this;
    } // setDreadDiscoverability()

    /**
     * Set the value of [owasp_skilllevel] column.
     *
     * @param  int $v new value
     * @return $this|\RiskScoring The current object (for fluent API support)
     */
    public function setOwaspSkilllevel($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->owasp_skilllevel !== $v) {
            $this->owasp_skilllevel = $v;
            $this->modifiedColumns[RiskScoringTableMap::COL_OWASP_SKILLLEVEL] = true;
        }

        return $this;
    } // setOwaspSkilllevel()

    /**
     * Set the value of [owasp_motive] column.
     *
     * @param  int $v new value
     * @return $this|\RiskScoring The current object (for fluent API support)
     */
    public function setOwaspMotive($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->owasp_motive !== $v) {
            $this->owasp_motive = $v;
            $this->modifiedColumns[RiskScoringTableMap::COL_OWASP_MOTIVE] = true;
        }

        return $this;
    } // setOwaspMotive()

    /**
     * Set the value of [owasp_opportunity] column.
     *
     * @param  int $v new value
     * @return $this|\RiskScoring The current object (for fluent API support)
     */
    public function setOwaspOpportunity($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->owasp_opportunity !== $v) {
            $this->owasp_opportunity = $v;
            $this->modifiedColumns[RiskScoringTableMap::COL_OWASP_OPPORTUNITY] = true;
        }

        return $this;
    } // setOwaspOpportunity()

    /**
     * Set the value of [owasp_size] column.
     *
     * @param  int $v new value
     * @return $this|\RiskScoring The current object (for fluent API support)
     */
    public function setOwaspSize($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->owasp_size !== $v) {
            $this->owasp_size = $v;
            $this->modifiedColumns[RiskScoringTableMap::COL_OWASP_SIZE] = true;
        }

        return $this;
    } // setOwaspSize()

    /**
     * Set the value of [owasp_easeofdiscovery] column.
     *
     * @param  int $v new value
     * @return $this|\RiskScoring The current object (for fluent API support)
     */
    public function setOwaspEaseofdiscovery($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->owasp_easeofdiscovery !== $v) {
            $this->owasp_easeofdiscovery = $v;
            $this->modifiedColumns[RiskScoringTableMap::COL_OWASP_EASEOFDISCOVERY] = true;
        }

        return $this;
    } // setOwaspEaseofdiscovery()

    /**
     * Set the value of [owasp_easeofexploit] column.
     *
     * @param  int $v new value
     * @return $this|\RiskScoring The current object (for fluent API support)
     */
    public function setOwaspEaseofexploit($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->owasp_easeofexploit !== $v) {
            $this->owasp_easeofexploit = $v;
            $this->modifiedColumns[RiskScoringTableMap::COL_OWASP_EASEOFEXPLOIT] = true;
        }

        return $this;
    } // setOwaspEaseofexploit()

    /**
     * Set the value of [owasp_awareness] column.
     *
     * @param  int $v new value
     * @return $this|\RiskScoring The current object (for fluent API support)
     */
    public function setOwaspAwareness($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->owasp_awareness !== $v) {
            $this->owasp_awareness = $v;
            $this->modifiedColumns[RiskScoringTableMap::COL_OWASP_AWARENESS] = true;
        }

        return $this;
    } // setOwaspAwareness()

    /**
     * Set the value of [owasp_intrusiondetection] column.
     *
     * @param  int $v new value
     * @return $this|\RiskScoring The current object (for fluent API support)
     */
    public function setOwaspIntrusiondetection($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->owasp_intrusiondetection !== $v) {
            $this->owasp_intrusiondetection = $v;
            $this->modifiedColumns[RiskScoringTableMap::COL_OWASP_INTRUSIONDETECTION] = true;
        }

        return $this;
    } // setOwaspIntrusiondetection()

    /**
     * Set the value of [owasp_lossofconfidentiality] column.
     *
     * @param  int $v new value
     * @return $this|\RiskScoring The current object (for fluent API support)
     */
    public function setOwaspLossofconfidentiality($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->owasp_lossofconfidentiality !== $v) {
            $this->owasp_lossofconfidentiality = $v;
            $this->modifiedColumns[RiskScoringTableMap::COL_OWASP_LOSSOFCONFIDENTIALITY] = true;
        }

        return $this;
    } // setOwaspLossofconfidentiality()

    /**
     * Set the value of [owasp_lossofintegrity] column.
     *
     * @param  int $v new value
     * @return $this|\RiskScoring The current object (for fluent API support)
     */
    public function setOwaspLossofintegrity($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->owasp_lossofintegrity !== $v) {
            $this->owasp_lossofintegrity = $v;
            $this->modifiedColumns[RiskScoringTableMap::COL_OWASP_LOSSOFINTEGRITY] = true;
        }

        return $this;
    } // setOwaspLossofintegrity()

    /**
     * Set the value of [owasp_lossofavailability] column.
     *
     * @param  int $v new value
     * @return $this|\RiskScoring The current object (for fluent API support)
     */
    public function setOwaspLossofavailability($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->owasp_lossofavailability !== $v) {
            $this->owasp_lossofavailability = $v;
            $this->modifiedColumns[RiskScoringTableMap::COL_OWASP_LOSSOFAVAILABILITY] = true;
        }

        return $this;
    } // setOwaspLossofavailability()

    /**
     * Set the value of [owasp_lossofaccountability] column.
     *
     * @param  int $v new value
     * @return $this|\RiskScoring The current object (for fluent API support)
     */
    public function setOwaspLossofaccountability($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->owasp_lossofaccountability !== $v) {
            $this->owasp_lossofaccountability = $v;
            $this->modifiedColumns[RiskScoringTableMap::COL_OWASP_LOSSOFACCOUNTABILITY] = true;
        }

        return $this;
    } // setOwaspLossofaccountability()

    /**
     * Set the value of [owasp_financialdamage] column.
     *
     * @param  int $v new value
     * @return $this|\RiskScoring The current object (for fluent API support)
     */
    public function setOwaspFinancialdamage($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->owasp_financialdamage !== $v) {
            $this->owasp_financialdamage = $v;
            $this->modifiedColumns[RiskScoringTableMap::COL_OWASP_FINANCIALDAMAGE] = true;
        }

        return $this;
    } // setOwaspFinancialdamage()

    /**
     * Set the value of [owasp_reputationdamage] column.
     *
     * @param  int $v new value
     * @return $this|\RiskScoring The current object (for fluent API support)
     */
    public function setOwaspReputationdamage($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->owasp_reputationdamage !== $v) {
            $this->owasp_reputationdamage = $v;
            $this->modifiedColumns[RiskScoringTableMap::COL_OWASP_REPUTATIONDAMAGE] = true;
        }

        return $this;
    } // setOwaspReputationdamage()

    /**
     * Set the value of [owasp_noncompliance] column.
     *
     * @param  int $v new value
     * @return $this|\RiskScoring The current object (for fluent API support)
     */
    public function setOwaspNoncompliance($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->owasp_noncompliance !== $v) {
            $this->owasp_noncompliance = $v;
            $this->modifiedColumns[RiskScoringTableMap::COL_OWASP_NONCOMPLIANCE] = true;
        }

        return $this;
    } // setOwaspNoncompliance()

    /**
     * Set the value of [owasp_privacyviolation] column.
     *
     * @param  int $v new value
     * @return $this|\RiskScoring The current object (for fluent API support)
     */
    public function setOwaspPrivacyviolation($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->owasp_privacyviolation !== $v) {
            $this->owasp_privacyviolation = $v;
            $this->modifiedColumns[RiskScoringTableMap::COL_OWASP_PRIVACYVIOLATION] = true;
        }

        return $this;
    } // setOwaspPrivacyviolation()

    /**
     * Set the value of [custom] column.
     *
     * @param  double $v new value
     * @return $this|\RiskScoring The current object (for fluent API support)
     */
    public function setCustom($v)
    {
        if ($v !== null) {
            $v = (double) $v;
        }

        if ($this->custom !== $v) {
            $this->custom = $v;
            $this->modifiedColumns[RiskScoringTableMap::COL_CUSTOM] = true;
        }

        return $this;
    } // setCustom()

    /**
     * Indicates whether the columns in this object are only set to default values.
     *
     * This method can be used in conjunction with isModified() to indicate whether an object is both
     * modified _and_ has some values set which are non-default.
     *
     * @return boolean Whether the columns in this object are only been set with default values.
     */
    public function hasOnlyDefaultValues()
    {
            if ($this->classic_likelihood !== 5) {
                return false;
            }

            if ($this->classic_impact !== 5) {
                return false;
            }

            if ($this->cvss_accessvector !== 'N') {
                return false;
            }

            if ($this->cvss_accesscomplexity !== 'L') {
                return false;
            }

            if ($this->cvss_authentication !== 'N') {
                return false;
            }

            if ($this->cvss_confimpact !== 'C') {
                return false;
            }

            if ($this->cvss_integimpact !== 'C') {
                return false;
            }

            if ($this->cvss_availimpact !== 'C') {
                return false;
            }

            if ($this->cvss_exploitability !== 'ND') {
                return false;
            }

            if ($this->cvss_remediationlevel !== 'ND') {
                return false;
            }

            if ($this->cvss_reportconfidence !== 'ND') {
                return false;
            }

            if ($this->cvss_collateraldamagepotential !== 'ND') {
                return false;
            }

            if ($this->cvss_targetdistribution !== 'ND') {
                return false;
            }

            if ($this->cvss_confidentialityrequirement !== 'ND') {
                return false;
            }

            if ($this->cvss_integrityrequirement !== 'ND') {
                return false;
            }

            if ($this->cvss_availabilityrequirement !== 'ND') {
                return false;
            }

            if ($this->dread_damagepotential !== 10) {
                return false;
            }

            if ($this->dread_reproducibility !== 10) {
                return false;
            }

            if ($this->dread_exploitability !== 10) {
                return false;
            }

            if ($this->dread_affectedusers !== 10) {
                return false;
            }

            if ($this->dread_discoverability !== 10) {
                return false;
            }

            if ($this->owasp_skilllevel !== 10) {
                return false;
            }

            if ($this->owasp_motive !== 10) {
                return false;
            }

            if ($this->owasp_opportunity !== 10) {
                return false;
            }

            if ($this->owasp_size !== 10) {
                return false;
            }

            if ($this->owasp_easeofdiscovery !== 10) {
                return false;
            }

            if ($this->owasp_easeofexploit !== 10) {
                return false;
            }

            if ($this->owasp_awareness !== 10) {
                return false;
            }

            if ($this->owasp_intrusiondetection !== 10) {
                return false;
            }

            if ($this->owasp_lossofconfidentiality !== 10) {
                return false;
            }

            if ($this->owasp_lossofintegrity !== 10) {
                return false;
            }

            if ($this->owasp_lossofavailability !== 10) {
                return false;
            }

            if ($this->owasp_lossofaccountability !== 10) {
                return false;
            }

            if ($this->owasp_financialdamage !== 10) {
                return false;
            }

            if ($this->owasp_reputationdamage !== 10) {
                return false;
            }

            if ($this->owasp_noncompliance !== 10) {
                return false;
            }

            if ($this->owasp_privacyviolation !== 10) {
                return false;
            }

            if ($this->custom !== 10) {
                return false;
            }

        // otherwise, everything was equal, so return TRUE
        return true;
    } // hasOnlyDefaultValues()

    /**
     * Hydrates (populates) the object variables with values from the database resultset.
     *
     * An offset (0-based "start column") is specified so that objects can be hydrated
     * with a subset of the columns in the resultset rows.  This is needed, for example,
     * for results of JOIN queries where the resultset row includes columns from two or
     * more tables.
     *
     * @param array   $row       The row returned by DataFetcher->fetch().
     * @param int     $startcol  0-based offset column which indicates which restultset column to start with.
     * @param boolean $rehydrate Whether this object is being re-hydrated from the database.
     * @param string  $indexType The index type of $row. Mostly DataFetcher->getIndexType().
                                  One of the class type constants TableMap::TYPE_PHPNAME, TableMap::TYPE_CAMELNAME
     *                            TableMap::TYPE_COLNAME, TableMap::TYPE_FIELDNAME, TableMap::TYPE_NUM.
     *
     * @return int             next starting column
     * @throws PropelException - Any caught Exception will be rewrapped as a PropelException.
     */
    public function hydrate($row, $startcol = 0, $rehydrate = false, $indexType = TableMap::TYPE_NUM)
    {
        try {

            $col = $row[TableMap::TYPE_NUM == $indexType ? 0 + $startcol : RiskScoringTableMap::translateFieldName('Id', TableMap::TYPE_PHPNAME, $indexType)];
            $this->id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 1 + $startcol : RiskScoringTableMap::translateFieldName('ScoringMethod', TableMap::TYPE_PHPNAME, $indexType)];
            $this->scoring_method = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 2 + $startcol : RiskScoringTableMap::translateFieldName('CalculatedRisk', TableMap::TYPE_PHPNAME, $indexType)];
            $this->calculated_risk = (null !== $col) ? (double) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 3 + $startcol : RiskScoringTableMap::translateFieldName('ClassicLikelihood', TableMap::TYPE_PHPNAME, $indexType)];
            $this->classic_likelihood = (null !== $col) ? (double) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 4 + $startcol : RiskScoringTableMap::translateFieldName('ClassicImpact', TableMap::TYPE_PHPNAME, $indexType)];
            $this->classic_impact = (null !== $col) ? (double) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 5 + $startcol : RiskScoringTableMap::translateFieldName('CvssAccessvector', TableMap::TYPE_PHPNAME, $indexType)];
            $this->cvss_accessvector = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 6 + $startcol : RiskScoringTableMap::translateFieldName('CvssAccesscomplexity', TableMap::TYPE_PHPNAME, $indexType)];
            $this->cvss_accesscomplexity = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 7 + $startcol : RiskScoringTableMap::translateFieldName('CvssAuthentication', TableMap::TYPE_PHPNAME, $indexType)];
            $this->cvss_authentication = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 8 + $startcol : RiskScoringTableMap::translateFieldName('CvssConfimpact', TableMap::TYPE_PHPNAME, $indexType)];
            $this->cvss_confimpact = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 9 + $startcol : RiskScoringTableMap::translateFieldName('CvssIntegimpact', TableMap::TYPE_PHPNAME, $indexType)];
            $this->cvss_integimpact = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 10 + $startcol : RiskScoringTableMap::translateFieldName('CvssAvailimpact', TableMap::TYPE_PHPNAME, $indexType)];
            $this->cvss_availimpact = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 11 + $startcol : RiskScoringTableMap::translateFieldName('CvssExploitability', TableMap::TYPE_PHPNAME, $indexType)];
            $this->cvss_exploitability = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 12 + $startcol : RiskScoringTableMap::translateFieldName('CvssRemediationlevel', TableMap::TYPE_PHPNAME, $indexType)];
            $this->cvss_remediationlevel = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 13 + $startcol : RiskScoringTableMap::translateFieldName('CvssReportconfidence', TableMap::TYPE_PHPNAME, $indexType)];
            $this->cvss_reportconfidence = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 14 + $startcol : RiskScoringTableMap::translateFieldName('CvssCollateraldamagepotential', TableMap::TYPE_PHPNAME, $indexType)];
            $this->cvss_collateraldamagepotential = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 15 + $startcol : RiskScoringTableMap::translateFieldName('CvssTargetdistribution', TableMap::TYPE_PHPNAME, $indexType)];
            $this->cvss_targetdistribution = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 16 + $startcol : RiskScoringTableMap::translateFieldName('CvssConfidentialityrequirement', TableMap::TYPE_PHPNAME, $indexType)];
            $this->cvss_confidentialityrequirement = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 17 + $startcol : RiskScoringTableMap::translateFieldName('CvssIntegrityrequirement', TableMap::TYPE_PHPNAME, $indexType)];
            $this->cvss_integrityrequirement = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 18 + $startcol : RiskScoringTableMap::translateFieldName('CvssAvailabilityrequirement', TableMap::TYPE_PHPNAME, $indexType)];
            $this->cvss_availabilityrequirement = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 19 + $startcol : RiskScoringTableMap::translateFieldName('DreadDamagepotential', TableMap::TYPE_PHPNAME, $indexType)];
            $this->dread_damagepotential = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 20 + $startcol : RiskScoringTableMap::translateFieldName('DreadReproducibility', TableMap::TYPE_PHPNAME, $indexType)];
            $this->dread_reproducibility = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 21 + $startcol : RiskScoringTableMap::translateFieldName('DreadExploitability', TableMap::TYPE_PHPNAME, $indexType)];
            $this->dread_exploitability = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 22 + $startcol : RiskScoringTableMap::translateFieldName('DreadAffectedusers', TableMap::TYPE_PHPNAME, $indexType)];
            $this->dread_affectedusers = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 23 + $startcol : RiskScoringTableMap::translateFieldName('DreadDiscoverability', TableMap::TYPE_PHPNAME, $indexType)];
            $this->dread_discoverability = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 24 + $startcol : RiskScoringTableMap::translateFieldName('OwaspSkilllevel', TableMap::TYPE_PHPNAME, $indexType)];
            $this->owasp_skilllevel = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 25 + $startcol : RiskScoringTableMap::translateFieldName('OwaspMotive', TableMap::TYPE_PHPNAME, $indexType)];
            $this->owasp_motive = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 26 + $startcol : RiskScoringTableMap::translateFieldName('OwaspOpportunity', TableMap::TYPE_PHPNAME, $indexType)];
            $this->owasp_opportunity = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 27 + $startcol : RiskScoringTableMap::translateFieldName('OwaspSize', TableMap::TYPE_PHPNAME, $indexType)];
            $this->owasp_size = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 28 + $startcol : RiskScoringTableMap::translateFieldName('OwaspEaseofdiscovery', TableMap::TYPE_PHPNAME, $indexType)];
            $this->owasp_easeofdiscovery = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 29 + $startcol : RiskScoringTableMap::translateFieldName('OwaspEaseofexploit', TableMap::TYPE_PHPNAME, $indexType)];
            $this->owasp_easeofexploit = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 30 + $startcol : RiskScoringTableMap::translateFieldName('OwaspAwareness', TableMap::TYPE_PHPNAME, $indexType)];
            $this->owasp_awareness = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 31 + $startcol : RiskScoringTableMap::translateFieldName('OwaspIntrusiondetection', TableMap::TYPE_PHPNAME, $indexType)];
            $this->owasp_intrusiondetection = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 32 + $startcol : RiskScoringTableMap::translateFieldName('OwaspLossofconfidentiality', TableMap::TYPE_PHPNAME, $indexType)];
            $this->owasp_lossofconfidentiality = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 33 + $startcol : RiskScoringTableMap::translateFieldName('OwaspLossofintegrity', TableMap::TYPE_PHPNAME, $indexType)];
            $this->owasp_lossofintegrity = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 34 + $startcol : RiskScoringTableMap::translateFieldName('OwaspLossofavailability', TableMap::TYPE_PHPNAME, $indexType)];
            $this->owasp_lossofavailability = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 35 + $startcol : RiskScoringTableMap::translateFieldName('OwaspLossofaccountability', TableMap::TYPE_PHPNAME, $indexType)];
            $this->owasp_lossofaccountability = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 36 + $startcol : RiskScoringTableMap::translateFieldName('OwaspFinancialdamage', TableMap::TYPE_PHPNAME, $indexType)];
            $this->owasp_financialdamage = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 37 + $startcol : RiskScoringTableMap::translateFieldName('OwaspReputationdamage', TableMap::TYPE_PHPNAME, $indexType)];
            $this->owasp_reputationdamage = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 38 + $startcol : RiskScoringTableMap::translateFieldName('OwaspNoncompliance', TableMap::TYPE_PHPNAME, $indexType)];
            $this->owasp_noncompliance = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 39 + $startcol : RiskScoringTableMap::translateFieldName('OwaspPrivacyviolation', TableMap::TYPE_PHPNAME, $indexType)];
            $this->owasp_privacyviolation = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 40 + $startcol : RiskScoringTableMap::translateFieldName('Custom', TableMap::TYPE_PHPNAME, $indexType)];
            $this->custom = (null !== $col) ? (double) $col : null;
            $this->resetModified();

            $this->setNew(false);

            if ($rehydrate) {
                $this->ensureConsistency();
            }

            return $startcol + 41; // 41 = RiskScoringTableMap::NUM_HYDRATE_COLUMNS.

        } catch (Exception $e) {
            throw new PropelException(sprintf('Error populating %s object', '\\RiskScoring'), 0, $e);
        }
    }

    /**
     * Checks and repairs the internal consistency of the object.
     *
     * This method is executed after an already-instantiated object is re-hydrated
     * from the database.  It exists to check any foreign keys to make sure that
     * the objects related to the current object are correct based on foreign key.
     *
     * You can override this method in the stub class, but you should always invoke
     * the base method from the overridden method (i.e. parent::ensureConsistency()),
     * in case your model changes.
     *
     * @throws PropelException
     */
    public function ensureConsistency()
    {
    } // ensureConsistency

    /**
     * Reloads this object from datastore based on primary key and (optionally) resets all associated objects.
     *
     * This will only work if the object has been saved and has a valid primary key set.
     *
     * @param      boolean $deep (optional) Whether to also de-associated any related objects.
     * @param      ConnectionInterface $con (optional) The ConnectionInterface connection to use.
     * @return void
     * @throws PropelException - if this object is deleted, unsaved or doesn't have pk match in db
     */
    public function reload($deep = false, ConnectionInterface $con = null)
    {
        if ($this->isDeleted()) {
            throw new PropelException("Cannot reload a deleted object.");
        }

        if ($this->isNew()) {
            throw new PropelException("Cannot reload an unsaved object.");
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(RiskScoringTableMap::DATABASE_NAME);
        }

        // We don't need to alter the object instance pool; we're just modifying this instance
        // already in the pool.

        $dataFetcher = ChildRiskScoringQuery::create(null, $this->buildPkeyCriteria())->setFormatter(ModelCriteria::FORMAT_STATEMENT)->find($con);
        $row = $dataFetcher->fetch();
        $dataFetcher->close();
        if (!$row) {
            throw new PropelException('Cannot find matching row in the database to reload object values.');
        }
        $this->hydrate($row, 0, true, $dataFetcher->getIndexType()); // rehydrate

        if ($deep) {  // also de-associate any related objects?

        } // if (deep)
    }

    /**
     * Removes this object from datastore and sets delete attribute.
     *
     * @param      ConnectionInterface $con
     * @return void
     * @throws PropelException
     * @see RiskScoring::setDeleted()
     * @see RiskScoring::isDeleted()
     */
    public function delete(ConnectionInterface $con = null)
    {
        if ($this->isDeleted()) {
            throw new PropelException("This object has already been deleted.");
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getWriteConnection(RiskScoringTableMap::DATABASE_NAME);
        }

        $con->transaction(function () use ($con) {
            $deleteQuery = ChildRiskScoringQuery::create()
                ->filterByPrimaryKey($this->getPrimaryKey());
            $ret = $this->preDelete($con);
            if ($ret) {
                $deleteQuery->delete($con);
                $this->postDelete($con);
                $this->setDeleted(true);
            }
        });
    }

    /**
     * Persists this object to the database.
     *
     * If the object is new, it inserts it; otherwise an update is performed.
     * All modified related objects will also be persisted in the doSave()
     * method.  This method wraps all precipitate database operations in a
     * single transaction.
     *
     * @param      ConnectionInterface $con
     * @return int             The number of rows affected by this insert/update and any referring fk objects' save() operations.
     * @throws PropelException
     * @see doSave()
     */
    public function save(ConnectionInterface $con = null)
    {
        if ($this->isDeleted()) {
            throw new PropelException("You cannot save an object that has been deleted.");
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getWriteConnection(RiskScoringTableMap::DATABASE_NAME);
        }

        return $con->transaction(function () use ($con) {
            $isInsert = $this->isNew();
            $ret = $this->preSave($con);
            if ($isInsert) {
                $ret = $ret && $this->preInsert($con);
            } else {
                $ret = $ret && $this->preUpdate($con);
            }
            if ($ret) {
                $affectedRows = $this->doSave($con);
                if ($isInsert) {
                    $this->postInsert($con);
                } else {
                    $this->postUpdate($con);
                }
                $this->postSave($con);
                RiskScoringTableMap::addInstanceToPool($this);
            } else {
                $affectedRows = 0;
            }

            return $affectedRows;
        });
    }

    /**
     * Performs the work of inserting or updating the row in the database.
     *
     * If the object is new, it inserts it; otherwise an update is performed.
     * All related objects are also updated in this method.
     *
     * @param      ConnectionInterface $con
     * @return int             The number of rows affected by this insert/update and any referring fk objects' save() operations.
     * @throws PropelException
     * @see save()
     */
    protected function doSave(ConnectionInterface $con)
    {
        $affectedRows = 0; // initialize var to track total num of affected rows
        if (!$this->alreadyInSave) {
            $this->alreadyInSave = true;

            if ($this->isNew() || $this->isModified()) {
                // persist changes
                if ($this->isNew()) {
                    $this->doInsert($con);
                    $affectedRows += 1;
                } else {
                    $affectedRows += $this->doUpdate($con);
                }
                $this->resetModified();
            }

            $this->alreadyInSave = false;

        }

        return $affectedRows;
    } // doSave()

    /**
     * Insert the row in the database.
     *
     * @param      ConnectionInterface $con
     *
     * @throws PropelException
     * @see doSave()
     */
    protected function doInsert(ConnectionInterface $con)
    {
        $modifiedColumns = array();
        $index = 0;


         // check the columns in natural order for more readable SQL queries
        if ($this->isColumnModified(RiskScoringTableMap::COL_ID)) {
            $modifiedColumns[':p' . $index++]  = 'id';
        }
        if ($this->isColumnModified(RiskScoringTableMap::COL_SCORING_METHOD)) {
            $modifiedColumns[':p' . $index++]  = 'scoring_method';
        }
        if ($this->isColumnModified(RiskScoringTableMap::COL_CALCULATED_RISK)) {
            $modifiedColumns[':p' . $index++]  = 'calculated_risk';
        }
        if ($this->isColumnModified(RiskScoringTableMap::COL_CLASSIC_LIKELIHOOD)) {
            $modifiedColumns[':p' . $index++]  = 'CLASSIC_likelihood';
        }
        if ($this->isColumnModified(RiskScoringTableMap::COL_CLASSIC_IMPACT)) {
            $modifiedColumns[':p' . $index++]  = 'CLASSIC_impact';
        }
        if ($this->isColumnModified(RiskScoringTableMap::COL_CVSS_ACCESSVECTOR)) {
            $modifiedColumns[':p' . $index++]  = 'CVSS_AccessVector';
        }
        if ($this->isColumnModified(RiskScoringTableMap::COL_CVSS_ACCESSCOMPLEXITY)) {
            $modifiedColumns[':p' . $index++]  = 'CVSS_AccessComplexity';
        }
        if ($this->isColumnModified(RiskScoringTableMap::COL_CVSS_AUTHENTICATION)) {
            $modifiedColumns[':p' . $index++]  = 'CVSS_Authentication';
        }
        if ($this->isColumnModified(RiskScoringTableMap::COL_CVSS_CONFIMPACT)) {
            $modifiedColumns[':p' . $index++]  = 'CVSS_ConfImpact';
        }
        if ($this->isColumnModified(RiskScoringTableMap::COL_CVSS_INTEGIMPACT)) {
            $modifiedColumns[':p' . $index++]  = 'CVSS_IntegImpact';
        }
        if ($this->isColumnModified(RiskScoringTableMap::COL_CVSS_AVAILIMPACT)) {
            $modifiedColumns[':p' . $index++]  = 'CVSS_AvailImpact';
        }
        if ($this->isColumnModified(RiskScoringTableMap::COL_CVSS_EXPLOITABILITY)) {
            $modifiedColumns[':p' . $index++]  = 'CVSS_Exploitability';
        }
        if ($this->isColumnModified(RiskScoringTableMap::COL_CVSS_REMEDIATIONLEVEL)) {
            $modifiedColumns[':p' . $index++]  = 'CVSS_RemediationLevel';
        }
        if ($this->isColumnModified(RiskScoringTableMap::COL_CVSS_REPORTCONFIDENCE)) {
            $modifiedColumns[':p' . $index++]  = 'CVSS_ReportConfidence';
        }
        if ($this->isColumnModified(RiskScoringTableMap::COL_CVSS_COLLATERALDAMAGEPOTENTIAL)) {
            $modifiedColumns[':p' . $index++]  = 'CVSS_CollateralDamagePotential';
        }
        if ($this->isColumnModified(RiskScoringTableMap::COL_CVSS_TARGETDISTRIBUTION)) {
            $modifiedColumns[':p' . $index++]  = 'CVSS_TargetDistribution';
        }
        if ($this->isColumnModified(RiskScoringTableMap::COL_CVSS_CONFIDENTIALITYREQUIREMENT)) {
            $modifiedColumns[':p' . $index++]  = 'CVSS_ConfidentialityRequirement';
        }
        if ($this->isColumnModified(RiskScoringTableMap::COL_CVSS_INTEGRITYREQUIREMENT)) {
            $modifiedColumns[':p' . $index++]  = 'CVSS_IntegrityRequirement';
        }
        if ($this->isColumnModified(RiskScoringTableMap::COL_CVSS_AVAILABILITYREQUIREMENT)) {
            $modifiedColumns[':p' . $index++]  = 'CVSS_AvailabilityRequirement';
        }
        if ($this->isColumnModified(RiskScoringTableMap::COL_DREAD_DAMAGEPOTENTIAL)) {
            $modifiedColumns[':p' . $index++]  = 'DREAD_DamagePotential';
        }
        if ($this->isColumnModified(RiskScoringTableMap::COL_DREAD_REPRODUCIBILITY)) {
            $modifiedColumns[':p' . $index++]  = 'DREAD_Reproducibility';
        }
        if ($this->isColumnModified(RiskScoringTableMap::COL_DREAD_EXPLOITABILITY)) {
            $modifiedColumns[':p' . $index++]  = 'DREAD_Exploitability';
        }
        if ($this->isColumnModified(RiskScoringTableMap::COL_DREAD_AFFECTEDUSERS)) {
            $modifiedColumns[':p' . $index++]  = 'DREAD_AffectedUsers';
        }
        if ($this->isColumnModified(RiskScoringTableMap::COL_DREAD_DISCOVERABILITY)) {
            $modifiedColumns[':p' . $index++]  = 'DREAD_Discoverability';
        }
        if ($this->isColumnModified(RiskScoringTableMap::COL_OWASP_SKILLLEVEL)) {
            $modifiedColumns[':p' . $index++]  = 'OWASP_SkillLevel';
        }
        if ($this->isColumnModified(RiskScoringTableMap::COL_OWASP_MOTIVE)) {
            $modifiedColumns[':p' . $index++]  = 'OWASP_Motive';
        }
        if ($this->isColumnModified(RiskScoringTableMap::COL_OWASP_OPPORTUNITY)) {
            $modifiedColumns[':p' . $index++]  = 'OWASP_Opportunity';
        }
        if ($this->isColumnModified(RiskScoringTableMap::COL_OWASP_SIZE)) {
            $modifiedColumns[':p' . $index++]  = 'OWASP_Size';
        }
        if ($this->isColumnModified(RiskScoringTableMap::COL_OWASP_EASEOFDISCOVERY)) {
            $modifiedColumns[':p' . $index++]  = 'OWASP_EaseOfDiscovery';
        }
        if ($this->isColumnModified(RiskScoringTableMap::COL_OWASP_EASEOFEXPLOIT)) {
            $modifiedColumns[':p' . $index++]  = 'OWASP_EaseOfExploit';
        }
        if ($this->isColumnModified(RiskScoringTableMap::COL_OWASP_AWARENESS)) {
            $modifiedColumns[':p' . $index++]  = 'OWASP_Awareness';
        }
        if ($this->isColumnModified(RiskScoringTableMap::COL_OWASP_INTRUSIONDETECTION)) {
            $modifiedColumns[':p' . $index++]  = 'OWASP_IntrusionDetection';
        }
        if ($this->isColumnModified(RiskScoringTableMap::COL_OWASP_LOSSOFCONFIDENTIALITY)) {
            $modifiedColumns[':p' . $index++]  = 'OWASP_LossOfConfidentiality';
        }
        if ($this->isColumnModified(RiskScoringTableMap::COL_OWASP_LOSSOFINTEGRITY)) {
            $modifiedColumns[':p' . $index++]  = 'OWASP_LossOfIntegrity';
        }
        if ($this->isColumnModified(RiskScoringTableMap::COL_OWASP_LOSSOFAVAILABILITY)) {
            $modifiedColumns[':p' . $index++]  = 'OWASP_LossOfAvailability';
        }
        if ($this->isColumnModified(RiskScoringTableMap::COL_OWASP_LOSSOFACCOUNTABILITY)) {
            $modifiedColumns[':p' . $index++]  = 'OWASP_LossOfAccountability';
        }
        if ($this->isColumnModified(RiskScoringTableMap::COL_OWASP_FINANCIALDAMAGE)) {
            $modifiedColumns[':p' . $index++]  = 'OWASP_FinancialDamage';
        }
        if ($this->isColumnModified(RiskScoringTableMap::COL_OWASP_REPUTATIONDAMAGE)) {
            $modifiedColumns[':p' . $index++]  = 'OWASP_ReputationDamage';
        }
        if ($this->isColumnModified(RiskScoringTableMap::COL_OWASP_NONCOMPLIANCE)) {
            $modifiedColumns[':p' . $index++]  = 'OWASP_NonCompliance';
        }
        if ($this->isColumnModified(RiskScoringTableMap::COL_OWASP_PRIVACYVIOLATION)) {
            $modifiedColumns[':p' . $index++]  = 'OWASP_PrivacyViolation';
        }
        if ($this->isColumnModified(RiskScoringTableMap::COL_CUSTOM)) {
            $modifiedColumns[':p' . $index++]  = 'Custom';
        }

        $sql = sprintf(
            'INSERT INTO risk_scoring (%s) VALUES (%s)',
            implode(', ', $modifiedColumns),
            implode(', ', array_keys($modifiedColumns))
        );

        try {
            $stmt = $con->prepare($sql);
            foreach ($modifiedColumns as $identifier => $columnName) {
                switch ($columnName) {
                    case 'id':
                        $stmt->bindValue($identifier, $this->id, PDO::PARAM_INT);
                        break;
                    case 'scoring_method':
                        $stmt->bindValue($identifier, $this->scoring_method, PDO::PARAM_INT);
                        break;
                    case 'calculated_risk':
                        $stmt->bindValue($identifier, $this->calculated_risk, PDO::PARAM_STR);
                        break;
                    case 'CLASSIC_likelihood':
                        $stmt->bindValue($identifier, $this->classic_likelihood, PDO::PARAM_STR);
                        break;
                    case 'CLASSIC_impact':
                        $stmt->bindValue($identifier, $this->classic_impact, PDO::PARAM_STR);
                        break;
                    case 'CVSS_AccessVector':
                        $stmt->bindValue($identifier, $this->cvss_accessvector, PDO::PARAM_STR);
                        break;
                    case 'CVSS_AccessComplexity':
                        $stmt->bindValue($identifier, $this->cvss_accesscomplexity, PDO::PARAM_STR);
                        break;
                    case 'CVSS_Authentication':
                        $stmt->bindValue($identifier, $this->cvss_authentication, PDO::PARAM_STR);
                        break;
                    case 'CVSS_ConfImpact':
                        $stmt->bindValue($identifier, $this->cvss_confimpact, PDO::PARAM_STR);
                        break;
                    case 'CVSS_IntegImpact':
                        $stmt->bindValue($identifier, $this->cvss_integimpact, PDO::PARAM_STR);
                        break;
                    case 'CVSS_AvailImpact':
                        $stmt->bindValue($identifier, $this->cvss_availimpact, PDO::PARAM_STR);
                        break;
                    case 'CVSS_Exploitability':
                        $stmt->bindValue($identifier, $this->cvss_exploitability, PDO::PARAM_STR);
                        break;
                    case 'CVSS_RemediationLevel':
                        $stmt->bindValue($identifier, $this->cvss_remediationlevel, PDO::PARAM_STR);
                        break;
                    case 'CVSS_ReportConfidence':
                        $stmt->bindValue($identifier, $this->cvss_reportconfidence, PDO::PARAM_STR);
                        break;
                    case 'CVSS_CollateralDamagePotential':
                        $stmt->bindValue($identifier, $this->cvss_collateraldamagepotential, PDO::PARAM_STR);
                        break;
                    case 'CVSS_TargetDistribution':
                        $stmt->bindValue($identifier, $this->cvss_targetdistribution, PDO::PARAM_STR);
                        break;
                    case 'CVSS_ConfidentialityRequirement':
                        $stmt->bindValue($identifier, $this->cvss_confidentialityrequirement, PDO::PARAM_STR);
                        break;
                    case 'CVSS_IntegrityRequirement':
                        $stmt->bindValue($identifier, $this->cvss_integrityrequirement, PDO::PARAM_STR);
                        break;
                    case 'CVSS_AvailabilityRequirement':
                        $stmt->bindValue($identifier, $this->cvss_availabilityrequirement, PDO::PARAM_STR);
                        break;
                    case 'DREAD_DamagePotential':
                        $stmt->bindValue($identifier, $this->dread_damagepotential, PDO::PARAM_INT);
                        break;
                    case 'DREAD_Reproducibility':
                        $stmt->bindValue($identifier, $this->dread_reproducibility, PDO::PARAM_INT);
                        break;
                    case 'DREAD_Exploitability':
                        $stmt->bindValue($identifier, $this->dread_exploitability, PDO::PARAM_INT);
                        break;
                    case 'DREAD_AffectedUsers':
                        $stmt->bindValue($identifier, $this->dread_affectedusers, PDO::PARAM_INT);
                        break;
                    case 'DREAD_Discoverability':
                        $stmt->bindValue($identifier, $this->dread_discoverability, PDO::PARAM_INT);
                        break;
                    case 'OWASP_SkillLevel':
                        $stmt->bindValue($identifier, $this->owasp_skilllevel, PDO::PARAM_INT);
                        break;
                    case 'OWASP_Motive':
                        $stmt->bindValue($identifier, $this->owasp_motive, PDO::PARAM_INT);
                        break;
                    case 'OWASP_Opportunity':
                        $stmt->bindValue($identifier, $this->owasp_opportunity, PDO::PARAM_INT);
                        break;
                    case 'OWASP_Size':
                        $stmt->bindValue($identifier, $this->owasp_size, PDO::PARAM_INT);
                        break;
                    case 'OWASP_EaseOfDiscovery':
                        $stmt->bindValue($identifier, $this->owasp_easeofdiscovery, PDO::PARAM_INT);
                        break;
                    case 'OWASP_EaseOfExploit':
                        $stmt->bindValue($identifier, $this->owasp_easeofexploit, PDO::PARAM_INT);
                        break;
                    case 'OWASP_Awareness':
                        $stmt->bindValue($identifier, $this->owasp_awareness, PDO::PARAM_INT);
                        break;
                    case 'OWASP_IntrusionDetection':
                        $stmt->bindValue($identifier, $this->owasp_intrusiondetection, PDO::PARAM_INT);
                        break;
                    case 'OWASP_LossOfConfidentiality':
                        $stmt->bindValue($identifier, $this->owasp_lossofconfidentiality, PDO::PARAM_INT);
                        break;
                    case 'OWASP_LossOfIntegrity':
                        $stmt->bindValue($identifier, $this->owasp_lossofintegrity, PDO::PARAM_INT);
                        break;
                    case 'OWASP_LossOfAvailability':
                        $stmt->bindValue($identifier, $this->owasp_lossofavailability, PDO::PARAM_INT);
                        break;
                    case 'OWASP_LossOfAccountability':
                        $stmt->bindValue($identifier, $this->owasp_lossofaccountability, PDO::PARAM_INT);
                        break;
                    case 'OWASP_FinancialDamage':
                        $stmt->bindValue($identifier, $this->owasp_financialdamage, PDO::PARAM_INT);
                        break;
                    case 'OWASP_ReputationDamage':
                        $stmt->bindValue($identifier, $this->owasp_reputationdamage, PDO::PARAM_INT);
                        break;
                    case 'OWASP_NonCompliance':
                        $stmt->bindValue($identifier, $this->owasp_noncompliance, PDO::PARAM_INT);
                        break;
                    case 'OWASP_PrivacyViolation':
                        $stmt->bindValue($identifier, $this->owasp_privacyviolation, PDO::PARAM_INT);
                        break;
                    case 'Custom':
                        $stmt->bindValue($identifier, $this->custom, PDO::PARAM_STR);
                        break;
                }
            }
            $stmt->execute();
        } catch (Exception $e) {
            Propel::log($e->getMessage(), Propel::LOG_ERR);
            throw new PropelException(sprintf('Unable to execute INSERT statement [%s]', $sql), 0, $e);
        }

        $this->setNew(false);
    }

    /**
     * Update the row in the database.
     *
     * @param      ConnectionInterface $con
     *
     * @return Integer Number of updated rows
     * @see doSave()
     */
    protected function doUpdate(ConnectionInterface $con)
    {
        $selectCriteria = $this->buildPkeyCriteria();
        $valuesCriteria = $this->buildCriteria();

        return $selectCriteria->doUpdate($valuesCriteria, $con);
    }

    /**
     * Retrieves a field from the object by name passed in as a string.
     *
     * @param      string $name name
     * @param      string $type The type of fieldname the $name is of:
     *                     one of the class type constants TableMap::TYPE_PHPNAME, TableMap::TYPE_CAMELNAME
     *                     TableMap::TYPE_COLNAME, TableMap::TYPE_FIELDNAME, TableMap::TYPE_NUM.
     *                     Defaults to TableMap::TYPE_PHPNAME.
     * @return mixed Value of field.
     */
    public function getByName($name, $type = TableMap::TYPE_PHPNAME)
    {
        $pos = RiskScoringTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);
        $field = $this->getByPosition($pos);

        return $field;
    }

    /**
     * Retrieves a field from the object by Position as specified in the xml schema.
     * Zero-based.
     *
     * @param      int $pos position in xml schema
     * @return mixed Value of field at $pos
     */
    public function getByPosition($pos)
    {
        switch ($pos) {
            case 0:
                return $this->getId();
                break;
            case 1:
                return $this->getScoringMethod();
                break;
            case 2:
                return $this->getCalculatedRisk();
                break;
            case 3:
                return $this->getClassicLikelihood();
                break;
            case 4:
                return $this->getClassicImpact();
                break;
            case 5:
                return $this->getCvssAccessvector();
                break;
            case 6:
                return $this->getCvssAccesscomplexity();
                break;
            case 7:
                return $this->getCvssAuthentication();
                break;
            case 8:
                return $this->getCvssConfimpact();
                break;
            case 9:
                return $this->getCvssIntegimpact();
                break;
            case 10:
                return $this->getCvssAvailimpact();
                break;
            case 11:
                return $this->getCvssExploitability();
                break;
            case 12:
                return $this->getCvssRemediationlevel();
                break;
            case 13:
                return $this->getCvssReportconfidence();
                break;
            case 14:
                return $this->getCvssCollateraldamagepotential();
                break;
            case 15:
                return $this->getCvssTargetdistribution();
                break;
            case 16:
                return $this->getCvssConfidentialityrequirement();
                break;
            case 17:
                return $this->getCvssIntegrityrequirement();
                break;
            case 18:
                return $this->getCvssAvailabilityrequirement();
                break;
            case 19:
                return $this->getDreadDamagepotential();
                break;
            case 20:
                return $this->getDreadReproducibility();
                break;
            case 21:
                return $this->getDreadExploitability();
                break;
            case 22:
                return $this->getDreadAffectedusers();
                break;
            case 23:
                return $this->getDreadDiscoverability();
                break;
            case 24:
                return $this->getOwaspSkilllevel();
                break;
            case 25:
                return $this->getOwaspMotive();
                break;
            case 26:
                return $this->getOwaspOpportunity();
                break;
            case 27:
                return $this->getOwaspSize();
                break;
            case 28:
                return $this->getOwaspEaseofdiscovery();
                break;
            case 29:
                return $this->getOwaspEaseofexploit();
                break;
            case 30:
                return $this->getOwaspAwareness();
                break;
            case 31:
                return $this->getOwaspIntrusiondetection();
                break;
            case 32:
                return $this->getOwaspLossofconfidentiality();
                break;
            case 33:
                return $this->getOwaspLossofintegrity();
                break;
            case 34:
                return $this->getOwaspLossofavailability();
                break;
            case 35:
                return $this->getOwaspLossofaccountability();
                break;
            case 36:
                return $this->getOwaspFinancialdamage();
                break;
            case 37:
                return $this->getOwaspReputationdamage();
                break;
            case 38:
                return $this->getOwaspNoncompliance();
                break;
            case 39:
                return $this->getOwaspPrivacyviolation();
                break;
            case 40:
                return $this->getCustom();
                break;
            default:
                return null;
                break;
        } // switch()
    }

    /**
     * Exports the object as an array.
     *
     * You can specify the key type of the array by passing one of the class
     * type constants.
     *
     * @param     string  $keyType (optional) One of the class type constants TableMap::TYPE_PHPNAME, TableMap::TYPE_CAMELNAME,
     *                    TableMap::TYPE_COLNAME, TableMap::TYPE_FIELDNAME, TableMap::TYPE_NUM.
     *                    Defaults to TableMap::TYPE_PHPNAME.
     * @param     boolean $includeLazyLoadColumns (optional) Whether to include lazy loaded columns. Defaults to TRUE.
     * @param     array $alreadyDumpedObjects List of objects to skip to avoid recursion
     *
     * @return array an associative array containing the field names (as keys) and field values
     */
    public function toArray($keyType = TableMap::TYPE_PHPNAME, $includeLazyLoadColumns = true, $alreadyDumpedObjects = array())
    {

        if (isset($alreadyDumpedObjects['RiskScoring'][$this->hashCode()])) {
            return '*RECURSION*';
        }
        $alreadyDumpedObjects['RiskScoring'][$this->hashCode()] = true;
        $keys = RiskScoringTableMap::getFieldNames($keyType);
        $result = array(
            $keys[0] => $this->getId(),
            $keys[1] => $this->getScoringMethod(),
            $keys[2] => $this->getCalculatedRisk(),
            $keys[3] => $this->getClassicLikelihood(),
            $keys[4] => $this->getClassicImpact(),
            $keys[5] => $this->getCvssAccessvector(),
            $keys[6] => $this->getCvssAccesscomplexity(),
            $keys[7] => $this->getCvssAuthentication(),
            $keys[8] => $this->getCvssConfimpact(),
            $keys[9] => $this->getCvssIntegimpact(),
            $keys[10] => $this->getCvssAvailimpact(),
            $keys[11] => $this->getCvssExploitability(),
            $keys[12] => $this->getCvssRemediationlevel(),
            $keys[13] => $this->getCvssReportconfidence(),
            $keys[14] => $this->getCvssCollateraldamagepotential(),
            $keys[15] => $this->getCvssTargetdistribution(),
            $keys[16] => $this->getCvssConfidentialityrequirement(),
            $keys[17] => $this->getCvssIntegrityrequirement(),
            $keys[18] => $this->getCvssAvailabilityrequirement(),
            $keys[19] => $this->getDreadDamagepotential(),
            $keys[20] => $this->getDreadReproducibility(),
            $keys[21] => $this->getDreadExploitability(),
            $keys[22] => $this->getDreadAffectedusers(),
            $keys[23] => $this->getDreadDiscoverability(),
            $keys[24] => $this->getOwaspSkilllevel(),
            $keys[25] => $this->getOwaspMotive(),
            $keys[26] => $this->getOwaspOpportunity(),
            $keys[27] => $this->getOwaspSize(),
            $keys[28] => $this->getOwaspEaseofdiscovery(),
            $keys[29] => $this->getOwaspEaseofexploit(),
            $keys[30] => $this->getOwaspAwareness(),
            $keys[31] => $this->getOwaspIntrusiondetection(),
            $keys[32] => $this->getOwaspLossofconfidentiality(),
            $keys[33] => $this->getOwaspLossofintegrity(),
            $keys[34] => $this->getOwaspLossofavailability(),
            $keys[35] => $this->getOwaspLossofaccountability(),
            $keys[36] => $this->getOwaspFinancialdamage(),
            $keys[37] => $this->getOwaspReputationdamage(),
            $keys[38] => $this->getOwaspNoncompliance(),
            $keys[39] => $this->getOwaspPrivacyviolation(),
            $keys[40] => $this->getCustom(),
        );
        $virtualColumns = $this->virtualColumns;
        foreach ($virtualColumns as $key => $virtualColumn) {
            $result[$key] = $virtualColumn;
        }


        return $result;
    }

    /**
     * Sets a field from the object by name passed in as a string.
     *
     * @param  string $name
     * @param  mixed  $value field value
     * @param  string $type The type of fieldname the $name is of:
     *                one of the class type constants TableMap::TYPE_PHPNAME, TableMap::TYPE_CAMELNAME
     *                TableMap::TYPE_COLNAME, TableMap::TYPE_FIELDNAME, TableMap::TYPE_NUM.
     *                Defaults to TableMap::TYPE_PHPNAME.
     * @return $this|\RiskScoring
     */
    public function setByName($name, $value, $type = TableMap::TYPE_PHPNAME)
    {
        $pos = RiskScoringTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);

        return $this->setByPosition($pos, $value);
    }

    /**
     * Sets a field from the object by Position as specified in the xml schema.
     * Zero-based.
     *
     * @param  int $pos position in xml schema
     * @param  mixed $value field value
     * @return $this|\RiskScoring
     */
    public function setByPosition($pos, $value)
    {
        switch ($pos) {
            case 0:
                $this->setId($value);
                break;
            case 1:
                $this->setScoringMethod($value);
                break;
            case 2:
                $this->setCalculatedRisk($value);
                break;
            case 3:
                $this->setClassicLikelihood($value);
                break;
            case 4:
                $this->setClassicImpact($value);
                break;
            case 5:
                $this->setCvssAccessvector($value);
                break;
            case 6:
                $this->setCvssAccesscomplexity($value);
                break;
            case 7:
                $this->setCvssAuthentication($value);
                break;
            case 8:
                $this->setCvssConfimpact($value);
                break;
            case 9:
                $this->setCvssIntegimpact($value);
                break;
            case 10:
                $this->setCvssAvailimpact($value);
                break;
            case 11:
                $this->setCvssExploitability($value);
                break;
            case 12:
                $this->setCvssRemediationlevel($value);
                break;
            case 13:
                $this->setCvssReportconfidence($value);
                break;
            case 14:
                $this->setCvssCollateraldamagepotential($value);
                break;
            case 15:
                $this->setCvssTargetdistribution($value);
                break;
            case 16:
                $this->setCvssConfidentialityrequirement($value);
                break;
            case 17:
                $this->setCvssIntegrityrequirement($value);
                break;
            case 18:
                $this->setCvssAvailabilityrequirement($value);
                break;
            case 19:
                $this->setDreadDamagepotential($value);
                break;
            case 20:
                $this->setDreadReproducibility($value);
                break;
            case 21:
                $this->setDreadExploitability($value);
                break;
            case 22:
                $this->setDreadAffectedusers($value);
                break;
            case 23:
                $this->setDreadDiscoverability($value);
                break;
            case 24:
                $this->setOwaspSkilllevel($value);
                break;
            case 25:
                $this->setOwaspMotive($value);
                break;
            case 26:
                $this->setOwaspOpportunity($value);
                break;
            case 27:
                $this->setOwaspSize($value);
                break;
            case 28:
                $this->setOwaspEaseofdiscovery($value);
                break;
            case 29:
                $this->setOwaspEaseofexploit($value);
                break;
            case 30:
                $this->setOwaspAwareness($value);
                break;
            case 31:
                $this->setOwaspIntrusiondetection($value);
                break;
            case 32:
                $this->setOwaspLossofconfidentiality($value);
                break;
            case 33:
                $this->setOwaspLossofintegrity($value);
                break;
            case 34:
                $this->setOwaspLossofavailability($value);
                break;
            case 35:
                $this->setOwaspLossofaccountability($value);
                break;
            case 36:
                $this->setOwaspFinancialdamage($value);
                break;
            case 37:
                $this->setOwaspReputationdamage($value);
                break;
            case 38:
                $this->setOwaspNoncompliance($value);
                break;
            case 39:
                $this->setOwaspPrivacyviolation($value);
                break;
            case 40:
                $this->setCustom($value);
                break;
        } // switch()

        return $this;
    }

    /**
     * Populates the object using an array.
     *
     * This is particularly useful when populating an object from one of the
     * request arrays (e.g. $_POST).  This method goes through the column
     * names, checking to see whether a matching key exists in populated
     * array. If so the setByName() method is called for that column.
     *
     * You can specify the key type of the array by additionally passing one
     * of the class type constants TableMap::TYPE_PHPNAME, TableMap::TYPE_CAMELNAME,
     * TableMap::TYPE_COLNAME, TableMap::TYPE_FIELDNAME, TableMap::TYPE_NUM.
     * The default key type is the column's TableMap::TYPE_PHPNAME.
     *
     * @param      array  $arr     An array to populate the object from.
     * @param      string $keyType The type of keys the array uses.
     * @return void
     */
    public function fromArray($arr, $keyType = TableMap::TYPE_PHPNAME)
    {
        $keys = RiskScoringTableMap::getFieldNames($keyType);

        if (array_key_exists($keys[0], $arr)) {
            $this->setId($arr[$keys[0]]);
        }
        if (array_key_exists($keys[1], $arr)) {
            $this->setScoringMethod($arr[$keys[1]]);
        }
        if (array_key_exists($keys[2], $arr)) {
            $this->setCalculatedRisk($arr[$keys[2]]);
        }
        if (array_key_exists($keys[3], $arr)) {
            $this->setClassicLikelihood($arr[$keys[3]]);
        }
        if (array_key_exists($keys[4], $arr)) {
            $this->setClassicImpact($arr[$keys[4]]);
        }
        if (array_key_exists($keys[5], $arr)) {
            $this->setCvssAccessvector($arr[$keys[5]]);
        }
        if (array_key_exists($keys[6], $arr)) {
            $this->setCvssAccesscomplexity($arr[$keys[6]]);
        }
        if (array_key_exists($keys[7], $arr)) {
            $this->setCvssAuthentication($arr[$keys[7]]);
        }
        if (array_key_exists($keys[8], $arr)) {
            $this->setCvssConfimpact($arr[$keys[8]]);
        }
        if (array_key_exists($keys[9], $arr)) {
            $this->setCvssIntegimpact($arr[$keys[9]]);
        }
        if (array_key_exists($keys[10], $arr)) {
            $this->setCvssAvailimpact($arr[$keys[10]]);
        }
        if (array_key_exists($keys[11], $arr)) {
            $this->setCvssExploitability($arr[$keys[11]]);
        }
        if (array_key_exists($keys[12], $arr)) {
            $this->setCvssRemediationlevel($arr[$keys[12]]);
        }
        if (array_key_exists($keys[13], $arr)) {
            $this->setCvssReportconfidence($arr[$keys[13]]);
        }
        if (array_key_exists($keys[14], $arr)) {
            $this->setCvssCollateraldamagepotential($arr[$keys[14]]);
        }
        if (array_key_exists($keys[15], $arr)) {
            $this->setCvssTargetdistribution($arr[$keys[15]]);
        }
        if (array_key_exists($keys[16], $arr)) {
            $this->setCvssConfidentialityrequirement($arr[$keys[16]]);
        }
        if (array_key_exists($keys[17], $arr)) {
            $this->setCvssIntegrityrequirement($arr[$keys[17]]);
        }
        if (array_key_exists($keys[18], $arr)) {
            $this->setCvssAvailabilityrequirement($arr[$keys[18]]);
        }
        if (array_key_exists($keys[19], $arr)) {
            $this->setDreadDamagepotential($arr[$keys[19]]);
        }
        if (array_key_exists($keys[20], $arr)) {
            $this->setDreadReproducibility($arr[$keys[20]]);
        }
        if (array_key_exists($keys[21], $arr)) {
            $this->setDreadExploitability($arr[$keys[21]]);
        }
        if (array_key_exists($keys[22], $arr)) {
            $this->setDreadAffectedusers($arr[$keys[22]]);
        }
        if (array_key_exists($keys[23], $arr)) {
            $this->setDreadDiscoverability($arr[$keys[23]]);
        }
        if (array_key_exists($keys[24], $arr)) {
            $this->setOwaspSkilllevel($arr[$keys[24]]);
        }
        if (array_key_exists($keys[25], $arr)) {
            $this->setOwaspMotive($arr[$keys[25]]);
        }
        if (array_key_exists($keys[26], $arr)) {
            $this->setOwaspOpportunity($arr[$keys[26]]);
        }
        if (array_key_exists($keys[27], $arr)) {
            $this->setOwaspSize($arr[$keys[27]]);
        }
        if (array_key_exists($keys[28], $arr)) {
            $this->setOwaspEaseofdiscovery($arr[$keys[28]]);
        }
        if (array_key_exists($keys[29], $arr)) {
            $this->setOwaspEaseofexploit($arr[$keys[29]]);
        }
        if (array_key_exists($keys[30], $arr)) {
            $this->setOwaspAwareness($arr[$keys[30]]);
        }
        if (array_key_exists($keys[31], $arr)) {
            $this->setOwaspIntrusiondetection($arr[$keys[31]]);
        }
        if (array_key_exists($keys[32], $arr)) {
            $this->setOwaspLossofconfidentiality($arr[$keys[32]]);
        }
        if (array_key_exists($keys[33], $arr)) {
            $this->setOwaspLossofintegrity($arr[$keys[33]]);
        }
        if (array_key_exists($keys[34], $arr)) {
            $this->setOwaspLossofavailability($arr[$keys[34]]);
        }
        if (array_key_exists($keys[35], $arr)) {
            $this->setOwaspLossofaccountability($arr[$keys[35]]);
        }
        if (array_key_exists($keys[36], $arr)) {
            $this->setOwaspFinancialdamage($arr[$keys[36]]);
        }
        if (array_key_exists($keys[37], $arr)) {
            $this->setOwaspReputationdamage($arr[$keys[37]]);
        }
        if (array_key_exists($keys[38], $arr)) {
            $this->setOwaspNoncompliance($arr[$keys[38]]);
        }
        if (array_key_exists($keys[39], $arr)) {
            $this->setOwaspPrivacyviolation($arr[$keys[39]]);
        }
        if (array_key_exists($keys[40], $arr)) {
            $this->setCustom($arr[$keys[40]]);
        }
    }

     /**
     * Populate the current object from a string, using a given parser format
     * <code>
     * $book = new Book();
     * $book->importFrom('JSON', '{"Id":9012,"Title":"Don Juan","ISBN":"0140422161","Price":12.99,"PublisherId":1234,"AuthorId":5678}');
     * </code>
     *
     * You can specify the key type of the array by additionally passing one
     * of the class type constants TableMap::TYPE_PHPNAME, TableMap::TYPE_CAMELNAME,
     * TableMap::TYPE_COLNAME, TableMap::TYPE_FIELDNAME, TableMap::TYPE_NUM.
     * The default key type is the column's TableMap::TYPE_PHPNAME.
     *
     * @param mixed $parser A AbstractParser instance,
     *                       or a format name ('XML', 'YAML', 'JSON', 'CSV')
     * @param string $data The source data to import from
     * @param string $keyType The type of keys the array uses.
     *
     * @return $this|\RiskScoring The current object, for fluid interface
     */
    public function importFrom($parser, $data, $keyType = TableMap::TYPE_PHPNAME)
    {
        if (!$parser instanceof AbstractParser) {
            $parser = AbstractParser::getParser($parser);
        }

        $this->fromArray($parser->toArray($data), $keyType);

        return $this;
    }

    /**
     * Build a Criteria object containing the values of all modified columns in this object.
     *
     * @return Criteria The Criteria object containing all modified values.
     */
    public function buildCriteria()
    {
        $criteria = new Criteria(RiskScoringTableMap::DATABASE_NAME);

        if ($this->isColumnModified(RiskScoringTableMap::COL_ID)) {
            $criteria->add(RiskScoringTableMap::COL_ID, $this->id);
        }
        if ($this->isColumnModified(RiskScoringTableMap::COL_SCORING_METHOD)) {
            $criteria->add(RiskScoringTableMap::COL_SCORING_METHOD, $this->scoring_method);
        }
        if ($this->isColumnModified(RiskScoringTableMap::COL_CALCULATED_RISK)) {
            $criteria->add(RiskScoringTableMap::COL_CALCULATED_RISK, $this->calculated_risk);
        }
        if ($this->isColumnModified(RiskScoringTableMap::COL_CLASSIC_LIKELIHOOD)) {
            $criteria->add(RiskScoringTableMap::COL_CLASSIC_LIKELIHOOD, $this->classic_likelihood);
        }
        if ($this->isColumnModified(RiskScoringTableMap::COL_CLASSIC_IMPACT)) {
            $criteria->add(RiskScoringTableMap::COL_CLASSIC_IMPACT, $this->classic_impact);
        }
        if ($this->isColumnModified(RiskScoringTableMap::COL_CVSS_ACCESSVECTOR)) {
            $criteria->add(RiskScoringTableMap::COL_CVSS_ACCESSVECTOR, $this->cvss_accessvector);
        }
        if ($this->isColumnModified(RiskScoringTableMap::COL_CVSS_ACCESSCOMPLEXITY)) {
            $criteria->add(RiskScoringTableMap::COL_CVSS_ACCESSCOMPLEXITY, $this->cvss_accesscomplexity);
        }
        if ($this->isColumnModified(RiskScoringTableMap::COL_CVSS_AUTHENTICATION)) {
            $criteria->add(RiskScoringTableMap::COL_CVSS_AUTHENTICATION, $this->cvss_authentication);
        }
        if ($this->isColumnModified(RiskScoringTableMap::COL_CVSS_CONFIMPACT)) {
            $criteria->add(RiskScoringTableMap::COL_CVSS_CONFIMPACT, $this->cvss_confimpact);
        }
        if ($this->isColumnModified(RiskScoringTableMap::COL_CVSS_INTEGIMPACT)) {
            $criteria->add(RiskScoringTableMap::COL_CVSS_INTEGIMPACT, $this->cvss_integimpact);
        }
        if ($this->isColumnModified(RiskScoringTableMap::COL_CVSS_AVAILIMPACT)) {
            $criteria->add(RiskScoringTableMap::COL_CVSS_AVAILIMPACT, $this->cvss_availimpact);
        }
        if ($this->isColumnModified(RiskScoringTableMap::COL_CVSS_EXPLOITABILITY)) {
            $criteria->add(RiskScoringTableMap::COL_CVSS_EXPLOITABILITY, $this->cvss_exploitability);
        }
        if ($this->isColumnModified(RiskScoringTableMap::COL_CVSS_REMEDIATIONLEVEL)) {
            $criteria->add(RiskScoringTableMap::COL_CVSS_REMEDIATIONLEVEL, $this->cvss_remediationlevel);
        }
        if ($this->isColumnModified(RiskScoringTableMap::COL_CVSS_REPORTCONFIDENCE)) {
            $criteria->add(RiskScoringTableMap::COL_CVSS_REPORTCONFIDENCE, $this->cvss_reportconfidence);
        }
        if ($this->isColumnModified(RiskScoringTableMap::COL_CVSS_COLLATERALDAMAGEPOTENTIAL)) {
            $criteria->add(RiskScoringTableMap::COL_CVSS_COLLATERALDAMAGEPOTENTIAL, $this->cvss_collateraldamagepotential);
        }
        if ($this->isColumnModified(RiskScoringTableMap::COL_CVSS_TARGETDISTRIBUTION)) {
            $criteria->add(RiskScoringTableMap::COL_CVSS_TARGETDISTRIBUTION, $this->cvss_targetdistribution);
        }
        if ($this->isColumnModified(RiskScoringTableMap::COL_CVSS_CONFIDENTIALITYREQUIREMENT)) {
            $criteria->add(RiskScoringTableMap::COL_CVSS_CONFIDENTIALITYREQUIREMENT, $this->cvss_confidentialityrequirement);
        }
        if ($this->isColumnModified(RiskScoringTableMap::COL_CVSS_INTEGRITYREQUIREMENT)) {
            $criteria->add(RiskScoringTableMap::COL_CVSS_INTEGRITYREQUIREMENT, $this->cvss_integrityrequirement);
        }
        if ($this->isColumnModified(RiskScoringTableMap::COL_CVSS_AVAILABILITYREQUIREMENT)) {
            $criteria->add(RiskScoringTableMap::COL_CVSS_AVAILABILITYREQUIREMENT, $this->cvss_availabilityrequirement);
        }
        if ($this->isColumnModified(RiskScoringTableMap::COL_DREAD_DAMAGEPOTENTIAL)) {
            $criteria->add(RiskScoringTableMap::COL_DREAD_DAMAGEPOTENTIAL, $this->dread_damagepotential);
        }
        if ($this->isColumnModified(RiskScoringTableMap::COL_DREAD_REPRODUCIBILITY)) {
            $criteria->add(RiskScoringTableMap::COL_DREAD_REPRODUCIBILITY, $this->dread_reproducibility);
        }
        if ($this->isColumnModified(RiskScoringTableMap::COL_DREAD_EXPLOITABILITY)) {
            $criteria->add(RiskScoringTableMap::COL_DREAD_EXPLOITABILITY, $this->dread_exploitability);
        }
        if ($this->isColumnModified(RiskScoringTableMap::COL_DREAD_AFFECTEDUSERS)) {
            $criteria->add(RiskScoringTableMap::COL_DREAD_AFFECTEDUSERS, $this->dread_affectedusers);
        }
        if ($this->isColumnModified(RiskScoringTableMap::COL_DREAD_DISCOVERABILITY)) {
            $criteria->add(RiskScoringTableMap::COL_DREAD_DISCOVERABILITY, $this->dread_discoverability);
        }
        if ($this->isColumnModified(RiskScoringTableMap::COL_OWASP_SKILLLEVEL)) {
            $criteria->add(RiskScoringTableMap::COL_OWASP_SKILLLEVEL, $this->owasp_skilllevel);
        }
        if ($this->isColumnModified(RiskScoringTableMap::COL_OWASP_MOTIVE)) {
            $criteria->add(RiskScoringTableMap::COL_OWASP_MOTIVE, $this->owasp_motive);
        }
        if ($this->isColumnModified(RiskScoringTableMap::COL_OWASP_OPPORTUNITY)) {
            $criteria->add(RiskScoringTableMap::COL_OWASP_OPPORTUNITY, $this->owasp_opportunity);
        }
        if ($this->isColumnModified(RiskScoringTableMap::COL_OWASP_SIZE)) {
            $criteria->add(RiskScoringTableMap::COL_OWASP_SIZE, $this->owasp_size);
        }
        if ($this->isColumnModified(RiskScoringTableMap::COL_OWASP_EASEOFDISCOVERY)) {
            $criteria->add(RiskScoringTableMap::COL_OWASP_EASEOFDISCOVERY, $this->owasp_easeofdiscovery);
        }
        if ($this->isColumnModified(RiskScoringTableMap::COL_OWASP_EASEOFEXPLOIT)) {
            $criteria->add(RiskScoringTableMap::COL_OWASP_EASEOFEXPLOIT, $this->owasp_easeofexploit);
        }
        if ($this->isColumnModified(RiskScoringTableMap::COL_OWASP_AWARENESS)) {
            $criteria->add(RiskScoringTableMap::COL_OWASP_AWARENESS, $this->owasp_awareness);
        }
        if ($this->isColumnModified(RiskScoringTableMap::COL_OWASP_INTRUSIONDETECTION)) {
            $criteria->add(RiskScoringTableMap::COL_OWASP_INTRUSIONDETECTION, $this->owasp_intrusiondetection);
        }
        if ($this->isColumnModified(RiskScoringTableMap::COL_OWASP_LOSSOFCONFIDENTIALITY)) {
            $criteria->add(RiskScoringTableMap::COL_OWASP_LOSSOFCONFIDENTIALITY, $this->owasp_lossofconfidentiality);
        }
        if ($this->isColumnModified(RiskScoringTableMap::COL_OWASP_LOSSOFINTEGRITY)) {
            $criteria->add(RiskScoringTableMap::COL_OWASP_LOSSOFINTEGRITY, $this->owasp_lossofintegrity);
        }
        if ($this->isColumnModified(RiskScoringTableMap::COL_OWASP_LOSSOFAVAILABILITY)) {
            $criteria->add(RiskScoringTableMap::COL_OWASP_LOSSOFAVAILABILITY, $this->owasp_lossofavailability);
        }
        if ($this->isColumnModified(RiskScoringTableMap::COL_OWASP_LOSSOFACCOUNTABILITY)) {
            $criteria->add(RiskScoringTableMap::COL_OWASP_LOSSOFACCOUNTABILITY, $this->owasp_lossofaccountability);
        }
        if ($this->isColumnModified(RiskScoringTableMap::COL_OWASP_FINANCIALDAMAGE)) {
            $criteria->add(RiskScoringTableMap::COL_OWASP_FINANCIALDAMAGE, $this->owasp_financialdamage);
        }
        if ($this->isColumnModified(RiskScoringTableMap::COL_OWASP_REPUTATIONDAMAGE)) {
            $criteria->add(RiskScoringTableMap::COL_OWASP_REPUTATIONDAMAGE, $this->owasp_reputationdamage);
        }
        if ($this->isColumnModified(RiskScoringTableMap::COL_OWASP_NONCOMPLIANCE)) {
            $criteria->add(RiskScoringTableMap::COL_OWASP_NONCOMPLIANCE, $this->owasp_noncompliance);
        }
        if ($this->isColumnModified(RiskScoringTableMap::COL_OWASP_PRIVACYVIOLATION)) {
            $criteria->add(RiskScoringTableMap::COL_OWASP_PRIVACYVIOLATION, $this->owasp_privacyviolation);
        }
        if ($this->isColumnModified(RiskScoringTableMap::COL_CUSTOM)) {
            $criteria->add(RiskScoringTableMap::COL_CUSTOM, $this->custom);
        }

        return $criteria;
    }

    /**
     * Builds a Criteria object containing the primary key for this object.
     *
     * Unlike buildCriteria() this method includes the primary key values regardless
     * of whether or not they have been modified.
     *
     * @throws LogicException if no primary key is defined
     *
     * @return Criteria The Criteria object containing value(s) for primary key(s).
     */
    public function buildPkeyCriteria()
    {
        throw new LogicException('The RiskScoring object has no primary key');

        return $criteria;
    }

    /**
     * If the primary key is not null, return the hashcode of the
     * primary key. Otherwise, return the hash code of the object.
     *
     * @return int Hashcode
     */
    public function hashCode()
    {
        $validPk = false;

        $validPrimaryKeyFKs = 0;
        $primaryKeyFKs = [];

        if ($validPk) {
            return crc32(json_encode($this->getPrimaryKey(), JSON_UNESCAPED_UNICODE));
        } elseif ($validPrimaryKeyFKs) {
            return crc32(json_encode($primaryKeyFKs, JSON_UNESCAPED_UNICODE));
        }

        return spl_object_hash($this);
    }

    /**
     * Returns NULL since this table doesn't have a primary key.
     * This method exists only for BC and is deprecated!
     * @return null
     */
    public function getPrimaryKey()
    {
        return null;
    }

    /**
     * Dummy primary key setter.
     *
     * This function only exists to preserve backwards compatibility.  It is no longer
     * needed or required by the Persistent interface.  It will be removed in next BC-breaking
     * release of Propel.
     *
     * @deprecated
     */
    public function setPrimaryKey($pk)
    {
        // do nothing, because this object doesn't have any primary keys
    }

    /**
     * Returns true if the primary key for this object is null.
     * @return boolean
     */
    public function isPrimaryKeyNull()
    {
        return ;
    }

    /**
     * Sets contents of passed object to values from current object.
     *
     * If desired, this method can also make copies of all associated (fkey referrers)
     * objects.
     *
     * @param      object $copyObj An object of \RiskScoring (or compatible) type.
     * @param      boolean $deepCopy Whether to also copy all rows that refer (by fkey) to the current row.
     * @param      boolean $makeNew Whether to reset autoincrement PKs and make the object new.
     * @throws PropelException
     */
    public function copyInto($copyObj, $deepCopy = false, $makeNew = true)
    {
        $copyObj->setId($this->getId());
        $copyObj->setScoringMethod($this->getScoringMethod());
        $copyObj->setCalculatedRisk($this->getCalculatedRisk());
        $copyObj->setClassicLikelihood($this->getClassicLikelihood());
        $copyObj->setClassicImpact($this->getClassicImpact());
        $copyObj->setCvssAccessvector($this->getCvssAccessvector());
        $copyObj->setCvssAccesscomplexity($this->getCvssAccesscomplexity());
        $copyObj->setCvssAuthentication($this->getCvssAuthentication());
        $copyObj->setCvssConfimpact($this->getCvssConfimpact());
        $copyObj->setCvssIntegimpact($this->getCvssIntegimpact());
        $copyObj->setCvssAvailimpact($this->getCvssAvailimpact());
        $copyObj->setCvssExploitability($this->getCvssExploitability());
        $copyObj->setCvssRemediationlevel($this->getCvssRemediationlevel());
        $copyObj->setCvssReportconfidence($this->getCvssReportconfidence());
        $copyObj->setCvssCollateraldamagepotential($this->getCvssCollateraldamagepotential());
        $copyObj->setCvssTargetdistribution($this->getCvssTargetdistribution());
        $copyObj->setCvssConfidentialityrequirement($this->getCvssConfidentialityrequirement());
        $copyObj->setCvssIntegrityrequirement($this->getCvssIntegrityrequirement());
        $copyObj->setCvssAvailabilityrequirement($this->getCvssAvailabilityrequirement());
        $copyObj->setDreadDamagepotential($this->getDreadDamagepotential());
        $copyObj->setDreadReproducibility($this->getDreadReproducibility());
        $copyObj->setDreadExploitability($this->getDreadExploitability());
        $copyObj->setDreadAffectedusers($this->getDreadAffectedusers());
        $copyObj->setDreadDiscoverability($this->getDreadDiscoverability());
        $copyObj->setOwaspSkilllevel($this->getOwaspSkilllevel());
        $copyObj->setOwaspMotive($this->getOwaspMotive());
        $copyObj->setOwaspOpportunity($this->getOwaspOpportunity());
        $copyObj->setOwaspSize($this->getOwaspSize());
        $copyObj->setOwaspEaseofdiscovery($this->getOwaspEaseofdiscovery());
        $copyObj->setOwaspEaseofexploit($this->getOwaspEaseofexploit());
        $copyObj->setOwaspAwareness($this->getOwaspAwareness());
        $copyObj->setOwaspIntrusiondetection($this->getOwaspIntrusiondetection());
        $copyObj->setOwaspLossofconfidentiality($this->getOwaspLossofconfidentiality());
        $copyObj->setOwaspLossofintegrity($this->getOwaspLossofintegrity());
        $copyObj->setOwaspLossofavailability($this->getOwaspLossofavailability());
        $copyObj->setOwaspLossofaccountability($this->getOwaspLossofaccountability());
        $copyObj->setOwaspFinancialdamage($this->getOwaspFinancialdamage());
        $copyObj->setOwaspReputationdamage($this->getOwaspReputationdamage());
        $copyObj->setOwaspNoncompliance($this->getOwaspNoncompliance());
        $copyObj->setOwaspPrivacyviolation($this->getOwaspPrivacyviolation());
        $copyObj->setCustom($this->getCustom());
        if ($makeNew) {
            $copyObj->setNew(true);
        }
    }

    /**
     * Makes a copy of this object that will be inserted as a new row in table when saved.
     * It creates a new object filling in the simple attributes, but skipping any primary
     * keys that are defined for the table.
     *
     * If desired, this method can also make copies of all associated (fkey referrers)
     * objects.
     *
     * @param  boolean $deepCopy Whether to also copy all rows that refer (by fkey) to the current row.
     * @return \RiskScoring Clone of current object.
     * @throws PropelException
     */
    public function copy($deepCopy = false)
    {
        // we use get_class(), because this might be a subclass
        $clazz = get_class($this);
        $copyObj = new $clazz();
        $this->copyInto($copyObj, $deepCopy);

        return $copyObj;
    }

    /**
     * Clears the current object, sets all attributes to their default values and removes
     * outgoing references as well as back-references (from other objects to this one. Results probably in a database
     * change of those foreign objects when you call `save` there).
     */
    public function clear()
    {
        $this->id = null;
        $this->scoring_method = null;
        $this->calculated_risk = null;
        $this->classic_likelihood = null;
        $this->classic_impact = null;
        $this->cvss_accessvector = null;
        $this->cvss_accesscomplexity = null;
        $this->cvss_authentication = null;
        $this->cvss_confimpact = null;
        $this->cvss_integimpact = null;
        $this->cvss_availimpact = null;
        $this->cvss_exploitability = null;
        $this->cvss_remediationlevel = null;
        $this->cvss_reportconfidence = null;
        $this->cvss_collateraldamagepotential = null;
        $this->cvss_targetdistribution = null;
        $this->cvss_confidentialityrequirement = null;
        $this->cvss_integrityrequirement = null;
        $this->cvss_availabilityrequirement = null;
        $this->dread_damagepotential = null;
        $this->dread_reproducibility = null;
        $this->dread_exploitability = null;
        $this->dread_affectedusers = null;
        $this->dread_discoverability = null;
        $this->owasp_skilllevel = null;
        $this->owasp_motive = null;
        $this->owasp_opportunity = null;
        $this->owasp_size = null;
        $this->owasp_easeofdiscovery = null;
        $this->owasp_easeofexploit = null;
        $this->owasp_awareness = null;
        $this->owasp_intrusiondetection = null;
        $this->owasp_lossofconfidentiality = null;
        $this->owasp_lossofintegrity = null;
        $this->owasp_lossofavailability = null;
        $this->owasp_lossofaccountability = null;
        $this->owasp_financialdamage = null;
        $this->owasp_reputationdamage = null;
        $this->owasp_noncompliance = null;
        $this->owasp_privacyviolation = null;
        $this->custom = null;
        $this->alreadyInSave = false;
        $this->clearAllReferences();
        $this->applyDefaultValues();
        $this->resetModified();
        $this->setNew(true);
        $this->setDeleted(false);
    }

    /**
     * Resets all references and back-references to other model objects or collections of model objects.
     *
     * This method is used to reset all php object references (not the actual reference in the database).
     * Necessary for object serialisation.
     *
     * @param      boolean $deep Whether to also clear the references on all referrer objects.
     */
    public function clearAllReferences($deep = false)
    {
        if ($deep) {
        } // if ($deep)

    }

    /**
     * Return the string representation of this object
     *
     * @return string
     */
    public function __toString()
    {
        return (string) $this->exportTo(RiskScoringTableMap::DEFAULT_STRING_FORMAT);
    }

    /**
     * Code to be run before persisting the object
     * @param  ConnectionInterface $con
     * @return boolean
     */
    public function preSave(ConnectionInterface $con = null)
    {
        return true;
    }

    /**
     * Code to be run after persisting the object
     * @param ConnectionInterface $con
     */
    public function postSave(ConnectionInterface $con = null)
    {

    }

    /**
     * Code to be run before inserting to database
     * @param  ConnectionInterface $con
     * @return boolean
     */
    public function preInsert(ConnectionInterface $con = null)
    {
        return true;
    }

    /**
     * Code to be run after inserting to database
     * @param ConnectionInterface $con
     */
    public function postInsert(ConnectionInterface $con = null)
    {

    }

    /**
     * Code to be run before updating the object in database
     * @param  ConnectionInterface $con
     * @return boolean
     */
    public function preUpdate(ConnectionInterface $con = null)
    {
        return true;
    }

    /**
     * Code to be run after updating the object in database
     * @param ConnectionInterface $con
     */
    public function postUpdate(ConnectionInterface $con = null)
    {

    }

    /**
     * Code to be run before deleting the object in database
     * @param  ConnectionInterface $con
     * @return boolean
     */
    public function preDelete(ConnectionInterface $con = null)
    {
        return true;
    }

    /**
     * Code to be run after deleting the object in database
     * @param ConnectionInterface $con
     */
    public function postDelete(ConnectionInterface $con = null)
    {

    }


    /**
     * Derived method to catches calls to undefined methods.
     *
     * Provides magic import/export method support (fromXML()/toXML(), fromYAML()/toYAML(), etc.).
     * Allows to define default __call() behavior if you overwrite __call()
     *
     * @param string $name
     * @param mixed  $params
     *
     * @return array|string
     */
    public function __call($name, $params)
    {
        if (0 === strpos($name, 'get')) {
            $virtualColumn = substr($name, 3);
            if ($this->hasVirtualColumn($virtualColumn)) {
                return $this->getVirtualColumn($virtualColumn);
            }

            $virtualColumn = lcfirst($virtualColumn);
            if ($this->hasVirtualColumn($virtualColumn)) {
                return $this->getVirtualColumn($virtualColumn);
            }
        }

        if (0 === strpos($name, 'from')) {
            $format = substr($name, 4);

            return $this->importFrom($format, reset($params));
        }

        if (0 === strpos($name, 'to')) {
            $format = substr($name, 2);
            $includeLazyLoadColumns = isset($params[0]) ? $params[0] : true;

            return $this->exportTo($format, $includeLazyLoadColumns);
        }

        throw new BadMethodCallException(sprintf('Call to undefined method: %s.', $name));
    }

}
