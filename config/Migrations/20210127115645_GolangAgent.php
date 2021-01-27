<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Class GolangAgent
 *
 * Created:
 * oitc migrations create GolangAgent
 *
 * Usage:
 * openitcockpit-update
 */
class GolangAgent extends AbstractMigration {

    public function up() {
        $this->table('agentconfigs')
            ->addColumn('use_autossl', 'boolean', [
                'default' => '1',
                'limit'   => null,
                'null'    => false,
                'after'   => 'insecure'
            ])
            ->addColumn('use_push_mode', 'boolean', [
                'default' => '0',
                'limit'   => null,
                'null'    => false,
                'after'   => 'use_autossl'
            ])
            ->addColumn('config', 'text', [
                'default' => '',
                'null'    => false,
                'limit'   => \Phinx\Db\Adapter\MysqlAdapter::TEXT_REGULAR,
                'after'   => 'push_noticed'
            ])
            ->update();
    }

    public function down() {
        $this->table('agentconfigs')
            ->removeColumn('use_autossl')
            ->removeColumn('use_push_mode')
            ->removeColumn('config')
            ->update();
    }
}
