# RowReorder for DataTables 

This is the distribution package for the [RowReorder extension](https://datatables.net/extensions/rowreorder) for [DataTables](https://datatables.net/). Only the core software for this library is contained in this package - to be correctly styled, a styling package for RowReorder must also be included. Please see the [npm installation documentation on the DataTables site](https://datatables.net/manual/installation#Node.js-/-NPM) for full details.

RowReorder adds end user reordering of rows in a DataTable through click-and-drag mouse and touch operations. RowReorder provides full support for Editor allowing end users to update sequential information in a fast and easy manner.


## Installation

### Browser

To use DataTables with a simple `<script>` tag, rather than using this package, it is recommended that you use the [DataTables download builder](//datatables.net/download) which can create CDN or locally hosted packages for you, will all dependencies satisfied.

### npm

For installation via npm, yarn and other similar package managers, install this package with your package manager - e.g.:

```
npm install datatables.net
npm install datatables.net-rowreorder
```

Then, to load and initialise DataTables and RowReorder in your code use:

```
import DataTable from 'datatables.net';
import 'datatables.net-rowreorder'

new DataTable('#myTable', {
    // initialisation options
});
```


## Documentation

Full documentation and examples for RowReorder can be found [on the DataTables website](https://datatables.net/extensions/rowreorder).

## Bug / Support

Support for DataTables is available through the [DataTables forums](//datatables.net/forums) and [commercial support options](//datatables.net/support) are available.

### Contributing

If you are thinking of contributing code to DataTables, first of all, thank you! All fixes, patches and enhancements to DataTables are very warmly welcomed. This repository is a distribution repo, so patches and issues sent to this repo will not be accepted. Instead, please direct pull requests to the [DataTables/RowReorder](http://github.com/DataTables/RowReorder). For issues / bugs, please direct your questions to the [DataTables forums](//datatables.net/forums).


## License

This software is released under the [MIT license](//datatables.net/license). You are free to use, modify and distribute this software, but all copyright information must remain.
