app.controller('MitigationCtrl',['$scope','$http','config', '$window',function($scope, $http, config, $window){
    $scope.todos = [];
    $scope.currentPage = 1;
    $scope.numPerPage = 5;
    $scope.maxSize = 5;
    $scope.count = 1;

    $http({
     method: 'GET',
     url: config.apiBasePath+'mitigation/count'
   }).then(function successCallback(response) {
        $scope.count = response.data.length; 

     }, function errorCallback(response) {
        //console.log('error');
       // called asynchronously if an error occurs
       // or server returns response with an error status.
     });

      $scope.numPages = function (data) {
      return Math.ceil($scope.count / $scope.numPerPage);
      };
    

    $http({
     method: 'GET',
     url: config.apiBasePath+'mitigation/list/1/5'
   }).then(function successCallback(response) {
         $scope.records= response.data;
        for (var i = response.data.length - 1; i >= 0; i--) 
        {
          $scope.todos.push(response.data[i])
        };

     }, function errorCallback(response) {
        //console.log('error');
       // called asynchronously if an error occurs
       // or server returns response with an error status.
     });

     $scope.getMitigationForm = function(id) {
      //url: config.apiBasePath+'risk/list'
      if(id){
           $window.sessionStorage.setItem('review_id', id);
           window.location = config.siteBasePath+"/management/profile.php";
          $window.sessionStorage.setItem("tab1", 2);
      }
     
     }
     
    $scope.$watch('currentPage', function(newPage){
      var startVal = (5 * (newPage - 1)) + 1;
      var endVal   = 5 * newPage
      $http({
        method: 'GET',
        url: config.apiBasePath+'mitigation/list/'+startVal+'/'+endVal
   }).then(function successCallback(response) {
         $scope.records= response.data;
        for (var i = response.data.length - 1; i >= 0; i--) 
        {
          $scope.todos.push(response.data[i])
        };

     }, function errorCallback(response) {
        //console.log('error');
       // called asynchronously if an error occurs
       // or server returns response with an error status.
     });
  });

    $scope.mitigationSave = function (form){
        if(form.$valid){
            $http({
                method: 'GET',
                data: {'mitigationDetails': $scope.user, 'comment': $scope.comment},
                url: config.apiBasePath+'mitigation/save/save'
            }).then(function successCallback(response) {
                $scope.getData();
            }, function errorCallback(response) {
                // called asynchronously if an error occurs
                // or server returns response with an error status.
            });

        } else {
            $scope.comment_need = true;
        }
    }

  $scope.showRisk = function (id){
    $window.sessionStorage.setItem('risk_id', id);
    $window.location = config.siteBasePath+"management/profile.php";
  }

}])  