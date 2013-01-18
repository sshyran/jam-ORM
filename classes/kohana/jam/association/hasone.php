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
abstract class Kohana_Jam_Association_HasOne extends Jam_Association {

	/**
	 * Polymorphic option - this describes the opposite polymorphic association
	 * @var string
	 */
	public $as = NULL;

	/**
	 * The foreign key
	 * @var string
	 */
	public $foreign_key = NULL;

	public $inverse_of = NULL;

	public $polymorphic_key = NULL;

	/**
	 * Automatically sets foreign to sensible defaults.
	 *
	 * @param   string  $model
	 * @param   string  $name
	 * @return  void
	 */
	public function initialize(Jam_Meta $meta, $name)
	{
		parent::initialize($meta, $name);

		if ( ! $this->foreign_model)
		{
			$this->foreign_model = $name;
		}

		if ( ! $this->foreign_key)
		{
			$this->foreign_key = $this->model.'_id';
		}

		// Polymorphic associations
		if ($this->as)
		{
			$this->foreign_key = $this->as.'_id';
			if ( ! $this->polymorphic_key)
			{
				$this->polymorphic_key = $this->as.'_model';
			}
		}
	}

	public function load_fields(Jam_Validated $model, $value)
	{
		if ( ! ($value instanceof Jam_Model))
		{
			$value = Jam::build($this->foreign_model)->load_fields($value);
		}

		if ($this->inverse_of)
		{
			$value->{$this->inverse_of} = $model;
		}
		
		return $value;
	}

	public function join($alias, $type = NULL)
	{
		$join = Jam_Query_Builder_Join::factory($alias ? array($this->foreign_model, $alias) : $this->foreign_model, $type)
			->context_model($this->model)
			->on($this->foreign_key, '=', ':primary_key');

		if ($this->is_polymorphic())
		{
			$join->on($this->polymorphic_key, '=', DB::expr('"'.$this->model.'"'));
		}

		return $join;
	}

	public function get(Jam_Validated $model, $value, $is_changed)
	{
		if ($is_changed)
		{
			if ($value instanceof Jam_Validated OR ! $value)
				return $value;

			$key = Jam_Association::primary_key($this->foreign_model, $value);
		
			$item = $this->_find_item($this->foreign_model, $key);
		
			$item->{$this->foreign_key} = $model->id();

			if ($this->is_polymorphic())
			{
				$item->{$this->polymorphic} = $model->meta()->model();
			}

			if ($item)
			{
				if (is_array($value))
				{
					$item->set($value);
				}

				if ($this->inverse_of)
				{
					$item->{$this->inverse_of} = $model;
				}
			}

			return $item;
		}
		else
		{
			return $this->_find_item($this->foreign_model, $model);
		}
	}

	public function model_after_check(Jam_Model $model, Jam_Event_Data $data, $changed)
	{
		if ($value = Arr::get($changed, $this->name) AND Jam_Association::value_is_changed($value))
		{
			if ( ! $model->{$this->name}->is_validating() AND ! $model->{$this->name}->check())
			{
				$model->errors()->add($this->name, 'association', array(':errors' => $model->{$this->name}->errors()));
			}
		}
	}

	public function model_after_save(Jam_Model $model, Jam_Event_Data $data, $changed)
	{
		if ($value = Arr::get($changed, $this->name))
		{
			$this->update_query($model, NULL, NULL)->execute();

			if (Jam_Association::is_changed($value) AND $item = $model->{$this->name})
			{
				if ( ! $item->is_saving())
				{
					$item->save();
				}
			}
			else
			{
				$key = Jam_Association::primary_key($value);

				$query = Jam_Query_Builder_Update::factory($this->foreign_model)
					->where(':unique_key', '=', $key)
					->value($this->foreign_key, $model->id());

				if ($this->is_polymorphic())
				{
					$query
						->value($this->polymorphic_key, $model->meta()->model());
				}
				$query->execute();
			}
		}
	}

	public function model_before_delete(Jam_Model $model)
	{
		switch ($this->dependent) 
		{
			case Jam_Association::DELETE:
				if ($model->{$this->name})
				{
					$model->{$this->name}->delete();
				}
			break;

			case Jam_Association::ERASE:
				$this->query_builder('delete', $model)->execute();
			break;

			case Jam_Association::NULLIFY:
				$this->update_query($model, NULL, NULL)->execute();
			break;
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

	protected function _find_item($foreign_model, $key)
	{
		if ($key instanceof Jam_Model)
		{
			$query = $this->query_builder('all', $key);
		}
		else
		{
			$query = Jam_Query_Builder_Collection::factory($foreign_model)
				->where(':unique_key', '=', $key)
				->limit(1);
		}

		return $query->current();
	}

	public function query_builder($type, Jam_Model $model)
	{
		$query = call_user_func("Jam::{$type}", $this->foreign_model)
			->where($this->foreign_key, '=', $model->id());

		if ($this->is_polymorphic())
		{
			$query->where($this->polymorphic_key, '=', $model->meta()->model());
		}

		return $query;
	}

	public function update_query(Jam_Model $model, $new_id, $new_model)
	{
		$query = $this->query_builder('update', $model)
			->value($this->foreign_key, $new_id);

		if ($this->is_polymorphic())
		{
			$query->value($this->polymorphic_key, $new_model);
		}
		return $query;
	}
}
