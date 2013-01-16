<?php defined('SYSPATH') OR die('No direct script access.');

/**
 * Tests for Jam_Model functionality.
 *
 * @package Jam
 * @group   jam
 * @group   jam.model
 */
class Jam_ModelTest extends Unittest_Jam_Database_TestCase {

	/**
	 * Provider for test_save_empty_model
	 */
	public function provider_save_empty_model()
	{
		return array(
			array('test_author'),
			array('test_category'),
		);
	}
	
	/**
	 * Tests that empty models can be saved with nothing set on them.
	 * This should work for every model that has no rules that require
	 * data to be set on them, since Jam properly manages NULLs and
	 * default values.
	 * 
	 * @dataProvider  provider_save_empty_model
	 */
	public function test_save_empty_model($model_name)
	{
		$model = Jam::build($model_name);
		$model->save();
		
		// Model should be saved, loaded, and have an id
		$this->assertTrue($model->saved());
		$this->assertTrue($model->loaded());
		$this->assertNotNull(Jam::find($model, $model->id()));
	}
	
	/**
	 * Tests that primary keys can be changed or set manually.
	 *
	 * We don't put this in the PrimaryTest because it has more
	 * to do with how the model handles it than the field.
	 */
	public function test_save_primary_key()
	{
		$model = Jam::build('test_post');
		$model->id = 9000;
		$model->save();

		// Verify data is as it should be
		$this->assertTrue($model->saved());
		$this->assertEquals(9000, $model->id);

		// Verify the record actually exists in the database
		$this->assertTrue(Jam::build('test_post', 9000)->loaded());

		// Manually re-selecting so that Postgres doesn't cause errors down the line
		$model = Jam::build('test_post', 9000);

		// Change it again so we can verify it works on UPDATE as well
		// This is key because Jam got this wrong in the past
		$model->id = 9001;
		$model->save();

		// Verify we can't find the old record 9000
		$this->assertFalse(Jam::build('test_post', 9000)->loaded());

		// And that we can find the new 9001
		$this->assertTrue(Jam::build('test_post', 9001)->loaded());

		// Cleanup
		Jam::build('test_post', 9001)->delete();
	}

	/**
	 * Provider for test_state
	 */
	public function provider_state()
	{
		return array(
			array(Jam::build('test_alias'), FALSE, FALSE, FALSE),
			array(Jam::build('test_alias')->set('name', 'Test'), FALSE, FALSE, TRUE),
			array(Jam::build('test_alias')->load_fields(array('name' => 'Test')), TRUE, TRUE, FALSE),
			array(Jam::build('test_alias')->load_fields(array('name' => 'Test'))->set('name', 'Test'), TRUE, FALSE, TRUE),
			array(Jam::build('test_alias')->load_fields(array('name' => 'Test'))->set('name', 'Test2'), TRUE, FALSE, TRUE),
			array(Jam::build('test_alias')->set('name', 'Test')->clear(), FALSE, FALSE, FALSE),
			array(Jam::build('test_alias')->load_fields(array('name' => 'Test'))->clear(), FALSE, FALSE, FALSE),
		);
	}

	/**
	 * Tests the various states a model may have are set properly.
	 *
	 * The states are access with Jam_Model::loaded(),
	 * Jam_Model::saved(), and Jam_Model::changed().
	 *
	 * @dataProvider  provider_state
	 */
	public function test_state($model, $loaded, $saved, $changed)
	{
		$this->assertSame($model->loaded(), $loaded);
		$this->assertSame($model->saved(), $saved);
		$this->assertSame($model->changed(), $changed);
	}

	/**
	 * Provider for test_original
	 */
	public function provider_original()
	{
		// Create a mock model for most of our tests
		$alias = Jam::build('test_alias')
			->load_fields(array(
				'id'          => 1,
				'name'        => 'Test',
				'description' => 'Description',
			))->set(array(
				'id'          => 2,
				'name'        => 'Test2',
				'description' => 'Description2',
			));

		// Test without changes
		return array(
			array($alias, 'id', 1),
			array($alias, 'name', 'Test'),
			array($alias, 'description', 'Description'),
		);
	}

	/**
	 * Tests Jam_Model::original()
	 *
	 * @dataProvider provider_original
	 */
	public function test_original($model, $field, $expected)
	{
		$this->assertSame($model->original($field), $expected);
	}

	/**
	 * Provider for test_changed
	 */
	public function provider_changed()
	{
		// Create a mock model for most of our tests
		$alias = Jam::build('test_alias')
			->load_fields(array(
				'id'          => 1,
				'name'        => 'Test',
				'description' => 'Description',
			))->set(array(
				'id'          => 2,
				'name'        => 'Test2',
				'description' => 'Description',
			));

		// Test without changes
		return array(
			array($alias, 'id', TRUE),
			array($alias, 'name', TRUE),
			array($alias, 'description', TRUE),
		);
	}

	/**
	 * Tests Jam_Model::changed()
	 *
	 * @dataProvider provider_changed
	 */
	public function test_changed($model, $field, $expected)
	{
		$this->assertSame($model->changed($field), $expected);
	}

	/**
	 * Tests Jam_Model::clear()
	 */
	public function test_clear()
	{
		// Empty model to compare
		$one = Jam::build('test_alias');

		// Set and cleared model
		$two = Jam::build('test_alias')
			->load_fields(array(
				'id'          => 1,
				'name'        => 'Test',
				'description' => 'Description',
			))->set(array(
				'id'          => 2,
				'name'        => 'Test2',
				'description' => 'Description2',
			))->clear();

		// They should match in a non-strict sense
		$this->assertEquals($one, $two);
	}

	public function test_check()
	{
		$video = Jam::build('test_video', 1);
		$this->assertTrue($video->check());
		$video->file = '111';
		$this->assertFalse($video->check());

		$this->setExpectedException('Jam_Exception_Validation');
		$video->check_insist();
	}
}