app.controller('RiskCtrl',['$scope', 'SelectBox', 'Risk', '$http', '$window', 'config', 'Upload' ,function($scope, SelectBox, Risk, $http, $window, config, Upload){

  var newRiskCount = 0;
  $scope.selected = 0;
  $scope.riskTab = [];
  $scope.risk = {};
  $scope.editMode = true;
  $scope.risk.error = false;
  $scope.tab1 = 2;

  $scope.team = SelectBox.list({type: "team"});
  $scope.impact = SelectBox.list({type: "impact"});
  $scope.category = SelectBox.list({type: "category"});
  $scope.location = SelectBox.list({type: "location"});
  $scope.technology = SelectBox.list({type: "technology"});
  $scope.regulation = SelectBox.list({type: "regulation"});
  $scope.likelihood = SelectBox.list({type: "likelihood"});
  $scope.source = SelectBox.list({type: "source"});
  $scope.user = SelectBox.list({type: "user"});

  $scope.scoringMethod = [
    { value: "1", name: "Classic" },
    { value: "2", name: "CVSS" },
    { value: "3", name: "DREAD" },
    { value: "4", name: "OWASP"},
    { value: "5", name: "Custom"}
  ];

  var addRiskTab = function(risk, title, id) {
    $scope.riskTab.unshift({
      id: id,
      title: title,
      risk: risk
    });
    $scope.selectRisk(0);
  };

  $scope.newRisk = function() {
    var title = "New Risk";
    if (newRiskCount) {
      title = title + "(" + newRiskCount + ")";
    }
    addRiskTab(new Risk(), title);
    newRiskCount++;
  };

  $scope.closeRisk = function(index) {
    $scope.riskTab.splice(index, 1);

    if($scope.riskTab.length) {
      $scope.selectRisk(index - 1);
    }else {
      newRiskCount = 0;
    }
  };

  $scope.selectRisk = function(index) {
    if (index < 0) index = 0;
    $scope.risk = $scope.riskTab[index].risk;
    $scope.selected = index;

    if ($scope.risk.id){
      $scope.editMode = false;
    }else{
      $scope.editMode = true;
    }
  };

  $scope.save = function() {

    if (angular.isUndefined($scope.risk.subject)){
      $scope.risk.error = true;
      return false;  
    }
    $scope.risk.error = false;

    $http({
      method: 'POST',
      data: {'data': $scope.risk},
      url: config.apiBasePath+'risk/save'
    }).then(function successCallback(response) {
        // this callback will be called asynchronously
        // when the response is available
      }, function errorCallback(response) {
        // called asynchronously if an error occurs
        // or server returns response with an error status.
      });
  };

  $scope.clearRisk = function() {
    $scope.risk = new Risk();
  };

  $scope.$on('$stateChangeStart', function(e, to) {
    if(to.name !== 'risk.new' && to.name !== 'risk.profile'){
      $scope.selected = -1;
    }
  });

  $scope.openForm = function() {
    $scope.newRisk();
    $scope.editMode = true;
  };

  $scope.openRisk = function() {
    Risk.get(function(r) {
      addRiskTab(r, r.subject, r.id);
    });
  };

  // $scope.openForm();
  //$scope.openRisk();
  
//     $http({
//     method: 'GET',
//     url: config.apiBasePath+'risk/list'
//    }).then(function successCallback(response) {
//       $scope.risk_records = response.data;
//         
//     }, function errorCallback(response) {
//        //console.log('error');
//       // called asynchronously if an error occurs
//       // or server returns response with an error status.
//     });
        
     //pagination in review list
     
    $scope.todos = [];
    $scope.currentPage = 1;
    $scope.numPerPage = 5;
    $scope.maxSize = 5;
    $scope.count = 1;

    $http({
     method: 'GET',
     url: config.apiBasePath+'risk/count'
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
     url: config.apiBasePath+'risk/list/1/5'
   }).then(function successCallback(response) {
         $scope.risk_records = response.data;
        for (var i = response.data.length - 1; i >= 0; i--) 
        {
          $scope.todos.push(response.data[i])
        };

     }, function errorCallback(response) {
        //console.log('error');
       // called asynchronously if an error occurs
       // or server returns response with an error status.
     });

    $scope.$watch('currentPage', function(newPage){
      var startVal = (5 * (newPage - 1)) + 1;
      var endVal   = 5 * newPage
      $http({
        method: 'GET',
        url: config.apiBasePath+'risk/list/'+startVal+'/'+endVal
   }).then(function successCallback(response) {
         $scope.risk_records= response.data;
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
     
    $scope.reviewRisk = function(id) {
      //url: config.apiBasePath+'risk/list'
      if(id){
           $window.sessionStorage.setItem('review_id', id);
           window.location = config.siteBasePath+"/management/profile.php";
      }
     
     }

    $scope.showReviewRiskForm = function(id) {
          //url: config.apiBasePath+'risk/list'
          if(id){
               $window.sessionStorage.setItem('review_id', id);
              $window.sessionStorage.setItem('tab1', 3);
               window.location = config.siteBasePath+"/management/profile.php";
              $window.sessionStorage.setItem('tab1', 3);
          }

         }
    // upload file
    // upload on file select or drop
    $scope.upload = function (file) {
        Upload.upload({
            url: config.siteBasePath+'management/upload.php',
            data: {file: file, 'user': $scope.risk.user}
        }).then(function (resp) {
            console.log('Success ' + resp.config.data.file.name + 'uploaded. Response: ' + resp.data);
        }, function (resp) {
            console.log('Error status: ' + resp.status);
        }, function (evt) {
            var progressPercentage = parseInt(100.0 * evt.loaded / evt.total);
            console.log('progress: ' + progressPercentage + '% ' + evt.config.data.file.name);
        });
    }

}]);

app.controller('ProfileCtrl',['$scope', 'SelectBox', '$http', 'config', '$window' ,function($scope, SelectBox, $http, config, $window){
  
  //var risk_id = $window.sessionStorage.getItem('risk_id');

  $scope.team = SelectBox.list({type: "team"});
  $scope.impact = SelectBox.list({type: "impact"});
  $scope.category = SelectBox.list({type: "category"});
  $scope.location = SelectBox.list({type: "location"});
  $scope.technology = SelectBox.list({type: "technology"});
  $scope.regulation = SelectBox.list({type: "regulation"});
  $scope.likelihood = SelectBox.list({type: "likelihood"});
  $scope.source = SelectBox.list({type: "source"});
  $scope.user = SelectBox.list({type: "user"});
  $scope.tab1 = $window.sessionStorage.getItem('tab1');

  $http({
    method: 'GET',
    url: config.apiBasePath+'risk'
  }).then(function successCallback(response) {
      $scope.risk = response.data;
    // when the response is available
  }, function errorCallback(response) {
    // called asynchronously if an error occurs
    // or server returns response with an error status.
  });

  $scope.newRisk = function(){
    window.location = config.siteBasePath+"management/index.php";
  }

  $http({
    method: 'GET',
    url: config.apiBasePath+'mitigation'
  }).then(function successCallback(response) {
    $scope.mitigation = response.data;

    // when the response is available
  }, function errorCallback(response) {
    // called asynchronously if an error occurs
    // or server returns response with an error status.
  });
    $http({
      method: 'GET',
      url: config.apiBasePath+'mitigation/selectvalues/strategy'
    }).then(function successCallback(response) {
      $scope.planningStrategy = response.data;
    }, function errorCallback(response) {
      console.log('error');
      // called asynchronously if an error occurs
      // or server returns response with an error status.
    });

  $http({
    method: 'GET',
    url: config.apiBasePath+'mitigation/selectvalues/mitigationOwner'
  }).then(function successCallback(response) {
    $scope.mitigationOwner = response.data;
  }, function errorCallback(response) {
    console.log('error');
    // called asynchronously if an error occurs
    // or server returns response with an error status.
  });

  $http({
    method: 'GET',
    url: config.apiBasePath+'mitigation/selectvalues/mitigationCost'
  }).then(function successCallback(response) {
    $scope.mitigationCost = response.data;
  }, function errorCallback(response) {
    console.log('error');
    // called asynchronously if an error occurs
    // or server returns response with an error status.
  });

  $http({
    method: 'GET',
    url: config.apiBasePath+'mitigation/selectvalues/mitigationEffort'
  }).then(function successCallback(response) {
    $scope.mitigationEffort = response.data;
  }, function errorCallback(response) {
    console.log('error');
    // called asynchronously if an error occurs
    // or server returns response with an error status.
  });

  $http({
    method: 'GET',
    url: config.apiBasePath+'mitigation/selectvalues/mitigationTeam'
  }).then(function successCallback(response) {
    $scope.mitigationTeam = response.data;
  }, function errorCallback(response) {
    console.log('error');
    // called asynchronously if an error occurs
    // or server returns response with an error status.
  });

  $scope.save = function() {
    $http({
      method: 'POST',
      data: {'data': $scope.risk},
      url: config.apiBasePath+'mitigation/save'
    }).then(function successCallback(response) {
      // this callback will be called asynchronously
      // when the response is available
    }, function errorCallback(response) {
      // called asynchronously if an error occurs
      // or server returns response with an error status.
    });
  };

  $scope.showRisk = function (id)
  {
    $window.sessionStorage.setItem('risk_id', id);
    window.location = config.siteBasePath+"management/profile.php";
  }
  
     $http({
        method: 'GET',
        url: config.apiBasePath+'risk/selectvalues/review'
    }).then(function successCallback(response) {
        var review_id = $window.sessionStorage.getItem('review_id');
        $scope.reviewValue = response.data;
    }, function errorCallback(response) {
        console.log('error');
        // called asynchronously if an error occurs
        // or server returns response with an error status.
    });
    
     $http({
        method: 'GET',
        url: config.apiBasePath+'risk/selectvalues/nextStep'
    }).then(function successCallback(response) {
        var review_id = $window.sessionStorage.getItem('review_id');
        $scope.nextStepValue = response.data;
    }, function errorCallback(response) {
        console.log('error');
        // called asynchronously if an error occurs
        // or server returns response with an error status.
    });
    
        $http({
        method: 'GET',
        url: config.apiBasePath+'risk/selectvalues/Comment'
    }).then(function successCallback(response) {
        var review_id = $window.sessionStorage.getItem('review_id');
        $scope.commentValue = response.data;
    }, function errorCallback(response) {
        console.log('error');
        // called asynchronously if an error occurs
        // or server returns response with an error status.
    });
    
    
      $http({
    method: 'GET',
    url: config.apiBasePath+'risk/review/status'
    }).then(function successCallback(response) {
        //var review_id = $window.sessionStorage.getItem('review_id');
        $scope.reviewList = response.data;
      // when the response is available
    }, function errorCallback(response) {
      // called asynchronously if an error occurs
      // or server returns response with an error status.
    });
    
    $scope.saveReviewData = function(form) {
    $http({
      method: 'POST',
      data: {'data': $scope.reviewList},
      url: config.apiBasePath+'risk/saveReviewData'
    }).then(function successCallback(response) {
      // this callback will be called asynchronously
      // when the response is available
    }, function errorCallback(response) {
      // called asynchronously if an error occurs
      // or server returns response with an error status.
    });
  };
   

}]);

app.controller('commentCtrl',['$scope', '$http', 'config', '$window' ,function($scope, $http, config, $window){

  var risk_id = $window.sessionStorage.getItem('risk_id');
  $scope.comment_need = false;

  $scope.getData = function (){
    $http({
      method: 'GET',
      url: config.apiBasePath+'risk/comment/'+risk_id
    }).then(function successCallback(response) {
        $scope.comments = response.data;
      // when the response is available
    }, function errorCallback(response) {
      // called asynchronously if an error occurs
      // or server returns response with an error status.
    });
  }

  $scope.getData();

  $( "#accordion" ).accordion();

  $scope.commentSave = function (form){
    if(form.$valid){
      $scope.comment_need = false;
      $http({
        method: 'POST',
        data: {'user': $scope.user, 'comment': $scope.comment},
        url: config.apiBasePath+'risk/comment/save'
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

}]);
