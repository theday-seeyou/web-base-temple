<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RenameStarterBrand extends AbstractMigration
{
    public function up(): void
    {
        $system_config = $this->getAdapter()->quoteTableName($this->getAdapter()->getAdapterTableName('system_config'));
        $this->execute(
            "UPDATE {$system_config} SET value='WebBase Admin' " .
            "WHERE type='base' AND name IN ('app_name','site_name') " .
            "AND value='WebBase CMS'"
        );
        $this->execute(
            "UPDATE {$system_config} SET value=REPLACE(value,'WebBase CMS','WebBase Admin') " .
            "WHERE type='base' AND name='site_copy' AND value LIKE '%WebBase CMS%'"
        );
    }

    public function down(): void
    {
        $system_config = $this->getAdapter()->quoteTableName($this->getAdapter()->getAdapterTableName('system_config'));
        $this->execute(
            "UPDATE {$system_config} SET value='WebBase CMS' " .
            "WHERE type='base' AND name IN ('app_name','site_name') " .
            "AND value='WebBase Admin'"
        );
        $this->execute(
            "UPDATE {$system_config} SET value=REPLACE(value,'WebBase Admin','WebBase CMS') " .
            "WHERE type='base' AND name='site_copy' AND value LIKE '%WebBase Admin%'"
        );
    }
}
