/*
Template Name: Admin Template
Author: Wrappixel

File: js
*/
// ==============================================================
// Auto select left navbar
// ==============================================================
$(function () {
    "use strict";
    var url = window.location + "";
    var url_without_params = url.split('?')[0];
    var url_without_hash = url.split('#')[0];

    var path = url.replace(
        window.location.protocol + "//" + window.location.host + "/",
        ""
    );

    var path_without_params = url.split('?')[0].replace(
        window.location.protocol + "//" + window.location.host + "/",
        ""
    );

    var path_without_hash = url.split('#')[0].replace(
        window.location.protocol + "//" + window.location.host + "/",
        ""
    );

    var element = $("ul#sidebarnav a").filter(function () {
        return this.href === url || this.href === path || this.href === path_without_params || this.href === url_without_params || this.href === path_without_hash || this.href === url_without_hash;
    });

    // There are pages nested under other pages that doesn't have associated menu items. In these cases the 'active' class is set on the menu item in the php code
    // so the 'element' variable would be empty
    if (element.length === 0) {
        element = $("ul#sidebarnav li.sidebar-item.active a");
    } else {
        element.parentsUntil(".sidebar-nav").each(function (index) {
            if ($(this).is("li") && $(this).children("a").length !== 0) {
                $(this).children("a").addClass("active");
                $(this).parent("ul#sidebarnav").length === 0
                ? $(this).addClass("active")
                : $(this).addClass("selected");
            } else if (!$(this).is("ul") && $(this).children("a").length === 0) {
                $(this).addClass("selected");
            } else if ($(this).is("ul")) {
                $(this).addClass("in");
            }
        });
        element.addClass("active");
    }

    // the scrollIntoViewIfNeeded function isn't implemented in some browsers
    if (element.length) {
        // element.length - 1 is used to target the last element in the list
        if (functionExists(element[element.length - 1].scrollIntoViewIfNeeded)) {
            element[element.length - 1].scrollIntoViewIfNeeded({behavior:"smooth"});
        } else {
            element[element.length - 1].scrollIntoView({behavior:"smooth"});
        }
    }

    $("#sidebarnav a").on("click", function (e) {
        if (!$(this).hasClass("active")) {
            // hide any open menus and remove all other classes
            $("ul", $(this).parents("ul:first")).removeClass("in");
            $("a", $(this).parents("ul:first")).removeClass("active");

            // open our new menu and add the open class
            $(this).next("ul").addClass("in");
            $(this).addClass("active");
        } else if ($(this).hasClass("active")) {
            $(this).removeClass("active");
            $(this).parents("ul:first").removeClass("active");
            $(this).next("ul").removeClass("in");
        }
    });
    $("#sidebarnav >li >a.has-arrow").on("click", function (e) {
        e.preventDefault();
    });
});

function  functionExists(f) {
    return typeof f === 'function';
}