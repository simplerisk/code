app.config(['$stateProvider', '$urlRouterProvider',function($stateProvider, $urlRouterProvider) {

  $urlRouterProvider.otherwise("/");

  $stateProvider
    .state('login', {
      url: '/login',
      templateUrl: "view/login.html"
    })
    .state('risk', {
      url: '/risk',
      templateUrl: "risk/tabs.html",
      controller: "RiskCtrl"
    })
    .state('risk.new', {
      url: '/new',
      templateUrl: "risk/submit_risk.html"
    })
    .state('risk.mitigation', {
      url: "/mitigation",
      template: "mitigation",
    })
    .state('risk.review', {
      url: "/review",
      template: "review",
    })
    .state('risk.project', {
      url: "/project",
      template: "project",
    })
    .state('risk.profile', {
      url: "/profile",
      templateUrl: "risk/profile.html"
    })
    .state('asset', {
      url: "/asset",
      template: "asset"
    })
    .state('assessment', {
      url: "/assessment",
      template: "assessment"
    })
    .state('reporting', {
      url: "/reporting",
      template: "reporting"
    })
    .state('configuration', {
      url: "/configuration",
      template: "configuration"
    });
}]);
