<?php
/**
*
* @package testing
* @copyright (c) 2011 phpBB Group
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

class phpbb_dbal_migration_if extends phpbb_db_migration
{
	function depends_on()
	{
		return array();
	}

	function update_schema()
	{
		return array();
	}

	function update_data()
	{
		return array(
			array('if', array(
				true,
				array('custom', array(array(&$this, 'test_true'))),
			)),
			array('if', array(
				false,
				array('custom', array(array(&$this, 'test_false'))),
			)),
		);
	}

	function test_true()
	{
		global $migrator_test_if_true_failed;

		$migrator_test_if_true_failed = false;
	}

	function test_false()
	{
		global $migrator_test_if_false_failed;

		$migrator_test_if_false_failed = true;
	}
}
