<?php

namespace Base;

use \RisksQuery as ChildRisksQuery;
use \DateTime;
use \Exception;
use \PDO;
use Map\RisksTableMap;
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
use Propel\Runtime\Util\PropelDateTime;

/**
 * Base class that represents a row from the 'risks' table.
 *
 *
 *
* @package    propel.generator..Base
*/
abstract class Risks implements ActiveRecordInterface
{
    /**
     * TableMap class name
     */
    const TABLE_MAP = '\\Map\\RisksTableMap';


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
     * The value for the status field.
     * @var        string
     */
    protected $status;

    /**
     * The value for the subject field.
     * @var        string
     */
    protected $subject;

    /**
     * The value for the reference_id field.
     * Note: this column has a database default value of: ''
     * @var        string
     */
    protected $reference_id;

    /**
     * The value for the regulation field.
     * @var        int
     */
    protected $regulation;

    /**
     * The value for the control_number field.
     * @var        string
     */
    protected $control_number;

    /**
     * The value for the location field.
     * @var        int
     */
    protected $location;

    /**
     * The value for the category field.
     * @var        int
     */
    protected $category;

    /**
     * The value for the team field.
     * @var        int
     */
    protected $team;

    /**
     * The value for the technology field.
     * @var        int
     */
    protected $technology;

    /**
     * The value for the owner field.
     * @var        int
     */
    protected $owner;

    /**
     * The value for the manager field.
     * @var        int
     */
    protected $manager;

    /**
     * The value for the assessment field.
     * @var        string
     */
    protected $assessment;

    /**
     * The value for the notes field.
     * @var        string
     */
    protected $notes;

    /**
     * The value for the submission_date field.
     * Note: this column has a database default value of: (expression) CURRENT_TIMESTAMP
     * @var        \DateTime
     */
    protected $submission_date;

    /**
     * The value for the last_update field.
     * Note: this column has a database default value of: NULL
     * @var        \DateTime
     */
    protected $last_update;

    /**
     * The value for the review_date field.
     * Note: this column has a database default value of: NULL
     * @var        \DateTime
     */
    protected $review_date;

    /**
     * The value for the mitigation_id field.
     * @var        int
     */
    protected $mitigation_id;

    /**
     * The value for the mgmt_review field.
     * @var        int
     */
    protected $mgmt_review;

    /**
     * The value for the project_id field.
     * Note: this column has a database default value of: 0
     * @var        int
     */
    protected $project_id;

    /**
     * The value for the close_id field.
     * @var        int
     */
    protected $close_id;

    /**
     * The value for the submitted_by field.
     * Note: this column has a database default value of: 1
     * @var        int
     */
    protected $submitted_by;

    /**
     * The value for the parent_id field.
     * @var        int
     */
    protected $parent_id;

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
        $this->reference_id = '';
        $this->last_update = PropelDateTime::newInstance(NULL, null, 'DateTime');
        $this->review_date = PropelDateTime::newInstance(NULL, null, 'DateTime');
        $this->project_id = 0;
        $this->submitted_by = 1;
    }

    /**
     * Initializes internal state of Base\Risks object.
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
     * Compares this with another <code>Risks</code> instance.  If
     * <code>obj</code> is an instance of <code>Risks</code>, delegates to
     * <code>equals(Risks)</code>.  Otherwise, returns <code>false</code>.
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
     * @return $this|Risks The current object, for fluid interface
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
     * Get the [status] column value.
     *
     * @return string
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Get the [subject] column value.
     *
     * @return string
     */
    public function getSubject()
    {
        return $this->subject;
    }

    /**
     * Get the [reference_id] column value.
     *
     * @return string
     */
    public function getReferenceId()
    {
        return $this->reference_id;
    }

    /**
     * Get the [regulation] column value.
     *
     * @return int
     */
    public function getRegulation()
    {
        return $this->regulation;
    }

    /**
     * Get the [control_number] column value.
     *
     * @return string
     */
    public function getControlNumber()
    {
        return $this->control_number;
    }

    /**
     * Get the [location] column value.
     *
     * @return int
     */
    public function getLocation()
    {
        return $this->location;
    }

    /**
     * Get the [category] column value.
     *
     * @return int
     */
    public function getCategory()
    {
        return $this->category;
    }

    /**
     * Get the [team] column value.
     *
     * @return int
     */
    public function getTeam()
    {
        return $this->team;
    }

    /**
     * Get the [technology] column value.
     *
     * @return int
     */
    public function getTechnology()
    {
        return $this->technology;
    }

    /**
     * Get the [owner] column value.
     *
     * @return int
     */
    public function getOwner()
    {
        return $this->owner;
    }

    /**
     * Get the [manager] column value.
     *
     * @return int
     */
    public function getManager()
    {
        return $this->manager;
    }

    /**
     * Get the [assessment] column value.
     *
     * @return string
     */
    public function getAssessment()
    {
        return $this->assessment;
    }

    /**
     * Get the [notes] column value.
     *
     * @return string
     */
    public function getNotes()
    {
        return $this->notes;
    }

    /**
     * Get the [optionally formatted] temporal [submission_date] column value.
     *
     *
     * @param      string $format The date/time format string (either date()-style or strftime()-style).
     *                            If format is NULL, then the raw DateTime object will be returned.
     *
     * @return string|DateTime Formatted date/time value as string or DateTime object (if format is NULL), NULL if column is NULL, and 0 if column value is 0000-00-00 00:00:00
     *
     * @throws PropelException - if unable to parse/validate the date/time value.
     */
    public function getSubmissionDate($format = NULL)
    {
        if ($format === null) {
            return $this->submission_date;
        } else {
            return $this->submission_date instanceof \DateTime ? $this->submission_date->format($format) : null;
        }
    }

    /**
     * Get the [optionally formatted] temporal [last_update] column value.
     *
     *
     * @param      string $format The date/time format string (either date()-style or strftime()-style).
     *                            If format is NULL, then the raw DateTime object will be returned.
     *
     * @return string|DateTime Formatted date/time value as string or DateTime object (if format is NULL), NULL if column is NULL, and 0 if column value is 0000-00-00 00:00:00
     *
     * @throws PropelException - if unable to parse/validate the date/time value.
     */
    public function getLastUpdate($format = NULL)
    {
        if ($format === null) {
            return $this->last_update;
        } else {
            return $this->last_update instanceof \DateTime ? $this->last_update->format($format) : null;
        }
    }

    /**
     * Get the [optionally formatted] temporal [review_date] column value.
     *
     *
     * @param      string $format The date/time format string (either date()-style or strftime()-style).
     *                            If format is NULL, then the raw DateTime object will be returned.
     *
     * @return string|DateTime Formatted date/time value as string or DateTime object (if format is NULL), NULL if column is NULL, and 0 if column value is 0000-00-00 00:00:00
     *
     * @throws PropelException - if unable to parse/validate the date/time value.
     */
    public function getReviewDate($format = NULL)
    {
        if ($format === null) {
            return $this->review_date;
        } else {
            return $this->review_date instanceof \DateTime ? $this->review_date->format($format) : null;
        }
    }

    /**
     * Get the [mitigation_id] column value.
     *
     * @return int
     */
    public function getMitigationId()
    {
        return $this->mitigation_id;
    }

    /**
     * Get the [mgmt_review] column value.
     *
     * @return int
     */
    public function getMgmtReview()
    {
        return $this->mgmt_review;
    }

    /**
     * Get the [project_id] column value.
     *
     * @return int
     */
    public function getProjectId()
    {
        return $this->project_id;
    }

    /**
     * Get the [close_id] column value.
     *
     * @return int
     */
    public function getCloseId()
    {
        return $this->close_id;
    }

    /**
     * Get the [submitted_by] column value.
     *
     * @return int
     */
    public function getSubmittedBy()
    {
        return $this->submitted_by;
    }

    /**
     * Get the [parent_id] column value.
     *
     * @return int
     */
    public function getParentId()
    {
        return $this->parent_id;
    }

    /**
     * Set the value of [id] column.
     *
     * @param  int $v new value
     * @return $this|\Risks The current object (for fluent API support)
     */
    public function setId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->id !== $v) {
            $this->id = $v;
            $this->modifiedColumns[RisksTableMap::COL_ID] = true;
        }

        return $this;
    } // setId()

    /**
     * Set the value of [status] column.
     *
     * @param  string $v new value
     * @return $this|\Risks The current object (for fluent API support)
     */
    public function setStatus($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->status !== $v) {
            $this->status = $v;
            $this->modifiedColumns[RisksTableMap::COL_STATUS] = true;
        }

        return $this;
    } // setStatus()

    /**
     * Set the value of [subject] column.
     *
     * @param  string $v new value
     * @return $this|\Risks The current object (for fluent API support)
     */
    public function setSubject($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->subject !== $v) {
            $this->subject = $v;
            $this->modifiedColumns[RisksTableMap::COL_SUBJECT] = true;
        }

        return $this;
    } // setSubject()

    /**
     * Set the value of [reference_id] column.
     *
     * @param  string $v new value
     * @return $this|\Risks The current object (for fluent API support)
     */
    public function setReferenceId($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->reference_id !== $v) {
            $this->reference_id = $v;
            $this->modifiedColumns[RisksTableMap::COL_REFERENCE_ID] = true;
        }

        return $this;
    } // setReferenceId()

    /**
     * Set the value of [regulation] column.
     *
     * @param  int $v new value
     * @return $this|\Risks The current object (for fluent API support)
     */
    public function setRegulation($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->regulation !== $v) {
            $this->regulation = $v;
            $this->modifiedColumns[RisksTableMap::COL_REGULATION] = true;
        }

        return $this;
    } // setRegulation()

    /**
     * Set the value of [control_number] column.
     *
     * @param  string $v new value
     * @return $this|\Risks The current object (for fluent API support)
     */
    public function setControlNumber($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->control_number !== $v) {
            $this->control_number = $v;
            $this->modifiedColumns[RisksTableMap::COL_CONTROL_NUMBER] = true;
        }

        return $this;
    } // setControlNumber()

    /**
     * Set the value of [location] column.
     *
     * @param  int $v new value
     * @return $this|\Risks The current object (for fluent API support)
     */
    public function setLocation($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->location !== $v) {
            $this->location = $v;
            $this->modifiedColumns[RisksTableMap::COL_LOCATION] = true;
        }

        return $this;
    } // setLocation()

    /**
     * Set the value of [category] column.
     *
     * @param  int $v new value
     * @return $this|\Risks The current object (for fluent API support)
     */
    public function setCategory($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->category !== $v) {
            $this->category = $v;
            $this->modifiedColumns[RisksTableMap::COL_CATEGORY] = true;
        }

        return $this;
    } // setCategory()

    /**
     * Set the value of [team] column.
     *
     * @param  int $v new value
     * @return $this|\Risks The current object (for fluent API support)
     */
    public function setTeam($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->team !== $v) {
            $this->team = $v;
            $this->modifiedColumns[RisksTableMap::COL_TEAM] = true;
        }

        return $this;
    } // setTeam()

    /**
     * Set the value of [technology] column.
     *
     * @param  int $v new value
     * @return $this|\Risks The current object (for fluent API support)
     */
    public function setTechnology($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->technology !== $v) {
            $this->technology = $v;
            $this->modifiedColumns[RisksTableMap::COL_TECHNOLOGY] = true;
        }

        return $this;
    } // setTechnology()

    /**
     * Set the value of [owner] column.
     *
     * @param  int $v new value
     * @return $this|\Risks The current object (for fluent API support)
     */
    public function setOwner($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->owner !== $v) {
            $this->owner = $v;
            $this->modifiedColumns[RisksTableMap::COL_OWNER] = true;
        }

        return $this;
    } // setOwner()

    /**
     * Set the value of [manager] column.
     *
     * @param  int $v new value
     * @return $this|\Risks The current object (for fluent API support)
     */
    public function setManager($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->manager !== $v) {
            $this->manager = $v;
            $this->modifiedColumns[RisksTableMap::COL_MANAGER] = true;
        }

        return $this;
    } // setManager()

    /**
     * Set the value of [assessment] column.
     *
     * @param  string $v new value
     * @return $this|\Risks The current object (for fluent API support)
     */
    public function setAssessment($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->assessment !== $v) {
            $this->assessment = $v;
            $this->modifiedColumns[RisksTableMap::COL_ASSESSMENT] = true;
        }

        return $this;
    } // setAssessment()

    /**
     * Set the value of [notes] column.
     *
     * @param  string $v new value
     * @return $this|\Risks The current object (for fluent API support)
     */
    public function setNotes($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->notes !== $v) {
            $this->notes = $v;
            $this->modifiedColumns[RisksTableMap::COL_NOTES] = true;
        }

        return $this;
    } // setNotes()

    /**
     * Sets the value of [submission_date] column to a normalized version of the date/time value specified.
     *
     * @param  mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return $this|\Risks The current object (for fluent API support)
     */
    public function setSubmissionDate($v)
    {
        $dt = PropelDateTime::newInstance($v, null, 'DateTime');
        if ($this->submission_date !== null || $dt !== null) {
            if ($dt !== $this->submission_date) {
                $this->submission_date = $dt;
                $this->modifiedColumns[RisksTableMap::COL_SUBMISSION_DATE] = true;
            }
        } // if either are not null

        return $this;
    } // setSubmissionDate()

    /**
     * Sets the value of [last_update] column to a normalized version of the date/time value specified.
     *
     * @param  mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return $this|\Risks The current object (for fluent API support)
     */
    public function setLastUpdate($v)
    {
        $dt = PropelDateTime::newInstance($v, null, 'DateTime');
        if ($this->last_update !== null || $dt !== null) {
            if ( ($dt != $this->last_update) // normalized values don't match
                || ($dt->format('Y-m-d H:i:s') === NULL) // or the entered value matches the default
                 ) {
                $this->last_update = $dt;
                $this->modifiedColumns[RisksTableMap::COL_LAST_UPDATE] = true;
            }
        } // if either are not null

        return $this;
    } // setLastUpdate()

    /**
     * Sets the value of [review_date] column to a normalized version of the date/time value specified.
     *
     * @param  mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return $this|\Risks The current object (for fluent API support)
     */
    public function setReviewDate($v)
    {
        $dt = PropelDateTime::newInstance($v, null, 'DateTime');
        if ($this->review_date !== null || $dt !== null) {
            if ( ($dt != $this->review_date) // normalized values don't match
                || ($dt->format('Y-m-d H:i:s') === NULL) // or the entered value matches the default
                 ) {
                $this->review_date = $dt;
                $this->modifiedColumns[RisksTableMap::COL_REVIEW_DATE] = true;
            }
        } // if either are not null

        return $this;
    } // setReviewDate()

    /**
     * Set the value of [mitigation_id] column.
     *
     * @param  int $v new value
     * @return $this|\Risks The current object (for fluent API support)
     */
    public function setMitigationId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->mitigation_id !== $v) {
            $this->mitigation_id = $v;
            $this->modifiedColumns[RisksTableMap::COL_MITIGATION_ID] = true;
        }

        return $this;
    } // setMitigationId()

    /**
     * Set the value of [mgmt_review] column.
     *
     * @param  int $v new value
     * @return $this|\Risks The current object (for fluent API support)
     */
    public function setMgmtReview($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->mgmt_review !== $v) {
            $this->mgmt_review = $v;
            $this->modifiedColumns[RisksTableMap::COL_MGMT_REVIEW] = true;
        }

        return $this;
    } // setMgmtReview()

    /**
     * Set the value of [project_id] column.
     *
     * @param  int $v new value
     * @return $this|\Risks The current object (for fluent API support)
     */
    public function setProjectId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->project_id !== $v) {
            $this->project_id = $v;
            $this->modifiedColumns[RisksTableMap::COL_PROJECT_ID] = true;
        }

        return $this;
    } // setProjectId()

    /**
     * Set the value of [close_id] column.
     *
     * @param  int $v new value
     * @return $this|\Risks The current object (for fluent API support)
     */
    public function setCloseId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->close_id !== $v) {
            $this->close_id = $v;
            $this->modifiedColumns[RisksTableMap::COL_CLOSE_ID] = true;
        }

        return $this;
    } // setCloseId()

    /**
     * Set the value of [submitted_by] column.
     *
     * @param  int $v new value
     * @return $this|\Risks The current object (for fluent API support)
     */
    public function setSubmittedBy($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->submitted_by !== $v) {
            $this->submitted_by = $v;
            $this->modifiedColumns[RisksTableMap::COL_SUBMITTED_BY] = true;
        }

        return $this;
    } // setSubmittedBy()

    /**
     * Set the value of [parent_id] column.
     *
     * @param  int $v new value
     * @return $this|\Risks The current object (for fluent API support)
     */
    public function setParentId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->parent_id !== $v) {
            $this->parent_id = $v;
            $this->modifiedColumns[RisksTableMap::COL_PARENT_ID] = true;
        }

        return $this;
    } // setParentId()

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
            if ($this->reference_id !== '') {
                return false;
            }

            if ($this->last_update && $this->last_update->format('Y-m-d H:i:s') !== NULL) {
                return false;
            }

            if ($this->review_date && $this->review_date->format('Y-m-d H:i:s') !== NULL) {
                return false;
            }

            if ($this->project_id !== 0) {
                return false;
            }

            if ($this->submitted_by !== 1) {
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

            $col = $row[TableMap::TYPE_NUM == $indexType ? 0 + $startcol : RisksTableMap::translateFieldName('Id', TableMap::TYPE_PHPNAME, $indexType)];
            $this->id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 1 + $startcol : RisksTableMap::translateFieldName('Status', TableMap::TYPE_PHPNAME, $indexType)];
            $this->status = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 2 + $startcol : RisksTableMap::translateFieldName('Subject', TableMap::TYPE_PHPNAME, $indexType)];
            $this->subject = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 3 + $startcol : RisksTableMap::translateFieldName('ReferenceId', TableMap::TYPE_PHPNAME, $indexType)];
            $this->reference_id = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 4 + $startcol : RisksTableMap::translateFieldName('Regulation', TableMap::TYPE_PHPNAME, $indexType)];
            $this->regulation = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 5 + $startcol : RisksTableMap::translateFieldName('ControlNumber', TableMap::TYPE_PHPNAME, $indexType)];
            $this->control_number = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 6 + $startcol : RisksTableMap::translateFieldName('Location', TableMap::TYPE_PHPNAME, $indexType)];
            $this->location = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 7 + $startcol : RisksTableMap::translateFieldName('Category', TableMap::TYPE_PHPNAME, $indexType)];
            $this->category = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 8 + $startcol : RisksTableMap::translateFieldName('Team', TableMap::TYPE_PHPNAME, $indexType)];
            $this->team = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 9 + $startcol : RisksTableMap::translateFieldName('Technology', TableMap::TYPE_PHPNAME, $indexType)];
            $this->technology = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 10 + $startcol : RisksTableMap::translateFieldName('Owner', TableMap::TYPE_PHPNAME, $indexType)];
            $this->owner = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 11 + $startcol : RisksTableMap::translateFieldName('Manager', TableMap::TYPE_PHPNAME, $indexType)];
            $this->manager = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 12 + $startcol : RisksTableMap::translateFieldName('Assessment', TableMap::TYPE_PHPNAME, $indexType)];
            $this->assessment = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 13 + $startcol : RisksTableMap::translateFieldName('Notes', TableMap::TYPE_PHPNAME, $indexType)];
            $this->notes = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 14 + $startcol : RisksTableMap::translateFieldName('SubmissionDate', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->submission_date = (null !== $col) ? PropelDateTime::newInstance($col, null, 'DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 15 + $startcol : RisksTableMap::translateFieldName('LastUpdate', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->last_update = (null !== $col) ? PropelDateTime::newInstance($col, null, 'DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 16 + $startcol : RisksTableMap::translateFieldName('ReviewDate', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->review_date = (null !== $col) ? PropelDateTime::newInstance($col, null, 'DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 17 + $startcol : RisksTableMap::translateFieldName('MitigationId', TableMap::TYPE_PHPNAME, $indexType)];
            $this->mitigation_id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 18 + $startcol : RisksTableMap::translateFieldName('MgmtReview', TableMap::TYPE_PHPNAME, $indexType)];
            $this->mgmt_review = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 19 + $startcol : RisksTableMap::translateFieldName('ProjectId', TableMap::TYPE_PHPNAME, $indexType)];
            $this->project_id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 20 + $startcol : RisksTableMap::translateFieldName('CloseId', TableMap::TYPE_PHPNAME, $indexType)];
            $this->close_id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 21 + $startcol : RisksTableMap::translateFieldName('SubmittedBy', TableMap::TYPE_PHPNAME, $indexType)];
            $this->submitted_by = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 22 + $startcol : RisksTableMap::translateFieldName('ParentId', TableMap::TYPE_PHPNAME, $indexType)];
            $this->parent_id = (null !== $col) ? (int) $col : null;
            $this->resetModified();

            $this->setNew(false);

            if ($rehydrate) {
                $this->ensureConsistency();
            }

            return $startcol + 23; // 23 = RisksTableMap::NUM_HYDRATE_COLUMNS.

        } catch (Exception $e) {
            throw new PropelException(sprintf('Error populating %s object', '\\Risks'), 0, $e);
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
            $con = Propel::getServiceContainer()->getReadConnection(RisksTableMap::DATABASE_NAME);
        }

        // We don't need to alter the object instance pool; we're just modifying this instance
        // already in the pool.

        $dataFetcher = ChildRisksQuery::create(null, $this->buildPkeyCriteria())->setFormatter(ModelCriteria::FORMAT_STATEMENT)->find($con);
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
     * @see Risks::setDeleted()
     * @see Risks::isDeleted()
     */
    public function delete(ConnectionInterface $con = null)
    {
        if ($this->isDeleted()) {
            throw new PropelException("This object has already been deleted.");
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getWriteConnection(RisksTableMap::DATABASE_NAME);
        }

        $con->transaction(function () use ($con) {
            $deleteQuery = ChildRisksQuery::create()
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
            $con = Propel::getServiceContainer()->getWriteConnection(RisksTableMap::DATABASE_NAME);
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
                RisksTableMap::addInstanceToPool($this);
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

        $this->modifiedColumns[RisksTableMap::COL_ID] = true;
        if (null !== $this->id) {
            throw new PropelException('Cannot insert a value for auto-increment primary key (' . RisksTableMap::COL_ID . ')');
        }

         // check the columns in natural order for more readable SQL queries
        if ($this->isColumnModified(RisksTableMap::COL_ID)) {
            $modifiedColumns[':p' . $index++]  = 'id';
        }
        if ($this->isColumnModified(RisksTableMap::COL_STATUS)) {
            $modifiedColumns[':p' . $index++]  = 'status';
        }
        if ($this->isColumnModified(RisksTableMap::COL_SUBJECT)) {
            $modifiedColumns[':p' . $index++]  = 'subject';
        }
        if ($this->isColumnModified(RisksTableMap::COL_REFERENCE_ID)) {
            $modifiedColumns[':p' . $index++]  = 'reference_id';
        }
        if ($this->isColumnModified(RisksTableMap::COL_REGULATION)) {
            $modifiedColumns[':p' . $index++]  = 'regulation';
        }
        if ($this->isColumnModified(RisksTableMap::COL_CONTROL_NUMBER)) {
            $modifiedColumns[':p' . $index++]  = 'control_number';
        }
        if ($this->isColumnModified(RisksTableMap::COL_LOCATION)) {
            $modifiedColumns[':p' . $index++]  = 'location';
        }
        if ($this->isColumnModified(RisksTableMap::COL_CATEGORY)) {
            $modifiedColumns[':p' . $index++]  = 'category';
        }
        if ($this->isColumnModified(RisksTableMap::COL_TEAM)) {
            $modifiedColumns[':p' . $index++]  = 'team';
        }
        if ($this->isColumnModified(RisksTableMap::COL_TECHNOLOGY)) {
            $modifiedColumns[':p' . $index++]  = 'technology';
        }
        if ($this->isColumnModified(RisksTableMap::COL_OWNER)) {
            $modifiedColumns[':p' . $index++]  = 'owner';
        }
        if ($this->isColumnModified(RisksTableMap::COL_MANAGER)) {
            $modifiedColumns[':p' . $index++]  = 'manager';
        }
        if ($this->isColumnModified(RisksTableMap::COL_ASSESSMENT)) {
            $modifiedColumns[':p' . $index++]  = 'assessment';
        }
        if ($this->isColumnModified(RisksTableMap::COL_NOTES)) {
            $modifiedColumns[':p' . $index++]  = 'notes';
        }
        if ($this->isColumnModified(RisksTableMap::COL_SUBMISSION_DATE)) {
            $modifiedColumns[':p' . $index++]  = 'submission_date';
        }
        if ($this->isColumnModified(RisksTableMap::COL_LAST_UPDATE)) {
            $modifiedColumns[':p' . $index++]  = 'last_update';
        }
        if ($this->isColumnModified(RisksTableMap::COL_REVIEW_DATE)) {
            $modifiedColumns[':p' . $index++]  = 'review_date';
        }
        if ($this->isColumnModified(RisksTableMap::COL_MITIGATION_ID)) {
            $modifiedColumns[':p' . $index++]  = 'mitigation_id';
        }
        if ($this->isColumnModified(RisksTableMap::COL_MGMT_REVIEW)) {
            $modifiedColumns[':p' . $index++]  = 'mgmt_review';
        }
        if ($this->isColumnModified(RisksTableMap::COL_PROJECT_ID)) {
            $modifiedColumns[':p' . $index++]  = 'project_id';
        }
        if ($this->isColumnModified(RisksTableMap::COL_CLOSE_ID)) {
            $modifiedColumns[':p' . $index++]  = 'close_id';
        }
        if ($this->isColumnModified(RisksTableMap::COL_SUBMITTED_BY)) {
            $modifiedColumns[':p' . $index++]  = 'submitted_by';
        }
        if ($this->isColumnModified(RisksTableMap::COL_PARENT_ID)) {
            $modifiedColumns[':p' . $index++]  = 'parent_id';
        }

        $sql = sprintf(
            'INSERT INTO risks (%s) VALUES (%s)',
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
                    case 'status':
                        $stmt->bindValue($identifier, $this->status, PDO::PARAM_STR);
                        break;
                    case 'subject':
                        $stmt->bindValue($identifier, $this->subject, PDO::PARAM_STR);
                        break;
                    case 'reference_id':
                        $stmt->bindValue($identifier, $this->reference_id, PDO::PARAM_STR);
                        break;
                    case 'regulation':
                        $stmt->bindValue($identifier, $this->regulation, PDO::PARAM_INT);
                        break;
                    case 'control_number':
                        $stmt->bindValue($identifier, $this->control_number, PDO::PARAM_STR);
                        break;
                    case 'location':
                        $stmt->bindValue($identifier, $this->location, PDO::PARAM_INT);
                        break;
                    case 'category':
                        $stmt->bindValue($identifier, $this->category, PDO::PARAM_INT);
                        break;
                    case 'team':
                        $stmt->bindValue($identifier, $this->team, PDO::PARAM_INT);
                        break;
                    case 'technology':
                        $stmt->bindValue($identifier, $this->technology, PDO::PARAM_INT);
                        break;
                    case 'owner':
                        $stmt->bindValue($identifier, $this->owner, PDO::PARAM_INT);
                        break;
                    case 'manager':
                        $stmt->bindValue($identifier, $this->manager, PDO::PARAM_INT);
                        break;
                    case 'assessment':
                        $stmt->bindValue($identifier, $this->assessment, PDO::PARAM_STR);
                        break;
                    case 'notes':
                        $stmt->bindValue($identifier, $this->notes, PDO::PARAM_STR);
                        break;
                    case 'submission_date':
                        $stmt->bindValue($identifier, $this->submission_date ? $this->submission_date->format("Y-m-d H:i:s") : null, PDO::PARAM_STR);
                        break;
                    case 'last_update':
                        $stmt->bindValue($identifier, $this->last_update ? $this->last_update->format("Y-m-d H:i:s") : null, PDO::PARAM_STR);
                        break;
                    case 'review_date':
                        $stmt->bindValue($identifier, $this->review_date ? $this->review_date->format("Y-m-d H:i:s") : null, PDO::PARAM_STR);
                        break;
                    case 'mitigation_id':
                        $stmt->bindValue($identifier, $this->mitigation_id, PDO::PARAM_INT);
                        break;
                    case 'mgmt_review':
                        $stmt->bindValue($identifier, $this->mgmt_review, PDO::PARAM_INT);
                        break;
                    case 'project_id':
                        $stmt->bindValue($identifier, $this->project_id, PDO::PARAM_INT);
                        break;
                    case 'close_id':
                        $stmt->bindValue($identifier, $this->close_id, PDO::PARAM_INT);
                        break;
                    case 'submitted_by':
                        $stmt->bindValue($identifier, $this->submitted_by, PDO::PARAM_INT);
                        break;
                    case 'parent_id':
                        $stmt->bindValue($identifier, $this->parent_id, PDO::PARAM_INT);
                        break;
                }
            }
            $stmt->execute();
        } catch (Exception $e) {
            Propel::log($e->getMessage(), Propel::LOG_ERR);
            throw new PropelException(sprintf('Unable to execute INSERT statement [%s]', $sql), 0, $e);
        }

        try {
            $pk = $con->lastInsertId();
        } catch (Exception $e) {
            throw new PropelException('Unable to get autoincrement id.', 0, $e);
        }
        $this->setId($pk);

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
        $pos = RisksTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);
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
                return $this->getStatus();
                break;
            case 2:
                return $this->getSubject();
                break;
            case 3:
                return $this->getReferenceId();
                break;
            case 4:
                return $this->getRegulation();
                break;
            case 5:
                return $this->getControlNumber();
                break;
            case 6:
                return $this->getLocation();
                break;
            case 7:
                return $this->getCategory();
                break;
            case 8:
                return $this->getTeam();
                break;
            case 9:
                return $this->getTechnology();
                break;
            case 10:
                return $this->getOwner();
                break;
            case 11:
                return $this->getManager();
                break;
            case 12:
                return $this->getAssessment();
                break;
            case 13:
                return $this->getNotes();
                break;
            case 14:
                return $this->getSubmissionDate();
                break;
            case 15:
                return $this->getLastUpdate();
                break;
            case 16:
                return $this->getReviewDate();
                break;
            case 17:
                return $this->getMitigationId();
                break;
            case 18:
                return $this->getMgmtReview();
                break;
            case 19:
                return $this->getProjectId();
                break;
            case 20:
                return $this->getCloseId();
                break;
            case 21:
                return $this->getSubmittedBy();
                break;
            case 22:
                return $this->getParentId();
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

        if (isset($alreadyDumpedObjects['Risks'][$this->hashCode()])) {
            return '*RECURSION*';
        }
        $alreadyDumpedObjects['Risks'][$this->hashCode()] = true;
        $keys = RisksTableMap::getFieldNames($keyType);
        $result = array(
            $keys[0] => $this->getId(),
            $keys[1] => $this->getStatus(),
            $keys[2] => $this->getSubject(),
            $keys[3] => $this->getReferenceId(),
            $keys[4] => $this->getRegulation(),
            $keys[5] => $this->getControlNumber(),
            $keys[6] => $this->getLocation(),
            $keys[7] => $this->getCategory(),
            $keys[8] => $this->getTeam(),
            $keys[9] => $this->getTechnology(),
            $keys[10] => $this->getOwner(),
            $keys[11] => $this->getManager(),
            $keys[12] => $this->getAssessment(),
            $keys[13] => $this->getNotes(),
            $keys[14] => $this->getSubmissionDate(),
            $keys[15] => $this->getLastUpdate(),
            $keys[16] => $this->getReviewDate(),
            $keys[17] => $this->getMitigationId(),
            $keys[18] => $this->getMgmtReview(),
            $keys[19] => $this->getProjectId(),
            $keys[20] => $this->getCloseId(),
            $keys[21] => $this->getSubmittedBy(),
            $keys[22] => $this->getParentId(),
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
     * @return $this|\Risks
     */
    public function setByName($name, $value, $type = TableMap::TYPE_PHPNAME)
    {
        $pos = RisksTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);

        return $this->setByPosition($pos, $value);
    }

    /**
     * Sets a field from the object by Position as specified in the xml schema.
     * Zero-based.
     *
     * @param  int $pos position in xml schema
     * @param  mixed $value field value
     * @return $this|\Risks
     */
    public function setByPosition($pos, $value)
    {
        switch ($pos) {
            case 0:
                $this->setId($value);
                break;
            case 1:
                $this->setStatus($value);
                break;
            case 2:
                $this->setSubject($value);
                break;
            case 3:
                $this->setReferenceId($value);
                break;
            case 4:
                $this->setRegulation($value);
                break;
            case 5:
                $this->setControlNumber($value);
                break;
            case 6:
                $this->setLocation($value);
                break;
            case 7:
                $this->setCategory($value);
                break;
            case 8:
                $this->setTeam($value);
                break;
            case 9:
                $this->setTechnology($value);
                break;
            case 10:
                $this->setOwner($value);
                break;
            case 11:
                $this->setManager($value);
                break;
            case 12:
                $this->setAssessment($value);
                break;
            case 13:
                $this->setNotes($value);
                break;
            case 14:
                $this->setSubmissionDate($value);
                break;
            case 15:
                $this->setLastUpdate($value);
                break;
            case 16:
                $this->setReviewDate($value);
                break;
            case 17:
                $this->setMitigationId($value);
                break;
            case 18:
                $this->setMgmtReview($value);
                break;
            case 19:
                $this->setProjectId($value);
                break;
            case 20:
                $this->setCloseId($value);
                break;
            case 21:
                $this->setSubmittedBy($value);
                break;
            case 22:
                $this->setParentId($value);
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
        $keys = RisksTableMap::getFieldNames($keyType);

        if (array_key_exists($keys[0], $arr)) {
            $this->setId($arr[$keys[0]]);
        }
        if (array_key_exists($keys[1], $arr)) {
            $this->setStatus($arr[$keys[1]]);
        }
        if (array_key_exists($keys[2], $arr)) {
            $this->setSubject($arr[$keys[2]]);
        }
        if (array_key_exists($keys[3], $arr)) {
            $this->setReferenceId($arr[$keys[3]]);
        }
        if (array_key_exists($keys[4], $arr)) {
            $this->setRegulation($arr[$keys[4]]);
        }
        if (array_key_exists($keys[5], $arr)) {
            $this->setControlNumber($arr[$keys[5]]);
        }
        if (array_key_exists($keys[6], $arr)) {
            $this->setLocation($arr[$keys[6]]);
        }
        if (array_key_exists($keys[7], $arr)) {
            $this->setCategory($arr[$keys[7]]);
        }
        if (array_key_exists($keys[8], $arr)) {
            $this->setTeam($arr[$keys[8]]);
        }
        if (array_key_exists($keys[9], $arr)) {
            $this->setTechnology($arr[$keys[9]]);
        }
        if (array_key_exists($keys[10], $arr)) {
            $this->setOwner($arr[$keys[10]]);
        }
        if (array_key_exists($keys[11], $arr)) {
            $this->setManager($arr[$keys[11]]);
        }
        if (array_key_exists($keys[12], $arr)) {
            $this->setAssessment($arr[$keys[12]]);
        }
        if (array_key_exists($keys[13], $arr)) {
            $this->setNotes($arr[$keys[13]]);
        }
        if (array_key_exists($keys[14], $arr)) {
            $this->setSubmissionDate($arr[$keys[14]]);
        }
        if (array_key_exists($keys[15], $arr)) {
            $this->setLastUpdate($arr[$keys[15]]);
        }
        if (array_key_exists($keys[16], $arr)) {
            $this->setReviewDate($arr[$keys[16]]);
        }
        if (array_key_exists($keys[17], $arr)) {
            $this->setMitigationId($arr[$keys[17]]);
        }
        if (array_key_exists($keys[18], $arr)) {
            $this->setMgmtReview($arr[$keys[18]]);
        }
        if (array_key_exists($keys[19], $arr)) {
            $this->setProjectId($arr[$keys[19]]);
        }
        if (array_key_exists($keys[20], $arr)) {
            $this->setCloseId($arr[$keys[20]]);
        }
        if (array_key_exists($keys[21], $arr)) {
            $this->setSubmittedBy($arr[$keys[21]]);
        }
        if (array_key_exists($keys[22], $arr)) {
            $this->setParentId($arr[$keys[22]]);
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
     * @return $this|\Risks The current object, for fluid interface
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
        $criteria = new Criteria(RisksTableMap::DATABASE_NAME);

        if ($this->isColumnModified(RisksTableMap::COL_ID)) {
            $criteria->add(RisksTableMap::COL_ID, $this->id);
        }
        if ($this->isColumnModified(RisksTableMap::COL_STATUS)) {
            $criteria->add(RisksTableMap::COL_STATUS, $this->status);
        }
        if ($this->isColumnModified(RisksTableMap::COL_SUBJECT)) {
            $criteria->add(RisksTableMap::COL_SUBJECT, $this->subject);
        }
        if ($this->isColumnModified(RisksTableMap::COL_REFERENCE_ID)) {
            $criteria->add(RisksTableMap::COL_REFERENCE_ID, $this->reference_id);
        }
        if ($this->isColumnModified(RisksTableMap::COL_REGULATION)) {
            $criteria->add(RisksTableMap::COL_REGULATION, $this->regulation);
        }
        if ($this->isColumnModified(RisksTableMap::COL_CONTROL_NUMBER)) {
            $criteria->add(RisksTableMap::COL_CONTROL_NUMBER, $this->control_number);
        }
        if ($this->isColumnModified(RisksTableMap::COL_LOCATION)) {
            $criteria->add(RisksTableMap::COL_LOCATION, $this->location);
        }
        if ($this->isColumnModified(RisksTableMap::COL_CATEGORY)) {
            $criteria->add(RisksTableMap::COL_CATEGORY, $this->category);
        }
        if ($this->isColumnModified(RisksTableMap::COL_TEAM)) {
            $criteria->add(RisksTableMap::COL_TEAM, $this->team);
        }
        if ($this->isColumnModified(RisksTableMap::COL_TECHNOLOGY)) {
            $criteria->add(RisksTableMap::COL_TECHNOLOGY, $this->technology);
        }
        if ($this->isColumnModified(RisksTableMap::COL_OWNER)) {
            $criteria->add(RisksTableMap::COL_OWNER, $this->owner);
        }
        if ($this->isColumnModified(RisksTableMap::COL_MANAGER)) {
            $criteria->add(RisksTableMap::COL_MANAGER, $this->manager);
        }
        if ($this->isColumnModified(RisksTableMap::COL_ASSESSMENT)) {
            $criteria->add(RisksTableMap::COL_ASSESSMENT, $this->assessment);
        }
        if ($this->isColumnModified(RisksTableMap::COL_NOTES)) {
            $criteria->add(RisksTableMap::COL_NOTES, $this->notes);
        }
        if ($this->isColumnModified(RisksTableMap::COL_SUBMISSION_DATE)) {
            $criteria->add(RisksTableMap::COL_SUBMISSION_DATE, $this->submission_date);
        }
        if ($this->isColumnModified(RisksTableMap::COL_LAST_UPDATE)) {
            $criteria->add(RisksTableMap::COL_LAST_UPDATE, $this->last_update);
        }
        if ($this->isColumnModified(RisksTableMap::COL_REVIEW_DATE)) {
            $criteria->add(RisksTableMap::COL_REVIEW_DATE, $this->review_date);
        }
        if ($this->isColumnModified(RisksTableMap::COL_MITIGATION_ID)) {
            $criteria->add(RisksTableMap::COL_MITIGATION_ID, $this->mitigation_id);
        }
        if ($this->isColumnModified(RisksTableMap::COL_MGMT_REVIEW)) {
            $criteria->add(RisksTableMap::COL_MGMT_REVIEW, $this->mgmt_review);
        }
        if ($this->isColumnModified(RisksTableMap::COL_PROJECT_ID)) {
            $criteria->add(RisksTableMap::COL_PROJECT_ID, $this->project_id);
        }
        if ($this->isColumnModified(RisksTableMap::COL_CLOSE_ID)) {
            $criteria->add(RisksTableMap::COL_CLOSE_ID, $this->close_id);
        }
        if ($this->isColumnModified(RisksTableMap::COL_SUBMITTED_BY)) {
            $criteria->add(RisksTableMap::COL_SUBMITTED_BY, $this->submitted_by);
        }
        if ($this->isColumnModified(RisksTableMap::COL_PARENT_ID)) {
            $criteria->add(RisksTableMap::COL_PARENT_ID, $this->parent_id);
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
        $criteria = ChildRisksQuery::create();
        $criteria->add(RisksTableMap::COL_ID, $this->id);

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
        $validPk = null !== $this->getId();

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
     * Returns the primary key for this object (row).
     * @return int
     */
    public function getPrimaryKey()
    {
        return $this->getId();
    }

    /**
     * Generic method to set the primary key (id column).
     *
     * @param       int $key Primary key.
     * @return void
     */
    public function setPrimaryKey($key)
    {
        $this->setId($key);
    }

    /**
     * Returns true if the primary key for this object is null.
     * @return boolean
     */
    public function isPrimaryKeyNull()
    {
        return null === $this->getId();
    }

    /**
     * Sets contents of passed object to values from current object.
     *
     * If desired, this method can also make copies of all associated (fkey referrers)
     * objects.
     *
     * @param      object $copyObj An object of \Risks (or compatible) type.
     * @param      boolean $deepCopy Whether to also copy all rows that refer (by fkey) to the current row.
     * @param      boolean $makeNew Whether to reset autoincrement PKs and make the object new.
     * @throws PropelException
     */
    public function copyInto($copyObj, $deepCopy = false, $makeNew = true)
    {
        $copyObj->setStatus($this->getStatus());
        $copyObj->setSubject($this->getSubject());
        $copyObj->setReferenceId($this->getReferenceId());
        $copyObj->setRegulation($this->getRegulation());
        $copyObj->setControlNumber($this->getControlNumber());
        $copyObj->setLocation($this->getLocation());
        $copyObj->setCategory($this->getCategory());
        $copyObj->setTeam($this->getTeam());
        $copyObj->setTechnology($this->getTechnology());
        $copyObj->setOwner($this->getOwner());
        $copyObj->setManager($this->getManager());
        $copyObj->setAssessment($this->getAssessment());
        $copyObj->setNotes($this->getNotes());
        $copyObj->setSubmissionDate($this->getSubmissionDate());
        $copyObj->setLastUpdate($this->getLastUpdate());
        $copyObj->setReviewDate($this->getReviewDate());
        $copyObj->setMitigationId($this->getMitigationId());
        $copyObj->setMgmtReview($this->getMgmtReview());
        $copyObj->setProjectId($this->getProjectId());
        $copyObj->setCloseId($this->getCloseId());
        $copyObj->setSubmittedBy($this->getSubmittedBy());
        $copyObj->setParentId($this->getParentId());
        if ($makeNew) {
            $copyObj->setNew(true);
            $copyObj->setId(NULL); // this is a auto-increment column, so set to default value
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
     * @return \Risks Clone of current object.
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
        $this->status = null;
        $this->subject = null;
        $this->reference_id = null;
        $this->regulation = null;
        $this->control_number = null;
        $this->location = null;
        $this->category = null;
        $this->team = null;
        $this->technology = null;
        $this->owner = null;
        $this->manager = null;
        $this->assessment = null;
        $this->notes = null;
        $this->submission_date = null;
        $this->last_update = null;
        $this->review_date = null;
        $this->mitigation_id = null;
        $this->mgmt_review = null;
        $this->project_id = null;
        $this->close_id = null;
        $this->submitted_by = null;
        $this->parent_id = null;
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
        return (string) $this->exportTo(RisksTableMap::DEFAULT_STRING_FORMAT);
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
