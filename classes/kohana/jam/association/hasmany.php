<?php defined('SYSPATH') OR die('No direct script access.');

/**
 * Handles has one to relationships
 *
 * @package    Jam
 * @category   Associations
 * @author     Ivan Kerin
 * @copyright  (c) 2012 Despark Ltd.
 * @license    http://www.opensource.org/licenses/isc-license.txt
 */
abstract class Kohana_Jam_Association_HasMany extends Jam_Association_Collection {

	public $as;

	public $foreign_default = 0;

	public $count_cache = NULL;

	/**
	 * Automatically sets foreign to sensible defaults.
	 *
	 * @param   string  $model
	 * @param   string  $name
	 * @return  void
	 */
	public function initialize(Jam_Meta $meta, $model, $name)
	{
		// Empty? The model defaults to the the singularized name
		// of this field, and the field defaults to this field's model's foreign key
		if (empty($this->foreign))
		{
			$this->foreign = Inflector::singular($name).'.'.Jam::meta($model)->foreign_key();
		}
		// We have a model? Default the field to this field's model's foreign key
		elseif (FALSE === strpos($this->foreign, '.'))
		{
			$this->foreign = $this->foreign.'.'.Jam::meta($model)->foreign_key();
		}

		parent::initialize($meta, $model, $name);

		// Polymorphic associations
		if ($this->as)
		{
			if ( ! is_string($this->as))
			{
				$this->as = $this->model;
			}
			$this->foreign['as'] = $this->as.'_model';
			$this->foreign['field'] = $this->as.'_id';
		}

		// Count Cache
		if ($this->count_cache)
		{
			if ($this->is_polymorphic())
				throw new Kohana_Exception('Cannot use count cache on polymorphic associations');
			
			if ($this->count_cache === TRUE)
			{
				$this->count_cache = $this->name.'_count';
			}

			$meta->field($this->count_cache, Jam::field('integer', array('default' => 0, 'allow_null' => FALSE)));

			$this->extension('countcache', Jam::extension('countcache'));
		}
	}

	public function attribute_join(Jam_Builder $builder, $alias = NULL, $type = NULL)
	{
		$join = $builder
			->join($this->foreign(NULL, $alias), $type)
			->on($this->foreign('field', $alias), '=', "{$this->model}.:primary_key");
		
		if ($this->as)
		{
			$join->on($this->foreign('as', $alias), '=', DB::expr('"'.$this->model.'"'));
		}

		return $join;
	}

	public function attribute_builder(Jam_Model $model)
	{
		if ( ! $model->loaded())
			throw new Kohana_Exception("Cannot create Jam_Builder on :model->:name because model is not loaded", array(':name' => $this->name, ':model' => $model->meta()->model()));

		$builder = Jam::query($this->foreign())
			->where($this->foreign('field'), '=', $model->id());

		if ($this->as)
		{
			$builder->where($this->foreign('as'), '=', $model->meta()->model());
		}

		return $builder;
	}

	public function attribute_before_delete(Jam_Model $model, $is_changed)
	{
		switch ($this->dependent) 
		{
			case Jam_Association::DELETE:
				foreach ($this->attribute_get($model) as $item) 
				{
					$item->delete();
				}
			break;
			case Jam_Association::ERASE:
				$this->builder($model)->delete();
			break;
			case Jam_Association::NULLIFY:
				$this->nullify_builder($model)->update();
			break;
		}
	}

	public function nullify_builder(Jam_Model $model)
	{
		$builder = $this->builder($model)
			->value($this->foreign('field'), $this->foreign_default);

		if ($this->as)
		{
			$builder->value($this->foreign('as'), NULL);
		}

		return $builder;
	}


	public function attribute_after_save(Jam_Model $model, $is_changed)
	{
		if ($is_changed AND $collection = $model->{$this->name} AND $collection->changed())
		{
			list($old_ids, $new_ids) = $this->diff_collection_ids($model, $collection);

			if (array_filter($old_ids))
			{
				$this->nullify_builder($model)->key($old_ids)->update();
			}
			
			if ($new_ids)
			{
				$new_items_builder = Jam::query($this->foreign())
					->key($new_ids)
					->value($this->foreign('field'), $model->id());

				if ($this->as)
				{
					$new_items_builder->value($this->foreign('as'), $model->meta()->model());
				}

				$new_items_builder->update();
			}
		}
	}

	/**
	 * See if the association is polymorphic
	 * @return boolean 
	 */
	public function is_polymorphic()
	{
		return (bool) $this->as;
	}

	public function assign_relation(Jam_Model $model, $item)
	{
		if ($item instanceof Jam_Model)
		{
			$item->set($this->foreign['field'], $model->id());

			if ($this->is_polymorphic())
			{
				$item->set($this->foreign['as'], $model->meta()->model());
			}
		}
		return parent::assign_relation($model, $item);
	}


} // End Kohana_Jam_Association_HasMany
