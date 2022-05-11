app.factory('SelectBox', ['$resource', 'config', function($resource, config) {
  return $resource(config.apiBasePath + 'get_values/', null, {
    'list': { method: 'GET', isArray: true }
  });
}]);

app.factory('Risk', ['$resource', 'config', function($resource, config) {
  return $resource(config.apiBasePath + 'risk/:id', null, {
    'list': { method: 'GET', isArray: true }
  });
}]);

app.factory('Session', ['$resource', 'config', function($resource, config) {
  return $resource(config.apiBasePath + '/:action', null, {
    'login': { method: 'POST', params: {action: 'login'}},
    'logout': { method: 'GET', params: {action: 'logout'}},
    'getUser': { method: 'GET', params: {action: 'get_user'}}
  });
}]);
