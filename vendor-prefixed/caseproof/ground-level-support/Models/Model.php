<?php
/**
 * @license GPL-3.0
 *
 * Modified by Team Caseproof using {@see https://github.com/BrianHenryIE/strauss}.
 */

declare(strict_types=1);

namespace MemberPress\GroundLevel\Support\Models;

use MemberPress\GroundLevel\Support\Contracts\Arrayable;
use MemberPress\GroundLevel\Support\Contracts\Jsonable;
use MemberPress\GroundLevel\Support\Casts;
use MemberPress\GroundLevel\Support\Exceptions\Exception;
use MemberPress\GroundLevel\Support\Exceptions\ReadOnlyAttributeError;
use MemberPress\GroundLevel\Support\Time;
use MemberPress\GroundLevel\Support\Concerns\HasAttributes;
use MemberPress\GroundLevel\Support\Util;
use JsonSerializable;
use stdClass;

/**
 * Base model class.
 */
abstract class Model implements Arrayable, Jsonable, JsonSerializable
{
    use HasAttributes;

    /**
     * The keyname of the ID attribute.
     *
     * @var string
     */
    protected string $idKey = 'id';

    /**
     * Determines whether or not the model's construction has completed.
     *
     * During construction, autoupdating functions shouldn't be triggered when
     * filling attributes regardless of whether auto updating is enabled or not.
     *
     * @var boolean
     */
    protected bool $isConstructed = false;

    /**
     * Lists of the item's attributes formats.
     *
     * An array of the item attributes ID mapped to the ID's format.
     *
     * @var array
     */
    protected array $attributesFormats = [];

    /**
     * Initializes a new object with data.
     *
     * @param array|stdClass|string|integer|null $item An array or object of model data (key->val), the item ID
     *                                                 as an int or string, or null to create an empty object.
     */
    public function __construct($item = null)
    {
        $this->attributeAliases[$this->idKey] = 'id';
        $this->attributeFormats               = array_merge(
            [
                $this->idKey => Casts::INTEGER(),
            ],
            $this->attributeFormats
        );

        if (is_numeric($item) || is_string($item)) {
            $this->setId($item);
        }
        if (! empty($item) && (is_array($item) || $item instanceof stdClass)) {
            $this->fillAttributes((array) $item);
        }
        $this->isConstructed = true;
        $this->resetChangedAttributes();
    }

    /**
     * Retrieves the object ID.
     *
     * @return string|integer|null
     */
    public function getId()
    {
        return $this->getAttributeSafe($this->idKey);
    }

    /**
     * Sets the model's ID.
     *
     * @param  integer|string $value The ID.
     * @return self
     * @throws ReadOnlyAttributeError Throws an exception if.
     */
    public function setId($value): self
    {
        if (! empty($value)) {
            // Id's are immutable once set.
            if (! empty($this->getId())) {
                throw new ReadOnlyAttributeError(
                    "The {$this->idKey} attribute is read-only."
                );
            }
            $this->setAttributeSafe($this->idKey, $value);
        }
        return $this;
    }

    /**
     * Retrieves the model's type ID.
     *
     * @return string
     */
    public function getModelType(): string
    {
        return Util::classBasename($this);
    }

    /**
     * Retrieves the keyname of the model's primary key attribute.
     *
     * @return string
     */
    public function getPrimaryKey(): string
    {
        return $this->idKey;
    }
}
