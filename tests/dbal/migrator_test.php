<?php
/**
*
* @package testing
* @copyright (c) 2011 phpBB Group
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

require_once dirname(__FILE__) . '/../../phpBB/includes/functions.php';
require_once dirname(__FILE__) . '/../../phpBB/includes/db/migrator.php';
require_once dirname(__FILE__) . '/../../phpBB/includes/db/migration/migration.php';
require_once dirname(__FILE__) . '/../../phpBB/includes/db/db_tools.php';

require_once dirname(__FILE__) . '/migration/dummy.php';
require_once dirname(__FILE__) . '/migration/unfulfillable.php';
require_once dirname(__FILE__) . '/migration/if.php';

class phpbb_dbal_migrator_test extends phpbb_database_test_case
{
	protected $db;
	protected $db_tools;
	protected $migrator;

	public function getDataSet()
	{
		return $this->createXMLDataSet(dirname(__FILE__).'/fixtures/migrator.xml');
	}

	public function setUp()
	{
		parent::setUp();

		$this->db = $this->new_dbal();
		$this->db_tools = new phpbb_db_tools($this->db);

		$this->config = new phpbb_config_db($this->db, new phpbb_mock_cache, 'phpbb_config');

		$tools = array(
			new phpbb_db_migration_tool_config($this->config),
		);
		$this->migrator = new phpbb_db_migrator($this->config, $this->db, $this->db_tools, 'phpbb_migrations', dirname(__FILE__) . '/../../phpBB/', 'php', 'phpbb_', $tools);
	}

	public function test_update()
	{
		$this->migrator->set_migrations(array('phpbb_dbal_migration_dummy'));

		// schema
		$this->migrator->update();
		$this->assertFalse($this->migrator->finished());

		$this->assertSqlResultEquals(
			array(array('success' => '1')),
			"SELECT 1 as success
				FROM phpbb_migrations
				WHERE migration_name = 'phpbb_dbal_migration_dummy'
					AND migration_start_time >= " . (time() - 1) . "
					AND migration_start_time <= " . (time() + 1),
			'Start time set correctly'
		);

		// data
		$this->migrator->update();
		$this->assertTrue($this->migrator->finished());

		$this->assertSqlResultEquals(
			array(array('extra_column' => '1')),
			"SELECT extra_column FROM phpbb_config WHERE config_name = 'foo'",
			'Dummy migration created extra_column with value 1 in all rows.'
		);

		$this->assertSqlResultEquals(
			array(array('success' => '1')),
			"SELECT 1 as success
				FROM phpbb_migrations
				WHERE migration_name = 'phpbb_dbal_migration_dummy'
					AND migration_start_time <= migration_end_time
					AND migration_end_time >= " . (time() - 1) . "
					AND migration_end_time <= " . (time() + 1),
			'End time set correctly'
		);

		// cleanup
		$this->db_tools->sql_column_remove('phpbb_config', 'extra_column');
	}

	public function test_unfulfillable()
	{
		$this->migrator->set_migrations(array('phpbb_dbal_migration_unfulfillable', 'phpbb_dbal_migration_dummy'));

		while (!$this->migrator->finished())
		{
			$this->migrator->update();
		}

		$this->assertTrue($this->migrator->finished());

		$this->assertSqlResultEquals(
			array(array('extra_column' => '1')),
			"SELECT extra_column FROM phpbb_config WHERE config_name = 'foo'",
			'Dummy migration was run, even though an unfulfillable migration was found.'
		);
	}

	public function test_if()
	{
		$this->migrator->set_migrations(array('phpbb_dbal_migration_if'));

		// Don't like this, but I'm not sure there is any other way to do this
		global $migrator_test_if_true_failed, $migrator_test_if_false_failed;
		$migrator_test_if_true_failed = true;
		$migrator_test_if_false_failed = false;

		while (!$this->migrator->finished())
		{
			$this->migrator->update();
		}

		if ($migrator_test_if_true_failed)
		{
			$this->fail('True test failed');
		}

		if ($migrator_test_if_false_failed)
		{
			$this->fail('False test failed');
		}
	}
}
