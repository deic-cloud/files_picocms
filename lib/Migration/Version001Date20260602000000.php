<?php

declare(strict_types=1);

namespace OCA\FilesPicoCMS\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version001Date20260602000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('files_picocms')) {
			$table = $schema->createTable('files_picocms');
			$table->addColumn('id', Types::INTEGER, [
				'autoincrement' => true,
				'notnull'       => true,
				'length'        => 12,
			]);
			$table->addColumn('uid', Types::STRING, [
				'notnull' => true,
				'length'  => 64,
			]);
			$table->addColumn('site', Types::STRING, [
				'notnull' => true,
				'length'  => 255,
			]);
			$table->addColumn('path', Types::STRING, [
				'notnull' => false,
				'length'  => 255,
				'default' => '',
			]);
			$table->addColumn('gid', Types::STRING, [
				'notnull' => true,
				'length'  => 255,
				'default' => '',
			]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['site'], 'files_picocms_site_unique');
			$table->addIndex(['uid'], 'files_picocms_uid_idx');
		}

		return $schema;
	}
}
