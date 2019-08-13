<?php
// Copyright (C) <2015>  <it-novum GmbH>
//
// This file is dual licensed
//
// 1.
//	This program is free software: you can redistribute it and/or modify
//	it under the terms of the GNU General Public License as published by
//	the Free Software Foundation, version 3 of the License.
//
//	This program is distributed in the hope that it will be useful,
//	but WITHOUT ANY WARRANTY; without even the implied warranty of
//	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//	GNU General Public License for more details.
//
//	You should have received a copy of the GNU General Public License
//	along with this program.  If not, see <http://www.gnu.org/licenses/>.
//

// 2.
//	If you purchased an openITCOCKPIT Enterprise Edition you can use this file
//	under the terms of the openITCOCKPIT Enterprise Edition license agreement.
//	License agreement and license key will be shipped with the order
//	confirmation.
use App\Model\Table\ContainersTable;
use App\Model\Table\HostgroupsTable;
use App\Model\Table\HostsTable;
use App\Model\Table\SystemdowntimesTable;
use Cake\ORM\TableRegistry;
use itnovum\openITCOCKPIT\Core\AngularJS\Request\AngularRequest;
use itnovum\openITCOCKPIT\Core\DbBackend;
use itnovum\openITCOCKPIT\Core\System\Gearman;
use itnovum\openITCOCKPIT\Core\SystemdowntimesConditions;
use itnovum\openITCOCKPIT\Core\Views\ContainerPermissions;
use itnovum\openITCOCKPIT\Database\PaginateOMat;
use itnovum\openITCOCKPIT\Filter\SystemdowntimesFilter;


/**
 * @property AppPaginatorComponent $Paginator
 * @property DbBackend $DbBackend
 * @property AppAuthComponent $Auth
 *
 * @property Systemdowntime $Systemdowntime
 * @property Host $Host
 * @property Service $Service
 * @property Hostgroup $Hostgroup
 */
class SystemdowntimesController extends AppController {
    public $uses = [
        'Systemdowntime',
        'Host',
        'Service',
        'Hostgroup',
        'Container',
    ];
    public $components = [
        'ListFilter.ListFilter',
        'RequestHandler',
        'CustomValidationErrors',
        'GearmanClient',
    ];
    public $helpers = [
        'ListFilter.ListFilter',
        'Status',
        'Monitoring',
        'CustomValidationErrors',
        'Uuid',
    ];
    public $layout = 'blank';

    public function host() {
        if (!$this->isAngularJsRequest()) {
            //Only ship template
            return;
        }

        $AngularRequest = new AngularRequest($this->request);
        $PaginateOMat = new PaginateOMat($this->Paginator, $this, $this->isScrollRequest(), $AngularRequest->getPage());

        $SystemdowntimesFilter = new SystemdowntimesFilter($this->request);
        $Conditions = new SystemdowntimesConditions();

        //Process conditions
        if ($this->hasRootPrivileges) {
            $Conditions->setContainerIds($this->MY_RIGHTS);
        }
        $Conditions->setOrder($AngularRequest->getOrderForPaginator('Systemdowntimes.from_time', 'desc'));
        $Conditions->setConditions($SystemdowntimesFilter->hostFilter());

        /** @var $SystemdowntimesTable SystemdowntimesTable */
        $SystemdowntimesTable = TableRegistry::getTableLocator()->get('Systemdowntimes');

        $recurringHostDowntimes = $SystemdowntimesTable->getRecurringHostDowntimes($Conditions, $PaginateOMat);

        //Prepare data for API
        $all_host_recurring_downtimes = [];
        foreach ($recurringHostDowntimes as $recurringHostDowntime) {
            if (!isset($recurringHostDowntime['host'])) {
                continue;
            }

            if ($this->hasRootPrivileges) {
                $allowEdit = true;
            } else {
                $containerIds = \Cake\Utility\Hash::extract($recurringHostDowntime['host']['hosts_to_containers_sharing'], '{n}.id');
                $ContainerPermissions = new ContainerPermissions($this->MY_RIGHTS_LEVEL, $containerIds);
                $allowEdit = $ContainerPermissions->hasPermission();
            }

            $Host = new \itnovum\openITCOCKPIT\Core\Views\Host($recurringHostDowntime['host']);
            $Systemdowntime = new \itnovum\openITCOCKPIT\Core\Views\Systemdowntime($recurringHostDowntime);

            $tmpRecord = [
                'Host'           => $Host->toArray(),
                'Systemdowntime' => $Systemdowntime->toArray()
            ];
            $tmpRecord['Host']['allow_edit'] = $allowEdit;
            $all_host_recurring_downtimes[] = $tmpRecord;
        }

        $this->set('all_host_recurring_downtimes', $all_host_recurring_downtimes);
        $toJson = ['all_host_recurring_downtimes', 'paging'];
        if ($this->isScrollRequest()) {
            $toJson = ['all_host_recurring_downtimes', 'scroll'];
        }
        $this->set('_serialize', $toJson);
    }

    public function service() {
        if (!$this->isAngularJsRequest()) {
            //Only ship template
            return;
        }

        $AngularRequest = new AngularRequest($this->request);
        $PaginateOMat = new PaginateOMat($this->Paginator, $this, $this->isScrollRequest(), $AngularRequest->getPage());

        $SystemdowntimesFilter = new SystemdowntimesFilter($this->request);
        $Conditions = new SystemdowntimesConditions();

        //Process conditions
        if ($this->hasRootPrivileges) {
            $Conditions->setContainerIds($this->MY_RIGHTS);
        }
        $Conditions->setOrder($AngularRequest->getOrderForPaginator('Systemdowntimes.from_time', 'desc'));
        $Conditions->setConditions($SystemdowntimesFilter->serviceFilter());

        /** @var $SystemdowntimesTable SystemdowntimesTable */
        $SystemdowntimesTable = TableRegistry::getTableLocator()->get('Systemdowntimes');

        $recurringServiceDowntimes = $SystemdowntimesTable->getRecurringServiceDowntimes($Conditions, $PaginateOMat);

        //Prepare data for API
        $all_service_recurring_downtimes = [];
        foreach ($recurringServiceDowntimes as $recurringServiceDowntime) {
            if (!isset($recurringServiceDowntime['service'])) {
                continue;
            }

            if ($this->hasRootPrivileges) {
                $allowEdit = true;
            } else {
                $containerIds = \Cake\Utility\Hash::extract($recurringServiceDowntime['host']['hosts_to_containers_sharing'], '{n}.id');
                $ContainerPermissions = new ContainerPermissions($this->MY_RIGHTS_LEVEL, $containerIds);
                $allowEdit = $ContainerPermissions->hasPermission();
            }

            $Service = new \itnovum\openITCOCKPIT\Core\Views\Service($recurringServiceDowntime['service'], $recurringServiceDowntime['servicename'], $allowEdit);
            $Host = new \itnovum\openITCOCKPIT\Core\Views\Host($recurringServiceDowntime['service']['host'], $allowEdit);
            $Systemdowntime = new \itnovum\openITCOCKPIT\Core\Views\Systemdowntime($recurringServiceDowntime);

            $tmpRecord = [
                'Service'        => $Service->toArray(),
                'Host'           => $Host->toArray(),
                'Systemdowntime' => $Systemdowntime->toArray()
            ];
            $tmpRecord['Host']['allow_edit'] = $allowEdit;
            $all_service_recurring_downtimes[] = $tmpRecord;
        }


        $this->set('all_service_recurring_downtimes', $all_service_recurring_downtimes);
        $toJson = ['all_service_recurring_downtimes', 'paging'];
        if ($this->isScrollRequest()) {
            $toJson = ['all_service_recurring_downtimes', 'scroll'];
        }
        $this->set('_serialize', $toJson);
    }

    public function hostgroup() {
        if (!$this->isAngularJsRequest()) {
            //Only ship template
            return;
        }

        $AngularRequest = new AngularRequest($this->request);
        $PaginateOMat = new PaginateOMat($this->Paginator, $this, $this->isScrollRequest(), $AngularRequest->getPage());

        $SystemdowntimesFilter = new SystemdowntimesFilter($this->request);
        $Conditions = new SystemdowntimesConditions();

        //Process conditions
        if ($this->hasRootPrivileges) {
            $Conditions->setContainerIds($this->MY_RIGHTS);
        }
        $Conditions->setOrder($AngularRequest->getOrderForPaginator('Systemdowntimes.from_time', 'desc'));
        $Conditions->setConditions($SystemdowntimesFilter->hostgroupFilter());

        /** @var $SystemdowntimesTable SystemdowntimesTable */
        $SystemdowntimesTable = TableRegistry::getTableLocator()->get('Systemdowntimes');

        $recurringHostgroupDowntimes = $SystemdowntimesTable->getRecurringHostgroupDowntimes($Conditions, $PaginateOMat);

        //Prepare data for API
        $all_hostgroup_recurring_downtimes = [];
        foreach ($recurringHostgroupDowntimes as $recurringHostgroupDowntime) {
            if (!isset($recurringHostgroupDowntime['hostgroup'])) {
                continue;
            }

            if ($this->hasRootPrivileges) {
                $allowEdit = true;
            } else {
                $ContainerPermissions = new ContainerPermissions($this->MY_RIGHTS_LEVEL, [$recurringHostgroupDowntime['hostgroup']['container_id']]);
                $allowEdit = $ContainerPermissions->hasPermission();
            }

            $Systemdowntime = new \itnovum\openITCOCKPIT\Core\Views\Systemdowntime($recurringHostgroupDowntime);

            $tmpRecord = [
                'Container'      => $recurringHostgroupDowntime['hostgroup']['container'],
                'Hostgroup'      => $recurringHostgroupDowntime['hostgroup'],
                'Systemdowntime' => $Systemdowntime->toArray()
            ];
            $tmpRecord['Hostgroup']['allow_edit'] = $allowEdit;
            $all_hostgroup_recurring_downtimes[] = $tmpRecord;
        }


        $this->set('all_hostgroup_recurring_downtimes', $all_hostgroup_recurring_downtimes);
        $toJson = ['all_hostgroup_recurring_downtimes', 'paging'];
        if ($this->isScrollRequest()) {
            $toJson = ['all_hostgroup_recurring_downtimes', 'scroll'];
        }
        $this->set('_serialize', $toJson);
    }

    public function node() {
        if (!$this->isAngularJsRequest()) {
            //Only ship template
            return;
        }

        $AngularRequest = new AngularRequest($this->request);
        $PaginateOMat = new PaginateOMat($this->Paginator, $this, $this->isScrollRequest(), $AngularRequest->getPage());

        $SystemdowntimesFilter = new SystemdowntimesFilter($this->request);
        $Conditions = new SystemdowntimesConditions();

        //Process conditions
        if ($this->hasRootPrivileges) {
            $Conditions->setContainerIds($this->MY_RIGHTS);
        }
        $Conditions->setOrder($AngularRequest->getOrderForPaginator('Systemdowntimes.from_time', 'desc'));
        $Conditions->setConditions($SystemdowntimesFilter->nodeFilter());

        /** @var $SystemdowntimesTable SystemdowntimesTable */
        $SystemdowntimesTable = TableRegistry::getTableLocator()->get('Systemdowntimes');

        $recurringNodeDowntimes = $SystemdowntimesTable->getRecurringNodeDowntimes($Conditions, $PaginateOMat);

        //Prepare data for API
        $all_node_recurring_downtimes = [];
        foreach ($recurringNodeDowntimes as $recurringNodeDowntime) {
            if (!isset($recurringNodeDowntime['container'])) {
                continue;
            }

            if ($this->hasRootPrivileges) {
                $allowEdit = true;
            } else {
                $ContainerPermissions = new ContainerPermissions($this->MY_RIGHTS_LEVEL, [$recurringNodeDowntime['hostgroup']['container_id']]);
                $allowEdit = $ContainerPermissions->hasPermission();
            }

            $Systemdowntime = new \itnovum\openITCOCKPIT\Core\Views\Systemdowntime($recurringNodeDowntime);

            $tmpRecord = [
                'Container'      => $recurringNodeDowntime['container'],
                'Systemdowntime' => $Systemdowntime->toArray()
            ];
            $tmpRecord['Container']['allow_edit'] = $allowEdit;
            $all_node_recurring_downtimes[] = $tmpRecord;
        }


        $this->set('all_node_recurring_downtimes', $all_node_recurring_downtimes);
        $toJson = ['all_node_recurring_downtimes', 'paging'];
        if ($this->isScrollRequest()) {
            $toJson = ['all_node_recurring_downtimes', 'scroll'];
        }
        $this->set('_serialize', $toJson);
    }

    public function addHostdowntime() {
        if (!$this->isAngularJsRequest()) {
            // ship html template
            return;
        }

        if ($this->request->is('post') || $this->request->is('put')) {
            /** @var $SystemdowntimesTable SystemdowntimesTable */
            $SystemdowntimesTable = TableRegistry::getTableLocator()->get('Systemdowntimes');
            $data = $this->request->data('Systemdowntime');


            if (!isset($data['object_id']) || empty($data['object_id'])) {
                $this->response->statusCode(400);
                $this->set('error', [
                    'object_id' => [
                        '_empty' => __('You have to select at least on object.')
                    ]
                ]);
                $this->set('_serialize', ['error']);
                return;
            }

            if (!is_array($data['object_id'])) {
                $data['object_id'] = [$data['object_id']];
            }

            if (isset($data['weekdays']) && is_array($data['weekdays'])) {
                $data['weekdays'] = implode(',', $data['weekdays']);
            }

            $User = new \itnovum\openITCOCKPIT\Core\ValueObjects\User($this->Auth);

            $data['author'] = $User->getFullName();

            $objectIds = $data['object_id'];
            unset($data['object_id']);

            $Entities = [];
            foreach ($objectIds as $objectId) {
                $tmpData = $data;
                $tmpData['object_id'] = $objectId;
                $Entity = $SystemdowntimesTable->newEntity($tmpData);
                if ($Entity->hasErrors()) {
                    //On entity has an error so ALL entities has an error!
                    $this->response->statusCode(400);
                    $this->set('error', $Entity->getErrors());
                    $this->set('_serialize', ['error']);
                    return;
                }

                //No errors
                $Entities[] = $Entity;
            }

            $isRecurringDowntime = $data['is_recurring'] === 1 || $data['is_recurring'] === '1';
            $success = true;

            if ($isRecurringDowntime) {
                //Recurring downtimes will get saved to the database
                $success = $SystemdowntimesTable->saveMany($Entities);
            } else {
                //Normal downtimes will be passed to the monitoring engine
                $GearmanClient = new Gearman();
                /** @var $HostsTable HostsTable */
                $HostsTable = TableRegistry::getTableLocator()->get('Hosts');

                foreach ($Entities as $Entity) {
                    $hostUuid = $HostsTable->getHostUuidById($Entity->get('object_id'));
                    $start = strtotime(
                        sprintf(
                            '%s %s',
                            $Entity->get('from_date'),
                            $Entity->get('from_time')
                        ));
                    $end = strtotime(
                        sprintf('%s %s',
                            $Entity->get('to_date'),
                            $Entity->get('to_time')
                        ));

                    $payload = [
                        'hostUuid'     => $hostUuid,
                        'downtimetype' => $Entity->get('downtimetype_id'),
                        'start'        => $start,
                        'end'          => $end,
                        'comment'      => $Entity->get('comment'),
                        'author'       => $this->Auth->user('full_name'),
                    ];
                    $GearmanClient->sendBackground('createHostDowntime', $payload);
                }
            }

            $this->set('success', $success);
            $this->set('_serialize', ['success']);
        }
    }

    /**
     * @deprecated
     */
    public function addHostgroupdowntime() {
        if (!$this->isAngularJsRequest()) {
            // ship html template
            return;
            //$this->set('back_url', $this->referer());
        }


        if ($this->request->is('post') || $this->request->is('put')) {
            if (isset($this->request->data['Systemdowntime']['weekdays']) && is_array($this->request->data['Systemdowntime']['weekdays'])) {
                $this->request->data['Systemdowntime']['weekdays'] = implode(',', $this->request->data['Systemdowntime']['weekdays']);
            }

            $isRecurringDowntime = ($this->request->data('Systemdowntime.is_recurring') == 1);
            $this->request->data = $this->_rewritePostData();

            if ($isRecurringDowntime) {
                $this->Systemdowntime->validate = $this->Systemdowntime->getValidationRulesForRecurringDowntimes();
                $this->Systemdowntime->set($this->request->data);
                if ($this->Systemdowntime->validateMany($this->request->data)) {
                    $this->Systemdowntime->create();
                    if ($this->Systemdowntime->saveAll($this->request->data)) {
                        if ($this->isAngularJsRequest()) {
                            $this->set('success', true);
                            $this->set('_serialize', ['success']);
                        }
                        $this->serializeId();
                    }
                } else {
                    $this->serializeErrorMessage();
                }
                return;
            }

            if ($isRecurringDowntime === false) {

                /** @var $HostgroupsTable HostgroupsTable */
                $HostgroupsTable = TableRegistry::getTableLocator()->get('Hostgroups');

                $this->Systemdowntime->set($this->request->data);
                if ($this->Systemdowntime->validateMany($this->request->data)) {
                    foreach ($this->request->data as $request) {
                        $start = strtotime(
                            sprintf(
                                '%s %s',
                                $request['Systemdowntime']['from_date'],
                                $request['Systemdowntime']['from_time']
                            ));
                        $end = strtotime(
                            sprintf('%s %s',
                                $request['Systemdowntime']['to_date'],
                                $request['Systemdowntime']['to_time']
                            ));

                        $payload = [
                            'hostgroupUuid' => $HostgroupsTable->getHostgroupUuidById($request['Systemdowntime']['object_id']),
                            'downtimetype'  => $request['Systemdowntime']['downtimetype_id'],
                            'start'         => $start,
                            'end'           => $end,
                            'comment'       => $request['Systemdowntime']['comment'],
                            'author'        => $this->Auth->user('full_name'),
                        ];

                        $this->GearmanClient->sendBackground('createHostgroupDowntime', $payload);
                    }
                    $this->set('success', true);
                    $this->set('_serialize', ['success']);
                    return;
                }
                $this->serializeErrorMessage();
                return;
            }
        }
    }

    /**
     * @deprecated
     */
    public function addServicedowntime() {
        if (!$this->isAngularJsRequest()) {
            // ship html template
            return;
            //$this->set('back_url', $this->referer());
        }


        if ($this->request->is('post') || $this->request->is('put')) {
            if (isset($this->request->data['Systemdowntime']['weekdays']) && is_array($this->request->data['Systemdowntime']['weekdays'])) {
                $this->request->data['Systemdowntime']['weekdays'] = implode(',', $this->request->data['Systemdowntime']['weekdays']);
            }

            $isRecurringDowntime = ($this->request->data('Systemdowntime.is_recurring') == 1);
            $this->request->data = $this->_rewritePostData();

            if ($isRecurringDowntime) {
                $this->Systemdowntime->validate = $this->Systemdowntime->getValidationRulesForRecurringDowntimes();
                $this->Systemdowntime->set($this->request->data);
                if ($this->Systemdowntime->validateMany($this->request->data)) {
                    $this->Systemdowntime->create();
                    if ($this->Systemdowntime->saveAll($this->request->data)) {
                        if ($this->isAngularJsRequest()) {
                            $this->set('success', true);
                            $this->set('_serialize', ['success']);
                        }
                        $this->serializeId();
                    }
                } else {
                    $this->serializeErrorMessage();
                }
                return;
            }


            if ($isRecurringDowntime === false) {
                $this->Systemdowntime->set($this->request->data);
                if ($this->Systemdowntime->validateMany($this->request->data)) {
                    foreach ($this->request->data as $request) {
                        $start = strtotime(
                            sprintf(
                                '%s %s',
                                $request['Systemdowntime']['from_date'],
                                $request['Systemdowntime']['from_time']
                            ));
                        $end = strtotime(
                            sprintf('%s %s',
                                $request['Systemdowntime']['to_date'],
                                $request['Systemdowntime']['to_time']
                            ));

                        $service = $this->Service->find('first', [
                            'recursive'  => -1,
                            'contain'    => [
                                'Host' => [
                                    'fields' => [
                                        'Host.uuid'
                                    ]
                                ]
                            ],
                            'fields'     => [
                                'Service.uuid'
                            ],
                            'conditions' => [
                                'Service.id' => $request['Systemdowntime']['object_id']
                            ]
                        ]);
                        $payload = [
                            'hostUuid'    => $service['Host']['uuid'],
                            'serviceUuid' => $service['Service']['uuid'],
                            'start'       => $start,
                            'end'         => $end,
                            'comment'     => $request['Systemdowntime']['comment'],
                            'author'      => $this->Auth->user('full_name'),
                        ];
                        $this->GearmanClient->sendBackground('createServiceDowntime', $payload);
                    }
                    $this->set('success', true);
                    $this->set('_serialize', ['success']);
                    return;
                }
                $this->serializeErrorMessage();
                return;
            }
        }
    }

    /**
     * @deprecated
     */
    public function addContainerdowntime() {
        if (!$this->isAngularJsRequest()) {
            // ship html template
            return;
            //$this->set('back_url', $this->referer());
        }


        /** @var $ContainersTable ContainersTable */
        $ContainersTable = TableRegistry::getTableLocator()->get('Containers');

        $childrenContainers = [];

        if ($this->request->is('post') || $this->request->is('put')) {
            if (isset($this->request->data['Systemdowntime']['weekdays']) && is_array($this->request->data['Systemdowntime']['weekdays'])) {
                $this->request->data['Systemdowntime']['weekdays'] = implode(',', $this->request->data['Systemdowntime']['weekdays']);
            }

            $isRecurringDowntime = ($this->request->data('Systemdowntime.is_recurring') == 1);

            if ($this->request->data('Systemdowntime.inherit_downtime') == 1) {
                $childrenContainers = [];

                foreach ($this->request->data('Systemdowntime.object_id') as $containerId) {
                    if ($containerId == ROOT_CONTAINER) {
                        $childrenContainers = $ContainersTable->resolveChildrenOfContainerIds(ROOT_CONTAINER, true);
                    } else {
                        $childrenContainers = $ContainersTable->resolveChildrenOfContainerIds($this->request->data('Systemdowntime.object_id'));
                        $childrenContainers = $ContainersTable->removeRootContainer($childrenContainers);
                    }
                }


                $objectIds = [];
                foreach ($childrenContainers as $childrenContainer) {
                    if (isset($this->MY_RIGHTS_LEVEL[$childrenContainer])) {
                        if ((int)$this->MY_RIGHTS_LEVEL[$childrenContainer] === WRITE_RIGHT) {
                            $objectIds[] = (int)$childrenContainer;
                        }
                    }
                }
                $this->request->data['Systemdowntime']['object_id'] = $objectIds;
            }


            if ($isRecurringDowntime) {
                $this->request->data = $this->_rewritePostData();
                $this->Systemdowntime->validate = $this->Systemdowntime->getValidationRulesForRecurringDowntimes();
                $this->Systemdowntime->set($this->request->data);
                if ($this->Systemdowntime->validateMany($this->request->data)) {
                    $this->Systemdowntime->create();
                    if ($this->Systemdowntime->saveAll($this->request->data)) {
                        if ($this->isAngularJsRequest()) {
                            $this->set('success', true);
                            $this->set('_serialize', ['success']);
                        }
                        $this->serializeId();
                    }
                } else {
                    $this->serializeErrorMessage();
                }
                return;
            }

            if ($isRecurringDowntime === false) {

                $postDataForValidate = $this->_rewritePostData();
                $this->Systemdowntime->set($postDataForValidate);
                if ($this->Systemdowntime->validateMany($postDataForValidate)) {
                    $hosts = $this->Host->hostsByContainerId(
                        $this->request->data['Systemdowntime']['object_id'],
                        'list',
                        ['Host.disabled' => 0],
                        'uuid'
                    );
                    foreach ($hosts as $hostUuid => $hostName) {
                        $start = strtotime(
                            sprintf(
                                '%s %s',
                                $this->request->data['Systemdowntime']['from_date'],
                                $this->request->data['Systemdowntime']['from_time']
                            ));
                        $end = strtotime(
                            sprintf('%s %s',
                                $this->request->data['Systemdowntime']['to_date'],
                                $this->request->data['Systemdowntime']['to_time']
                            ));

                        $payload = [
                            'hostUuid'     => $hostUuid,
                            'downtimetype' => $this->request->data['Systemdowntime']['downtimetype_id'],
                            'start'        => $start,
                            'end'          => $end,
                            'comment'      => $this->request->data['Systemdowntime']['comment'],
                            'author'       => $this->Auth->user('full_name'),
                        ];
                        $this->GearmanClient->sendBackground('createHostDowntime', $payload);
                    }
                    $this->set('success', true);
                    $this->set('_serialize', ['success']);
                    return;
                }
                $this->serializeErrorMessage();
                return;
            }
        }
    }

    /**
     * @param int|null $id
     */
    public function delete($id = null) {
        if (!$this->request->is('post')) {
            throw new MethodNotAllowedException();
        }

        /** @var $SystemdowntimesTable SystemdowntimesTable */
        $SystemdowntimesTable = TableRegistry::getTableLocator()->get('Systemdowntimes');

        if (!$SystemdowntimesTable->existsById($id)) {
            throw new NotFoundException(__('Invalide Systemdowntime'));
        }

        $systemdowntime = $SystemdowntimesTable->get($id);
        if ($SystemdowntimesTable->delete($systemdowntime)) {
            $this->set('success', true);
            $this->set('message', __('Systemdowntime successfully deleted'));
            $this->set('_serialize', ['success', 'message']);
            return;
        }

        $this->response->statusCode(400);
        $this->set('success', false);
        $this->set('message', __('Error while deleting systemdowntime'));
        $this->set('_serialize', ['success', 'message']);
    }

    /**
     * @return array
     * @deprecated
     */
    private function _rewritePostData() {
        /*
        why we need this function? The problem is, may be a user want to save the downtime for more that one host. the array we get from $this->request->data looks like this:
            array(
                'Systemdowntime' => array(
                    'downtimetype' => 'host',
                    'object_id' => array(
                        (int) 0 => '1',
                        (int) 1 => '2'
                    ),
                    'downtimetype_id' => '0',
                    'comment' => 'In maintenance',
                    'is_recurring' => '1',
                    'weekdays' => '1',
                    'recurring_days_month' => '1',
                    'from_date' => '11.09.2014',
                    'from_time' => '99:99',
                    'to_date' => '14.09.2014',
                    'to_time' => '06:09'
                )
            )

        the big problem is the object_id, this throws us an "Array to string conversion". So we need to rewrite the post array fo some like this:

        array(
            (int) 0 => array(
                'Systemdowntime' => array(
                    'downtimetype' => 'host',
                    'object_id' => '2',
                    'downtimetype_id' => '0',
                    'comment' => 'In maintenance',
                    'is_recurring' => '1',
                    'weekdays' => '',
                    'recurring_days_month' => 'asdadasd',
                    'from_date' => '11.09.2014',
                    'from_time' => '06:09',
                    'to_date' => '14.09.2014',
                    'to_time' => '06:09'
                )
            ),
            (int) 1 => array(
                'Systemdowntime' => array(
                    'downtimetype' => 'host',
                    'object_id' => '3',
                    'downtimetype_id' => '0',
                    'comment' => 'In maintenance',
                    'is_recurring' => '1',
                    'weekdays' => '',
                    'recurring_days_month' => 'asdadasd',
                    'from_date' => '11.09.2014',
                    'from_time' => '06:09',
                    'to_date' => '14.09.2014',
                    'to_time' => '06:09'
                )
            )
        )

        */
        if (empty($this->request->data('Systemdowntime.object_id'))) {
            return [$this->request->data];
        }
        $return = [];
        if (is_array($this->request->data['Systemdowntime']['object_id'])) {
            foreach ($this->request->data['Systemdowntime']['object_id'] as $object_id) {
                $tmp['Systemdowntime'] = $this->request->data['Systemdowntime'];
                $tmp['Systemdowntime']['object_id'] = $object_id;
                $tmp['Systemdowntime']['author'] = $this->Auth->user('full_name');
                $return[] = $tmp;
            }
        }
        return $return;

    }
}
