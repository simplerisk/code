var app = angular.module('simplerisk',['ngResource', 'ngFileUpload', 'ui.bootstrap']);

app.value('config', {
  'apiBasePath': 'http://simplerisk.loc/api/',
  'siteBasePath': 'http://simplerisk.loc/',
  'onLoginView': 'risk.new'
});
