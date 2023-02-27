angular.module('openITCOCKPIT')
    .controller('BrowsersIndexController', function($scope, $http, $window, $rootScope, $httpParamSerializer, $stateParams, SortService, MassChangeService, QueryStringService, $state){
        SortService.setSort('Hoststatus.current_state');
        SortService.setDirection('desc');

        $scope.containerId = QueryStringService.getStateValue($stateParams, 'containerId', 1); //Default ROOT_CONTAINER

        $scope.containers = [];
        $scope.data = {
            containerFilter: ''
        };
        $scope.recursiveBrowser = false;

        /*** Filter Settings ***/
        var defaultFilter = function(){
            $scope.filter = {
                Hoststatus: {
                    current_state: QueryStringService.hoststate($stateParams),
                    acknowledged: QueryStringService.getValue('has_been_acknowledged', false) === '1',
                    not_acknowledged: QueryStringService.getValue('has_not_been_acknowledged', false) === '1',
                    in_downtime: QueryStringService.getValue('in_downtime', false) === '1',
                    not_in_downtime: QueryStringService.getValue('not_in_downtime', false) === '1',
                    output: ''
                },
                Host: {
                    name: QueryStringService.getValue('filter[Hosts.name]', ''),
                    keywords: '',
                    address: QueryStringService.getValue('filter[Hosts.address]', ''),
                    satellite_id: [],
                    host_type: []
                }
            };
        };
        /*** Filter end ***/

        /*** column vars ***/
        $scope.fields = [];
        $scope.columnsLength = 17;
        $scope.columnsTableKey = 'HostsBrowserColumns';

        /*** columns functions
         columns:
          ['Hoststatus',
         'is acknowledged',
         'is in downtime',
         'Notifications enabled',
         'Shared',
         'Passively transferred host',
         'Priority',
         'Host name',
         'Host description',
         'IP address',
         'Last state change',
         'Last check',
         'Host output',
         'Instance',
         'Service Summary ',
         'Host notes',
         'Host type'] ***/
        $scope.defaultColumns = function(){
            $scope.fields = [true,true,true,false,true,true,false,true,false,true,true,true,true,true,false,false,false];
            $window.localStorage.removeItem($scope.columnsTableKey);
        };

        $scope.saveColumns = function(){
            $window.localStorage.removeItem($scope.columnsTableKey);
            $window.localStorage.setItem($scope.columnsTableKey,JSON.stringify($scope.fields));

        }

        $scope.loadColumns = function(){
            var fields =  JSON.parse($window.localStorage.getItem($scope.columnsTableKey));
            if(typeof fields !== undefined && Array.isArray(fields)) {
                $scope.fields = fields;
            }else {
                $scope.defaultColumns()
            }
        }

        $scope.triggerLoadColumns= function(fields){
            $scope.fields = fields;
        };
        /*** end columns functions ***/

        $scope.massChange = {};
        $scope.selectedElements = 0;
        $scope.deleteUrl = '/hosts/delete/';
        $scope.deactivateUrl = '/hosts/deactivate/';

        $scope.init = true;
        $scope.showFilter = false;
        $scope.showFields = false;
        $scope.load = function(){
            $('[data-toggle="popover"]').popover('dispose');
            $http.get("/browsers/index/" + $scope.containerId + ".json", {
                params: {
                    angular: true
                }
            }).then(function(result){
                $scope.init = false;

                $scope.containersFromApi = result.data.containers;
                $scope.containers = $scope.containersFromApi; //We need the original containers for filter

                $scope.recursiveBrowser = result.data.recursiveBrowser;
                $scope.breadcrumbs = result.data.breadcrumbs;

                $scope.loadHosts();
                $scope.loadStatusCounts();
            });
        };

        $scope.loadHosts = function(){
            var hasBeenAcknowledged = '';
            var inDowntime = '';
            if($scope.filter.Hoststatus.acknowledged ^ $scope.filter.Hoststatus.not_acknowledged){
                hasBeenAcknowledged = $scope.filter.Hoststatus.acknowledged === true;
            }
            if($scope.filter.Hoststatus.in_downtime ^ $scope.filter.Hoststatus.not_in_downtime){
                inDowntime = $scope.filter.Hoststatus.in_downtime === true;
            }

            var params = {
                'angular': true,
                'sort': SortService.getSort(),
                'page': $scope.currentPage,
                'direction': SortService.getDirection(),
                'filter[Hosts.name]': $scope.filter.Host.name,
                'filter[Hoststatus.output]': $scope.filter.Hoststatus.output,
                'filter[Hoststatus.current_state][]': $rootScope.currentStateForApi($scope.filter.Hoststatus.current_state),
                'filter[Hosts.keywords][]': $scope.filter.Host.keywords.split(','),
                'filter[Hoststatus.problem_has_been_acknowledged]': hasBeenAcknowledged,
                'filter[Hoststatus.scheduled_downtime_depth]': inDowntime,
                'filter[Hosts.address]': $scope.filter.Host.address,
                'filter[Hosts.satellite_id][]': $scope.filter.Host.satellite_id,
                'filter[Hosts.host_type][]': $scope.filter.Host.host_type,
                'BrowserContainerId': $scope.containerId
            };

            $http.get("/hosts/index.json", {
                params: params
            }).then(function(result){
                $scope.hosts = result.data.all_hosts;
                $scope.paging = result.data.paging;

            }, function errorCallback(result){
                if(result.status === 403){
                    $state.go('403');
                }

                if(result.status === 404){
                    $state.go('404');
                }
            });
        };

        $scope.loadStatusCounts = function(){
            $http.get("/angular/statuscount.json", {
                params: {
                    angular: true,
                    'containerIds[]': $scope.containerId,
                    'recursive': $scope.recursiveBrowser
                }
            }).then(function(result){
                $scope.hoststatusCountHash = result.data.hoststatusCount;
                $scope.servicestatusCountHash = result.data.servicestatusCount;

                $scope.hoststatusSum = result.data.hoststatusSum;
                $scope.servicestatusSum = result.data.servicestatusSum;

                $scope.hoststatusCountPercentage = result.data.hoststatusCountPercentage;
                $scope.servicestatusCountPercentage = result.data.servicestatusCountPercentage;

            });
        };

        $scope.changeContainerId = function(containerId){
            $scope.containerId = containerId;
            $scope.load();
        };

        $scope.triggerFilter = function(){
            $scope.showFilter = !$scope.showFilter === true;
        };

        $scope.triggerFields = function(){
            $scope.showFields = !$scope.showFields === true;
        };

        $scope.resetFilter = function(){
            defaultFilter();
            $scope.undoSelection();
        };

        $scope.selectAll = function(){
            if($scope.hosts){
                for(var key in $scope.hosts){
                    if($scope.hosts[key].Host.allow_edit){
                        var id = $scope.hosts[key].Host.id;
                        $scope.massChange[id] = true;
                    }
                }
            }
        };

        $scope.undoSelection = function(){
            MassChangeService.clearSelection();
            $scope.massChange = MassChangeService.getSelected();
            $scope.selectedElements = MassChangeService.getCount();
        };

        $scope.getObjectForDelete = function(host){
            var object = {};
            object[host.Host.id] = host.Host.hostname;
            return object;
        };

        $scope.getObjectsForDelete = function(){
            var objects = {};
            var selectedObjects = MassChangeService.getSelected();
            for(var key in $scope.hosts){
                for(var id in selectedObjects){
                    if(id == $scope.hosts[key].Host.id){
                        objects[id] = $scope.hosts[key].Host.hostname;
                    }
                }
            }
            return objects;
        };

        $scope.getObjectsForExternalCommand = function(){
            var objects = {};
            var selectedObjects = MassChangeService.getSelected();
            for(var key in $scope.hosts){
                for(var id in selectedObjects){
                    if(id == $scope.hosts[key].Host.id){
                        objects[id] = $scope.hosts[key];
                    }

                }
            }
            return objects;
        };


        $scope.linkForCopy = function(){
            var ids = Object.keys(MassChangeService.getSelected());
            return ids.join(',');
        };

        $scope.linkForEditDetails = function(){
            var ids = Object.keys(MassChangeService.getSelected());
            return ids.join(',');
        };

        $scope.changepage = function(page){
            $scope.undoSelection();
            if(page !== $scope.currentPage){
                $scope.currentPage = page;
                $scope.load();
            }
        };


        //Fire on page load
        $scope.loadColumns(); // load column config
        defaultFilter();
        SortService.setCallback($scope.load);

        jQuery(function(){
            $("input[data-role=tagsinput]").tagsinput();
        });

        $scope.$watch('filter', function(){
            $scope.currentPage = 1;
            $scope.undoSelection();
            $scope.load();
        }, true);


        $scope.$watch('massChange', function(){
            MassChangeService.setSelected($scope.massChange);
            $scope.selectedElements = MassChangeService.getCount();
        }, true);

        $scope.$watch('data.containerFilter', function(){
            var searchString = $scope.data.containerFilter.toLowerCase();

            if(searchString === ''){
                $scope.containers = $scope.containersFromApi;
                return true;
            }

            $scope.containers = [];
            for(var key in $scope.containersFromApi){
                var containerName = $scope.containersFromApi[key].value.name.toLowerCase();
                if(containerName.match(searchString)){
                    $scope.containers.push($scope.containersFromApi[key]);
                }
            }

        }, true);

        $scope.getDowntimeDetails = function (id) {
            var selector = 'downtimeBrowsertip_' + id;
            $http.get("/hosts/browser/" + Number(id) + ".json", {
                params: {
                    'angular': true
                }
            }).then(function (result) {
                var html = '<div>';
                var text1 = '';
                var text2= '';
                var text3 = '';
                var text4 = '';
                var text5 = '';
                var end = '</div>';
                var title = '';
                if(result.data.downtime.scheduledStartTime && result.data.downtime.scheduledEndTime ) {
                    text1 = "<h4>Downtime:</h4>";
                    text2 = "Start: " + result.data.downtime.scheduledStartTime + "<br/>";
                    text3 = "End: " + result.data.downtime.scheduledEndTime + "<br/>";
                    text4 = "Comment: " + result.data.downtime.commentData + "<br/>";
                    text5 = "Author: " + result.data.downtime.authorName + "<br/>";
                    title = html.concat(text1, text2, text3, text4, text5, end);
                } else {
                    html = '<div>';
                    text1 = "<h5>No Downtime</h5>";
                    title = html.concat(text1, end);
                }
                //$('[data-toggle="popover"]').popover('dispose');
                $('#' + selector).popover({
                    delay: 200,
                    placement: "right",
                    template: '<div class="popover" role="tooltip"><div class="arrow"></div><h3 class="popover-header"></h3><div class="popover-body"></div></div>',
                    trigger: 'hover focus',
                    content: title,
                    html: true
                });
                $('#' + selector).popover('show');
            });
        };

        $scope.getAckDetails = function(id){
            var selector = 'ackBrowsertip_' + id;
            $http.get("/hosts/browser/" + Number(id) + ".json", {
                params: {
                    'angular': true
                }
            }).then(function (result) {
                var html = '<div>';
                var text1 = '';
                var text2= '';
                var text3 = '';
                var text4 = '';
                var end = '</div>';
                var title = '';
                if(result.data.acknowledgement.comment_data && result.data.acknowledgement.author_name &&  result.data.acknowledgement.entry_time) {
                    if(result.data.acknowledgement.is_sticky){
                        text1 = "<h4>State of host is acknowledged(sticky)</h4>";
                    } else {
                        text1 = "<h4>State of host is acknowledged</h4>";
                    }
                    text2 = "Set by: " + result.data.acknowledgement.author_name + "<br/>";
                    text3 = "Set at: " + result.data.acknowledgement.entry_time + "<br/>";
                    text4 = "Comment: " + result.data.acknowledgement.comment_data + "<br/>";
                    title = html.concat(text1, text2, text3, text4, end);
                } else {
                    html = '<div>';
                    text1 = "<h4>Not acknowledeged</h4>";
                    title = html.concat(text1, end);
                }
                //$('[data-toggle="popover"], .popover').popover('dispose');
                $('#' + selector).popover({
                    delay: 200,
                    placement: "right",
                    template: '<div class="popover" role="tooltip"><div class="arrow"></div><h3 class="popover-header"></h3><div class="popover-body"></div></div>',
                    content: title,
                    trigger: 'hover focus',
                    html: true
                });
                $('#' + selector).popover('show');
            }, function errorCallback(result){
                $('#' + selector).popover('dispose');

            });
        };
        $scope.delPopover = function(){
            $('[data-toggle="popover"]').popover('dispose');
        };

        $scope.$on('$destroy', function() {
            $('[data-toggle="popover"]').popover('dispose');
        });

    });
