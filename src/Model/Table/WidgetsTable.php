<?php
declare(strict_types=1);

namespace App\Model\Table;

use App\Lib\PluginManager;
use App\Lib\Traits\Cake2ResultTableTrait;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use itnovum\openITCOCKPIT\Core\Dashboards\ModuleWidgetsInterface;

/**
 * Widgets Model
 *
 * @property \App\Model\Table\DashboardTabsTable&\Cake\ORM\Association\BelongsTo $DashboardTabs
 * @property \App\Model\Table\HostsTable&\Cake\ORM\Association\BelongsTo $Hosts
 * @property \App\Model\Table\ServicesTable&\Cake\ORM\Association\BelongsTo $Services
 *
 * @method \App\Model\Entity\Widget get($primaryKey, $options = [])
 * @method \App\Model\Entity\Widget newEntity($data = null, array $options = [])
 * @method \App\Model\Entity\Widget[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\Widget|false save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\Widget saveOrFail(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\Widget patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\Widget[] patchEntities($entities, array $data, array $options = [])
 * @method \App\Model\Entity\Widget findOrCreate($search, callable $callback = null, $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class WidgetsTable extends Table {

    use Cake2ResultTableTrait;

    /**
     * Initialize method
     *
     * @param array $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void {
        parent::initialize($config);

        $this->setTable('widgets');
        $this->setDisplayField('title');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->belongsTo('DashboardTabs', [
            'foreignKey' => 'dashboard_tab_id',
            'joinType'   => 'INNER',
        ]);
        $this->belongsTo('Types', [
            'foreignKey' => 'type_id',
            'joinType'   => 'INNER',
        ]);
        $this->belongsTo('Hosts', [
            'foreignKey' => 'host_id',
        ]);
        $this->belongsTo('Services', [
            'foreignKey' => 'service_id',
        ]);
    }

    /**
     * Default validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator {
        $validator
            ->integer('id')
            ->allowEmptyString('id', null, 'create');

        $validator
            ->integer('row')
            ->requirePresence('row', 'create')
            ->numeric('row', __('This field needs a numeric value.'))
            ->notEmptyString('row');

        $validator
            ->integer('col')
            ->requirePresence('col', 'create')
            ->numeric('col', __('This field needs a numeric value.'))
            ->notEmptyString('col');

        $validator
            ->integer('width')
            ->requirePresence('width', 'create')
            ->numeric('width', __('This field needs a numeric value.'))
            ->notEmptyString('width');

        $validator
            ->integer('height')
            ->requirePresence('height', 'create')
            ->numeric('height', __('This field needs a numeric value.'))
            ->notEmptyString('height');

        $validator
            ->scalar('title')
            ->maxLength('title', 255)
            ->allowEmptyString('title');

        $validator
            ->scalar('color')
            ->maxLength('color', 255)
            ->allowEmptyString('color');

        $validator
            ->scalar('directive')
            ->maxLength('directive', 255)
            ->requirePresence('directive', 'create')
            ->notEmptyString('directive');

        $validator
            ->scalar('icon')
            ->maxLength('icon', 255)
            ->requirePresence('icon', 'create')
            ->notEmptyString('icon');

        $validator
            ->scalar('json_data')
            ->maxLength('json_data', 2000)
            ->allowEmptyString('json_data');

        return $validator;
    }

    /**
     * Returns a rules checker object that will be used for validating
     * application integrity.
     *
     * @param \Cake\ORM\RulesChecker $rules The rules object to be modified.
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker {
        $rules->add($rules->existsIn(['dashboard_tab_id'], 'DashboardTabs'));
        $rules->add($rules->existsIn(['host_id'], 'Hosts'));
        $rules->add($rules->existsIn(['service_id'], 'Services'));

        return $rules;
    }

    /**
     * @param array $ACL_PERMISSIONS
     * @return array
     */
    public function getAvailableWidgets($ACL_PERMISSIONS = []) {
        //Default Widgets static dashboards - no permissions required
        $widgets = [
            [
                'type_id'   => 1,
                'title'     => __('Welcome'),
                'icon'      => 'fas fa-comment',
                'directive' => 'welcome-widget', //AngularJS directive,
                'width'     => 6,
                'height'    => 7,
                'default'   => [
                    'row' => 0,
                    'col' => 0
                ]
            ],
            [
                'type_id'   => 2,
                'title'     => __('Parent outages'),
                'icon'      => 'fas fa-exchange-alt',
                'directive' => 'parent-outages-widget',
                'width'     => 6,
                'height'    => 7,
                'default'   => [
                    'row' => 0,
                    'col' => 6
                ]
            ],

            [
                'type_id'   => 3,
                'title'     => __('Hosts pie chart'),
                'icon'      => 'fa fa-pie-chart',
                'directive' => 'hosts-piechart-widget',
                'width'     => 6,
                'height'    => 11,
                'default'   => [
                    'row' => 7,
                    'col' => 0
                ]
            ],
            [
                'type_id'   => 7,
                'title'     => __('Hosts pie chart 180'),
                'icon'      => 'fa fa-pie-chart',
                'directive' => 'hosts-piechart-180-widget',
                'width'     => 6,
                'height'    => 11
            ],
            [
                'type_id'   => 4,
                'title'     => __('Services pie chart'),
                'icon'      => 'fa fa-pie-chart',
                'directive' => 'services-piechart-widget',
                'width'     => 6,
                'height'    => 11,
                'default'   => [
                    'row' => 7,
                    'col' => 6
                ]
            ],
            [
                'type_id'   => 8,
                'title'     => __('Services pie chart 180'),
                'icon'      => 'fa fa-pie-chart',
                'directive' => 'services-piechart180-widget',
                'width'     => 6,
                'height'    => 11
            ],
            [
                'type_id'   => 11,
                'title'     => __('Traffic light'),
                'icon'      => 'fas fa-road',
                'directive' => 'trafficlight-widget',
                'width'     => 3,
                'height'    => 14
            ],
            [
                'type_id'   => 12,
                'title'     => __('Tachometer'),
                'icon'      => 'fas fa-tachometer-alt',
                'directive' => 'tachometer-widget',
                'width'     => 3,
                'height'    => 14
            ],
            [
                'type_id'   => 13,
                'title'     => __('Notice'),
                'icon'      => 'fas fa-pencil-square',
                'directive' => 'notice-widget',
                'width'     => 6,
                'height'    => 13
            ],
            /*
            [
                'type_id'   => 15,
                'title'     => __('Graphgenerator'),
                'icon'      => 'fa-area-chart',
                'directive' => 'graphgenerator-widget',
                'width'     => 6,
                'height'    => 7
            ]
            */
        ];

        //Depands on user rights
        if (isset($ACL_PERMISSIONS['downtimes']['host'])) {
            $widgets[] = [
                'type_id'   => 5,
                'title'     => __('Hosts in downtime'),
                'icon'      => 'fas fa-power-off',
                'directive' => 'hosts-downtime-widget',
                'width'     => 12,
                'height'    => 15,
                'default'   => [
                    'row' => 18,
                    'col' => 0
                ]
            ];
        }

        if (isset($ACL_PERMISSIONS['downtimes']['service'])) {
            $widgets[] = [
                'type_id'   => 6,
                'title'     => __('Services in downtime'),
                'icon'      => 'fas fa-power-off',
                'directive' => 'services-downtime-widget',
                'width'     => 12,
                'height'    => 15,
                'default'   => [
                    'row' => 32,
                    'col' => 0
                ]
            ];
        }

        if (isset($ACL_PERMISSIONS['hosts']['index'])) {
            $widgets[] = [
                'type_id'   => 9,
                'title'     => __('Host status list'),
                'icon'      => 'far fa-list-alt',
                'directive' => 'hosts-status-widget',
                'width'     => 12,
                'height'    => 16
            ];
            $widgets[] = [
                'type_id'   => 16,
                'title'     => __('Host status overview'),
                'icon'      => 'fas fa-info-circle',
                'directive' => 'host-status-overview-widget',
                'width'     => 3,
                'height'    => 15
            ];
        }

        if (isset($ACL_PERMISSIONS['services']['index'])) {
            $widgets[] = [
                'type_id'   => 10,
                'title'     => __('Service status list'),
                'icon'      => 'far fa-list-alt',
                'directive' => 'services-status-widget',
                'width'     => 12,
                'height'    => 16
            ];
            $widgets[] = [
                'type_id'   => 17,
                'title'     => __('Service status overview'),
                'icon'      => 'fas fa-info-circle',
                'directive' => 'service-status-overview-widget',
                'width'     => 3,
                'height'    => 15
            ];
        }

        $modules = PluginManager::getAvailablePlugins();
        foreach ($modules as $module) {
            $className = sprintf('\\%s\\Lib\\Widgets', $module);
            if (class_exists($className)) {

                /** @var ModuleWidgetsInterface $PluginWidgets */
                $PluginWidgets = new $className($ACL_PERMISSIONS);

                foreach ($PluginWidgets->getAvailableWidgets() as $pluginWidget) {
                    $widgets[] = $pluginWidget;
                }
            }
        }

        return $widgets;
    }

    /**
     * @param int $id
     * @return bool
     */
    public function existsById($id) {
        return $this->exists(['Widgets.id' => $id]);
    }

    /**
     * @param int $typeId
     * @param array $ACL_PERMISSIONS
     * @return bool
     */
    public function isWidgetAvailable($typeId, $ACL_PERMISSIONS = []) {
        $typeId = (int)$typeId;
        foreach ($this->getAvailableWidgets($ACL_PERMISSIONS) as $widget) {
            if ($widget['type_id'] === $typeId) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param int $typeId
     * @param array $ACL_PERMISSIONS
     * @return array
     */
    public function getWidgetByTypeId($typeId, $ACL_PERMISSIONS = []) {
        $typeId = (int)$typeId;
        foreach ($this->getAvailableWidgets($ACL_PERMISSIONS) as $widget) {
            if ($widget['type_id'] === $typeId) {
                return $widget;
            }
        }
        return [];
    }

    /**
     * @param $ACL_PERMISSIONS
     * @return array
     */
    public function getDefaultWidgets($ACL_PERMISSIONS) {
        $widgets = [];
        foreach ($this->getAvailableWidgets($ACL_PERMISSIONS) as $widget) {
            if (isset($widget['default'])) {
                $widget['row'] = $widget['default']['row'];
                $widget['col'] = $widget['default']['col'];
                $widget['color'] = 'jarviswidget-color-blueDark';
                unset($widget['default']);
                $widgets[] = $widget;
            }
        }

        return $widgets;
    }

    public function getWidgetByIdAsCake2($id){
        $result = $this->find()
            ->where([
                'Widgets.id' => $id
            ])
            ->disableHydration()
            ->first();
        return $this->formatFirstResultAsCake2($result);
    }
}