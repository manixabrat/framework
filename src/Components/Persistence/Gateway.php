<?php

namespace Manix\Brat\Components\Persistence;

use Exception;
use Manix\Brat\Components\Collection;
use Manix\Brat\Components\Criteria;
use Manix\Brat\Components\Model;
use Manix\Brat\Components\Sorter;
use Manix\Brat\Helpers\Time;

/**
 * The base gateway interface.
 */
abstract class Gateway {

  /**
   * The model interface.
   */
  const MODEL = Model::class;
  const TIMESTAMP_CREATED = 'created';
  const TIMESTAMP_UPDATED = 'updated';

  /**
   * @var string The table/namespace for this persistence element.
   */
  protected $table;

  /**
   * @var array List of fields to extract and persist per model instance.
   */
  protected $fields = [];

  /**
   * @var string An auto-increment field, if present.
   */
  protected $ai;

  /**
   * @var array The primary key declaration.
   */
  protected $pk;

  /**
   * @var array An array containing declarations of the relations of this gateway.
   */
  protected $rel;

  /**
   * @var array An array containing the currently joined gateways.
   */
  protected $joins = [];

  /**
   * @var Sorter
   */
  protected $sorter;

  /**
   * Number of records to skip from result set.
   * @var int
   */
  public $cutoff = 0;

  /**
   * Number of records to retrieve after cutoff.
   * @var int
   */
  public $limit = 1000;

  /**
   * Whether this gateway has timestamps or not.
   * @var bool
   */
  protected $timestamps;

  /**
   * Get the key names that form the model's primary key.
   * @return array
   */
  public function getPK() {
    return $this->pk;
  }

  /**
   * Get the auto increment value for this gateway, if present.
   * @return mixed String or NULL.
   */
  public function getAI() {
    return $this->ai;
  }

  /**
   * Get the key names that this gateway cares about.
   * @return array
   */
  public function getFields() {
    $fields = $this->fields;

    if ($this->timestamps) {
      $fields[] = self::TIMESTAMP_CREATED;
      $fields[] = self::TIMESTAMP_UPDATED;
    }

    return $fields;
  }

  /**
   * Set the key names that this gateway cares about.
   * @param array $fields
   * @return $this
   */
  public function setFields(array $fields) {
    $this->fields = $fields;
    return $this;
  }

  public function addField($field) {
    $this->removeField($field);
    $this->fields[] = $field;
  }

  public function removeField($field) {
    foreach (array_keys($this->fields, $field) as $key) {
      unset($this->fields[$key]);
    }
  }

  /**
   * Persist an object.
   * 
   * @param Model $model The model.
   * @param array $fields The fields to persist from the model. If null, then persist all.
   * @return bool Whether the model was persisted successfully or not.
   */
  abstract public function persist(Model $model, array $fields = null): bool;

  /**
   * Persist a collection of objects.
   * @param Collection $collection
   * @param array $fields
   * @return int The number of successfully persisted objects.
   */
  public function persistCollecion(Collection $collection, array $fields = null): int {
    $count = 0;

    foreach ($collection as $object) {
      $count += $this->persist($object, $fields) ? 1 : 0;
    }

    return $count;
  }

  /**
   * Wipe a persisted object.
   * 
   * @param array $pk Values for primary key.
   * @return bool Whether object was wiped successfully or not.
   */
  abstract public function wipe(...$pk): bool;

  abstract public function wipeBy(Criteria $criteria): bool;

  /**
   * Find a persisted object by primary key.
   * 
   * @param array $pk Values for primary key.
   * @return Collection The found objects.
   */
  abstract public function find(...$pk): Collection;

  abstract public function findBy(Criteria $criteria): Collection;

  protected function instantiate(array $set) {
    $interface = static::MODEL;
    $collection = new Collection($interface);

    foreach ($set as $row) {
      $collection->push(new $interface($this->unpack($row)));
    }

    return $collection;
  }

  /**
   * Joins a predefined related gateway.
   * @param string $rel The key for the relation.
   * @param mixed $gate A gateway instance or array of fields to select.
   * @return $gate
   */
  public function join($rel, $gate = null): Gateway {
    if (isset($this->rel[$rel])) {
      $class = $this->rel[$rel][0];

      if (is_array($gate)) {
        $gate = (new $class)->setFields($gate);
      } elseif ($gate === null) {
        $gate = new $class;
      }

      if ($gate instanceof $class) {
        $this->joins[$rel] = $gate;
        return $gate;
      }
      
      throw new Exception('Trying to join a wrong gateway.', 500);
    } else {
      throw new Exception('Trying to join an undefined relation.', 500);
    }
  }

  /**
   * Remove a previously joined gateway.
   * @param string $rel The key for the relation.
   */
  public function unjoin($rel) {
    unset($this->joins[$rel]);
  }

  public function sort(Sorter $sorter) {
    $this->sorter = $sorter;

    return $this;
  }

  /**
   * Prepare model data for persistence.
   * @param array $row The model data.
   * @return array The modified, if necessary, data.
   */
  public function pack($row) {
    if ($this->timestamps) {
      $row[($row[self::TIMESTAMP_CREATED] ?? null) ? self::TIMESTAMP_UPDATED : self::TIMESTAMP_CREATED] = new Time();
    }

    return $row;
  }

  /**
   * Prepare a persisted row for injection in Model.
   * @param array $row
   * @return array The modified, if necessary, data.
   */
  public function unpack($row) {
    if ($this->timestamps) {
      $row[self::TIMESTAMP_CREATED] = new Time($row[self::TIMESTAMP_CREATED]);
      $row[self::TIMESTAMP_UPDATED] = new Time($row[self::TIMESTAMP_UPDATED] ?? '0000');
    }

    return $row;
  }

  /**
   * Retrieve the local field name for a relation.
   * @param string $relation Relation name
   * @return string The field name.
   */
  public function getLocalRelationKey($relation) {
    return $this->rel[$relation][1] ?? $relation;
  }

  /**
   * Retrieve the remote field name for a relation.
   * @param string $relation Relation name
   * @param Gateway $remoteGateway
   * @return mixed The field name or false if it can not be determined.
   */
  public function getRemoteRelationKey($relation, Gateway $remoteGateway) {
    return $this->rel[$relation][2] ?? $remoteGateway->getPK()[0] ?? false;
  }

}
