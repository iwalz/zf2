<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2013 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 * @package   Zend_Stdlib
 */

namespace Zend\Stdlib\Hydrator;

use Zend\Stdlib\Exception,
    Zend\Stdlib\Hydrator\Filter\FilterComposite,
    Zend\Stdlib\Hydrator\Filter\IsFilter,
    Zend\Stdlib\Hydrator\Filter\GetFilter,
    Zend\Stdlib\Hydrator\Filter\HasFilter;

/**
 * @category   Zend
 * @package    Zend_Stdlib
 * @subpackage Hydrator
 */
class ClassMethods extends AbstractHydrator
{
    /**
     * Flag defining whether array keys are underscore-separated (true) or camel case (false)
     * @var boolean
     */
    protected $underscoreSeparatedKeys;
    /**
     * Composite to validate the methods, that need to be hydrated
     * @var Filter\FilterComposite
     */
    protected $filterComposite;

    /**
     * Define if extract values will use camel case or name with underscore
     * @param boolean $underscoreSeparatedKeys
     */
    public function __construct($underscoreSeparatedKeys = true)
    {
        parent::__construct();
        $this->underscoreSeparatedKeys = $underscoreSeparatedKeys;

        $this->filterComposite = new FilterComposite();
        $this->filterComposite->addFilter("is", new IsFilter());
        $this->filterComposite->addFilter("has", new HasFilter());
        $this->filterComposite->addFilter("get", new GetFilter());
    }

    /**
     * Extract values from an object with class methods
     *
     * Extracts the getter/setter of the given $object.
     *
     * @param  object $object
     * @return array
     * @throws Exception\BadMethodCallException for a non-object $object
     */
    public function extract($object)
    {
        if (!is_object($object)) {
            throw new Exception\BadMethodCallException(sprintf(
                '%s expects the provided $object to be a PHP object)', __METHOD__
            ));
        }

        $transform = function ($letters) {
            $letter = array_shift($letters);
            return '_' . strtolower($letter);
        };
        $attributes = array();
        $methods = get_class_methods($object);

        foreach ($methods as $method) {
            if (
                !$this->filterComposite->filter(
                    get_class($object) . '::' . $method
                )
            ) {
                continue;
            }

            $attribute = $method;
            if (preg_match('/^get/', $method)) {
                $attribute = substr($method, 3);
                $attribute = lcfirst($attribute);
            }

            if ($this->underscoreSeparatedKeys) {
                $attribute = preg_replace_callback('/([A-Z])/', $transform, $attribute);
            }
            $attributes[$attribute] = $this->extractValue($attribute, $object->$method());
        }

        return $attributes;
    }

    /**
     * Hydrate an object by populating getter/setter methods
     *
     * Hydrates an object by getter/setter methods of the object.
     *
     * @param  array $data
     * @param  object $object
     * @return object
     * @throws Exception\BadMethodCallException for a non-object $object
     */
    public function hydrate(array $data, $object)
    {
        if (!is_object($object)) {
            throw new Exception\BadMethodCallException(sprintf(
                '%s expects the provided $object to be a PHP object)', __METHOD__
            ));
        }

        $transform = function ($letters) {
            $letter = substr(array_shift($letters), 1, 1);
            return ucfirst($letter);
        };

        foreach ($data as $property => $value) {
            $method = 'set' . ucfirst($property);
            if ($this->underscoreSeparatedKeys) {
                $method = preg_replace_callback('/(_[a-z])/', $transform, $method);
            }
            if (method_exists($object, $method)) {
                $value = $this->hydrateValue($property, $value);

                $object->$method($value);
            }
        }
        return $object;
    }

    /**
     * Add a new validator to take care of what needs to be hydrated.
     * To exclude e.g. the method getServiceLocator:
     *
     * <code>
     * $composite->addValidator("servicelocator",
     *     function($property) {
     *         list($class, $method) = explode('::', $property);
     *         if ($method === 'getServiceLocator') {
     *             return false;
     *         }
     *         return true;
     *     }, ValidatorComposite::CONDITION_AND
     * );
     * </code>
     *
     * @param string $name Index in the composite
     * @param callable|Zend\Stdlib\Hydrator\Filter\FilterInterface $validator
     * @param int $condition
     */
    public function addFilter($name, $validator, $condition = FilterComposite::CONDITION_OR)
    {
        $this->filterComposite->addFilter($name, $validator, $condition);
    }

    /**
     * Check whether a specific validator exists at key $name or not
     *
     * @param string $name Index in the composite
     * @return bool
     */
    public function hasFilter($name)
    {
        return $this->filterComposite->hasFilter($name);
    }

    /**
     * Remove a validator from the composition.
     * To not extract "has" methods, you simply need to unregister it
     *
     * <code>
     * $filterComposite->removeValidator('has');
     * </code>
     *
     * @param $name
     */
    public function removeFilter($name)
    {
        $this->filterComposite->removeFilter($name);
    }
}
