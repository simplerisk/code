
// Add the reverse array functionality to jquery result arrays
jQuery.fn.reverse = [].reverse;


$(function () {
  "use strict";

  // ==============================================================
  // Theme options
  // ==============================================================

  // this is for close icon when navigation open in mobile view
  $(".nav-toggler").on("click", function () {
    $("#main-wrapper").toggleClass("show-sidebar");
    $(".nav-toggler i").toggleClass("ti-menu");
  });
  $(".nav-lock").on("click", function () {
    $("body").toggleClass("lock-nav");
    $(".nav-lock i").toggleClass("mdi-toggle-switch-off");
    $("body, .page-wrapper").trigger("resize");
  });

  // ==============================================================
  // Right sidebar options
  // ==============================================================
  $(function () {
    $(".service-panel-toggle").on("click", function () {
      $(".customizer").toggleClass("show-service-panel");
    });
    $(".page-wrapper").on("click", function () {
      $(".customizer").removeClass("show-service-panel");
    });
  });
  // ==============================================================
  // This is for the floating labels
  // ==============================================================
  $(".floating-labels .form-control")
    .on("focus blur", function (e) {
      $(this)
        .parents(".form-group")
        .toggleClass("focused", e.type === "focus" || this.value.length > 0);
    })
    .trigger("blur");

    // ==============================================================
    //tooltip
    // ==============================================================
    $(function () {
      $('[data-toggle="tooltip"]').tooltip();
    });
  // ==============================================================
  //Popover
  // ==============================================================
  $(function () {
    $('[data-toggle="popover"]').popover();
  });

  // ==============================================================
  // Resize all elements
  // ==============================================================
  $("body, .page-wrapper").trigger("resize");
  $(".page-wrapper").delay(20).show();


  //****************************
  /* This is for the mini-sidebar if width is less then 1170*/
  //****************************
/*  var setsidebartype = function () {
	
	// It's here to eventually be able to support 3-state menu switching of full->mini->none->full
	if ($("#main-wrapper").attr("data-sidebartype") == 'no-sidebar') {
		return true;
	}
	
    var width = window.innerWidth > 0 ? window.innerWidth : this.screen.width;
    if (width < 1170) {
      $("#main-wrapper").attr("data-sidebartype", "mini-sidebar");
    } else {
      $("#main-wrapper").attr("data-sidebartype", "full");
    }
  };
  $(window).ready(setsidebartype);
  $(window).on("resize", setsidebartype);*/


  // The logic that should be executed when the size of the content is changing
  $(document).on('simplerisk.content.resize', function () {
    // Readjust the datatable column headers when the sidebar's size was changed
    if ($.fn.dataTable) {
        $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust();
    }

    // Readjust the treegrids when the sidebar's size was changed
    // Had to add he setTimeout() because if there're more than one treegrids on the page
    // then there needs to be a little delay between those calls
    if ($.fn.treegrid) {
        $('table.datagrid-f').each(function() {setTimeout(() => {$(this).treegrid("resize");}, 1);});
    }
  });

  //****************************
  /* This is for sidebartoggler*/
  //****************************
  $(".sidebartoggler").on("click", function () {
	
    $("#main-wrapper").toggleClass("no-sidebar");
    if ($("#main-wrapper").hasClass("no-sidebar")) {
      $(".sidebartoggler").prop("checked", !0);
      $("#main-wrapper").attr("data-sidebartype", "no-sidebar");
    } else {
      $(".sidebartoggler").prop("checked", !1);
      $("#main-wrapper").attr("data-sidebartype", "full");
    }

    // Trigger Simplerisk's own logic related to the resize of the content part of the page
    $(document).trigger('simplerisk.content.resize');
  });

  // Trigger Simplerisk's own logic related to the resize of the content part of the page
  $(window).on("resize", () => $(document).trigger('simplerisk.content.resize'));

});
