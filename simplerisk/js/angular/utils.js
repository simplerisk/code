var Utils = {
  getSelectedLabel: function(k, values) {

    for(var i in values) {
      var v = values[i];
      if(v.value == k) {
        return v.name;
      }
    }
    return k;
  }
};
